<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/Repositories/WellnessRepository.php';
use App\Repositories\WellnessRepository;
use App\Support\WellnessEngine;

/** Captures prepared INSERT parameters in PHP only. It cannot connect to MySQL.
 *  SELECT fixtures are queued explicitly instead of simulating a SQL engine. */
final class WellnessPlanPdo extends PDO
{
    public array $reads = [];
    public array $plans = [];
    public int $nextId = 1000;
    public bool $transaction = false;
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false { $statement = new WellnessPlanStatement($this, $query); $statement->execute(); return $statement; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new WellnessPlanStatement($this, $query); }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->nextId; }
    public function beginTransaction(): bool { if ($this->transaction) { throw new RuntimeException('Nested transaction.'); } return $this->transaction = true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function read(string $contains, array|callable $rows): void { $this->reads[] = [$contains, $rows]; }
    public function inserted(string $table): array { $rows = array_values(array_filter($this->plans, static fn (array $plan): bool => $plan['table'] === $table)); return $rows[count($rows)-1]['row']; }
}
final class WellnessPlanStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private WellnessPlanPdo $connection, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if ($this->sql === 'SELECT DATABASE()') { $this->rows = [['well_yu_db']]; return true; }
        if (str_starts_with($this->sql, 'SELECT')) {
            [$contains, $rows] = array_shift($this->connection->reads) ?? throw new RuntimeException('Unplanned SELECT.');
            if (!str_contains($this->sql, $contains)) { throw new RuntimeException('Unexpected SELECT order: ' . $this->sql); }
            $this->rows = is_callable($rows) ? $rows($this->connection) : $rows; return true;
        }
        if (!preg_match('/^INSERT INTO `well_yu_db`\.`(welyu_\w+)` \((.*?)\) VALUES \((.*?)\)$/D', $this->sql, $match)) { throw new RuntimeException('Only isolated extension INSERT planning is allowed.'); }
        if (!in_array($match[1], WellnessRepository::TABLES, true)) { throw new RuntimeException('Unexpected insert target.'); }
        preg_match_all('/`([a-z_][a-z0-9_]*)`/', $match[2], $fields);
        preg_match_all('/:([a-z_][a-z0-9_]*)/', $match[3], $placeholders);
        if ($fields[1] !== $placeholders[1] || $fields[1] !== array_keys($params ?? [])) { throw new RuntimeException('Fields and bound parameters differ.'); }
        $row = ['id' => ++$this->connection->nextId] + ($params ?? []);
        $this->connection->plans[] = ['table' => $match[1], 'sql' => $this->sql, 'row' => $row]; return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed { return $this->rows[0] ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return isset($this->rows[0]) ? array_values($this->rows[0])[$column] : false; }
}
$checks=0;$check=static function(bool $ok,string $message)use(&$checks):void{if(!$ok)throw new RuntimeException($message);$checks++;};
$db=new WellnessPlanPdo();$repository=new WellnessRepository($db,static fn()=>[]);
$actor=['id'=>11,'role'=>'investor'];
$proposal=['id'=>1,'owner_user_id'=>11,'review_status'=>'submitted','property_id'=>null,'payload_json'=>'{"concept":"Synthetic concept","intended_users":"Synthetic group","location":"Synthetic site"}','title'=>'Illustrative proposal','mode'=>'open'];
$keys=array_keys(WellnessEngine::CRITERIA);$config=WellnessEngine::configuration(['name'=>'Synthetic provisional fixture','weights'=>array_combine($keys,[20,15,20,15,15,15])]);
$configuration=['id'=>7,'name'=>$config['name'],'payload_json'=>json_encode($config),'validation_status'=>'provisional','validation_reference'=>null];
$input=['proposal_id'=>1,'configuration_id'=>7,'criteria'=>[]];foreach($keys as$key)$input['criteria'][$key]=['value'=>50,'source'=>'Synthetic source','observed_on'=>'2026-01-01'];
$db->read('`welyu_proposals`',[$proposal]);$db->read('`welyu_configurations`',[$configuration]);$db->read('SELECT id FROM `well_yu_db`.`welyu_assessments`',[]);
$db->read('`welyu_assessments`',static fn($db)=>[$db->inserted('welyu_assessments')]);$db->read('`welyu_proposals`',[$proposal]);
$assessment=$repository->assess($input,$actor);$planned=$db->inserted('welyu_assessments');
$check((float)$assessment['result']['mce_score']===50.0,'Actual repository assessment save path computes and returns its frozen score.');
$check(strlen($planned['input_sha256'])===64&&json_decode($planned['snapshot_json'],true)['configuration']['id']===7,'Hash-bearing fields and exact configuration are bound for insertion.');
$check(WellnessEngine::hash(json_decode($planned['snapshot_json'],true))===$planned['input_sha256'],'An assessment snapshot retains its canonical fingerprint after a JSON round trip.');
$check($planned['created_by_user_id']===11&&$planned['proposal_id']===1,'Save plan binds current actor and authorized proposal.');
$db->read('`welyu_assessments`',[$planned]);$db->read('`welyu_proposals`',[$proposal]);
$second=$planned;$second['id']=999;$second['input_sha256']=str_repeat('b',64);
$db->read('`welyu_assessments`',[$second]);$db->read('`welyu_proposals`',[$proposal]);
foreach([1,1]as$_){$db->read('`welyu_proposals`',[$proposal]);$db->read('`welyu_scenarios`',[]);}
$db->read('`welyu_comparisons`',static fn($db)=>[$db->inserted('welyu_comparisons')]);
foreach([$planned,$second]as$row){$db->read('`welyu_assessments`',[$row]);$db->read('`welyu_proposals`',[$proposal]);}
$comparison=$repository->compare(['name'=>'Illustrative A/B comparison','assessment_ids'=>[$planned['id'],999]],$actor);
$check(count($comparison['snapshot']['assessments'])===2,'Comparison save freezes both authorized assessment versions.');
$check(strlen($db->inserted('welyu_comparisons')['snapshot_sha256'])===64,'Comparison fingerprint is accepted and bound.');
$check(WellnessEngine::hash($comparison['snapshot'])===$comparison['snapshot_sha256'],'A saved comparison retains its canonical fingerprint after a JSON round trip.');
$check(count(array_filter($db->plans,static fn($p)=>$p['table']==='welyu_comparison_items'))===2,'Both comparison membership relationships are planned atomically.');
$check(!$db->inTransaction(),'Comparison transaction commits its plans.');
$db->read('`welyu_services`',[['id'=>101,'owner_user_id'=>11,'review_status'=>'submitted','payload_json'=>'{}']]);
$documentId=$repository->addDocument('service',101,['original_name'=>'Synthetic evidence.pdf','storage_name'=>str_repeat('a',32).'.pdf','mime_type'=>'application/pdf','size_bytes'=>1024,'sha256'=>str_repeat('c',64)],$actor);
$document=$db->inserted('welyu_documents');$check($document['id']===$documentId&&$document['sha256']===str_repeat('c',64)&&$document['service_id']===101,'Document hash and authorized parent are accepted without touching a file or database.');
$check($db->reads===[],'Every planned read was consumed in the expected save/export-access sequence.');
echo "Wellness persistence plans: {$checks} checks passed; PDO methods captured in PHP only, no database connection or SQL execution.\n";
