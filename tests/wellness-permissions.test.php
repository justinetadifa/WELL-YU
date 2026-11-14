<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/Repositories/WellnessRepository.php';
use App\Repositories\WellnessRepository;

/** This double handles SELECTs in PHP arrays; it never opens a database. */
final class WellnessReadDouble extends PDO
{
    public array $data = [];
    public string $database = 'well_yu_db';
    public int $writeAttempts = 0;
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false { $statement = new WellnessStatementDouble($this, $query); $statement->execute(); return $statement; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new WellnessStatementDouble($this, $query); }
}
final class WellnessStatementDouble extends PDOStatement
{
    private array $rows = [];
    public function __construct(private WellnessReadDouble $connection, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if (!str_starts_with($this->sql, 'SELECT')) { $this->connection->writeAttempts++; throw new RuntimeException('No writes allowed in this test.'); }
        if ($this->sql === 'SELECT DATABASE()') { $this->rows = [[$this->connection->database]]; return true; }
        preg_match('/FROM `well_yu_db`\.`(welyu_\w+)`/', $this->sql, $match);
        $row = $this->connection->data[$match[1] ?? ''][(int) ($params['id'] ?? 0)] ?? null;
        $this->rows = $row === null ? [] : [$row]; return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed { return $this->rows[0] ?? false; }
    public function fetchColumn(int $column = 0): mixed { return isset($this->rows[0]) ? array_values($this->rows[0])[$column] : false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
$checks=0;$check=static function(bool $condition,string $message)use(&$checks):void{if(!$condition)throw new RuntimeException($message);$checks++;};
$denied=static function(callable $action,string $message)use($check):void{try{$action();}catch(OutOfBoundsException $error){$check(true,$message);return;}$check(false,$message);};
$db=new WellnessReadDouble();
$db->data['welyu_proposals']=[1=>['id'=>1,'owner_user_id'=>11,'review_status'=>'reviewed','payload_json'=>'{}','property_id'=>null],2=>['id'=>2,'owner_user_id'=>22,'review_status'=>'reviewed','payload_json'=>'{}','property_id'=>null],3=>['id'=>3,'owner_user_id'=>11,'review_status'=>'submitted','payload_json'=>'{}','property_id'=>77]];
$db->data['welyu_services']=[101=>['id'=>101,'owner_user_id'=>22,'review_status'=>'reviewed','payload_json'=>'{}']];
$db->data['welyu_documents']=[9=>['id'=>9,'service_id'=>101,'need_id'=>null,'proposal_id'=>null,'storage_name'=>'private-test.pdf']];
$db->data['welyu_assessments']=[31=>['id'=>31,'proposal_id'=>2,'snapshot_json'=>'{"property_id":null}','result_json'=>'{}']];
$db->data['welyu_comparisons']=[50=>['id'=>50,'owner_user_id'=>11,'snapshot_json'=>'{"assessments":[{"id":31}]}']];
$owner=['id'=>11,'role'=>'investor'];$other=['id'=>22,'role'=>'investor'];$reviewer=['id'=>33,'role'=>'admin','department'=>'ASSESSOR'];
$repository=new WellnessRepository($db,static function(int $id,?array $user):array{if($id===77)throw new OutOfBoundsException('Property no longer visible.');return ['id'=>$id];});
$check($repository->record('proposal',1,$owner,true)['id']===1,'Owner can read proposal.');
$denied(fn()=>$repository->record('proposal',1,$other),'Reviewed proposal remains private.');
$denied(fn()=>$repository->record('proposal',1,null),'Guest cannot read proposal.');
$check($repository->record('proposal',1,$reviewer,true)['id']===1,'Existing authorized review role may inspect.');
$check($repository->record('service',101,null)['id']===101,'Reviewed service has a public view.');
$denied(fn()=>$repository->document(9,$owner),'Public service access does not expose private evidence.');
$check($repository->document(9,$other)['id']===9,'Service owner can download evidence.');
$check($repository->document(9,$reviewer)['id']===9,'Authorized reviewer can download evidence.');
$denied(fn()=>$repository->assessment(31,$owner),'Assessment permissions follow proposal.');
$denied(fn()=>$repository->comparison(50,$owner),'Export rechecks every snapshot parent, not only comparison ownership.');
$denied(fn()=>$repository->record('proposal',3,$owner,true),'Linked property visibility is rechecked.');
$check($db->writeAttempts===0,'Every permission check was read-only.');
$db->database='wrong_database';$blocked=false;try{new WellnessRepository($db,static fn()=>[]);}catch(RuntimeException $error){$blocked=true;}$check($blocked,'Repository refuses another active database.');
echo "Wellness permissions: {$checks} checks passed against a SELECT-only PHP double; no real SQL execution.\n";
