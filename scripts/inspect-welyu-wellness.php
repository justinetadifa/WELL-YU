<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (array_diff(array_slice($argv, 1), ['--plan']) !== []) { fwrite(STDERR, "Usage: php scripts/inspect-welyu-wellness.php [--plan]\nNo --apply option exists.\n"); exit(1); }
define('WELL_YU_INSPECTION_FUNCTIONS_ONLY', true);
require __DIR__ . '/inspect-well-yu-database.php';
require_once dirname(__DIR__) . '/app/Repositories/WellnessRepository.php';
use App\Repositories\WellnessRepository;
$root=dirname(__DIR__);
try {
    $config=require $root.'/app/config.php';
    if($config['db']['name']!=='well_yu_db'||$config['app']['auto_migrate']||$config['app']['auto_seed']||$config['db']['auto_create'])throw new RuntimeException('Isolation/disabled-automation check failed.');
    foreach(['host','charset']as$key)if(preg_match('/[;\x00-\x20]/',(string)$config['db'][$key]))throw new RuntimeException('Unsafe connection component.');
    $pdo=new PDO(sprintf('mysql:host=%s;port=%d;dbname=well_yu_db;charset=utf8mb4',$config['db']['host'],$config['db']['port']),$config['db']['user'],$config['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
    if(wy_inspection_query($pdo,'SELECT DATABASE() AS active_database')[0]['active_database']!=='well_yu_db')throw new RuntimeException('Wrong active database.');
    $sql=(string)file_get_contents($root.'/database/well-yu/01-wellness.sql');
    preg_match_all('/CREATE TABLE IF NOT EXISTS (welyu_\w+) \((.*?)^\) ENGINE=[^;]+;/ms',$sql,$matches,PREG_SET_ORDER);$expected=[];
    foreach($matches as$match)$expected[$match[1]]=['ddl'=>$match[0],'body'=>$match[2]];
    if(array_keys($expected)!==WellnessRepository::TABLES)throw new RuntimeException('Unexpected migration inventory.');
    $tables=[];foreach(wy_inspection_query($pdo,"SELECT TABLE_NAME, ENGINE FROM information_schema.tables WHERE TABLE_SCHEMA='well_yu_db' AND TABLE_TYPE='BASE TABLE'")as$row)$tables[$row['TABLE_NAME']]=$row;
    $columns=[];foreach(wy_inspection_query($pdo,"SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.columns WHERE TABLE_SCHEMA='well_yu_db'")as$row)$columns[$row['TABLE_NAME']][$row['COLUMN_NAME']]=$row;
    $indexes=[];foreach(wy_inspection_query($pdo,"SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.statistics WHERE TABLE_SCHEMA='well_yu_db' ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX")as$row){$indexes[$row['TABLE_NAME']][$row['INDEX_NAME']]['columns'][]=$row['COLUMN_NAME'];$indexes[$row['TABLE_NAME']][$row['INDEX_NAME']]['unique']=(int)$row['NON_UNIQUE']===0;}
    $fks=wy_inspection_query($pdo,"SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.key_column_usage WHERE TABLE_SCHEMA='well_yu_db' AND REFERENCED_TABLE_NAME IS NOT NULL");
    $deleteRules=[];foreach(wy_inspection_query($pdo,"SELECT TABLE_NAME, CONSTRAINT_NAME, DELETE_RULE FROM information_schema.referential_constraints WHERE CONSTRAINT_SCHEMA='well_yu_db'")as$row)$deleteRules[$row['TABLE_NAME']][$row['CONSTRAINT_NAME']]=$row['DELETE_RULE'];
    $issues=[];$fkCount=0;$columnCount=0;
    foreach(['users','properties']as$base)if(!isset($tables[$base]))$issues[]='Required base table missing: '.$base;
    foreach($expected as$name=>$definition){
        preg_match_all('/^  ([a-z][a-z0-9_]*) ([A-Z]+(?:\([^)]*\))?(?: UNSIGNED)?)([^\n]*)/m',$definition['body'],$fields,PREG_SET_ORDER);$columnCount+=count($fields);
        preg_match_all('/CONSTRAINT (\w+) FOREIGN KEY \((\w+)\) REFERENCES (\w+)\((\w+)\)/',$definition['body'],$keys,PREG_SET_ORDER);$fkCount+=count($keys);
        if(!isset($tables[$name]))continue;
        if($tables[$name]['ENGINE']!=='InnoDB')$issues[]='Wrong engine: '.$name;
        $expectedIndexes=[];
        foreach($fields as$field){$actual=$columns[$name][$field[1]]??null;if(!$actual){$issues[]='Missing column: '.$name.'.'.$field[1];continue;}$want=wy_inspection_type($field[2]);$have=wy_inspection_type($actual['COLUMN_TYPE']);if($want!==$have&&!($want==='json'&&$have==='longtext'))$issues[]='Column type differs: '.$name.'.'.$field[1];$nullable=str_contains($field[3],'NOT NULL')||str_contains($field[3],'PRIMARY KEY')?'NO':'YES';if($actual['IS_NULLABLE']!==$nullable)$issues[]='Nullability differs: '.$name.'.'.$field[1];if(str_contains($field[3],'PRIMARY KEY'))$expectedIndexes['PRIMARY']=['columns'=>[$field[1]],'unique'=>true];}
        if(preg_match('/^  PRIMARY KEY \(([^)]+)\)/m',$definition['body'],$primary))$expectedIndexes['PRIMARY']=['columns'=>array_map('trim',explode(',',$primary[1])),'unique'=>true];
        preg_match_all('/^  (UNIQUE )?KEY (\w+) \(([^)]+)\)/m',$definition['body'],$namedIndexes,PREG_SET_ORDER);
        foreach($namedIndexes as$index)$expectedIndexes[$index[2]]=['columns'=>array_map('trim',explode(',',$index[3])),'unique'=>trim($index[1])==='UNIQUE'];
        foreach($expectedIndexes as$index=>$wanted)if(($indexes[$name][$index]??null)!==$wanted)$issues[]='Missing/different index: '.$name.'.'.$index;
        foreach($keys as$key){$found=array_filter($fks,static fn(array$fk):bool=>$fk['TABLE_NAME']===$name&&$fk['CONSTRAINT_NAME']===$key[1]&&$fk['COLUMN_NAME']===$key[2]&&$fk['REFERENCED_TABLE_SCHEMA']==='well_yu_db'&&$fk['REFERENCED_TABLE_NAME']===$key[3]&&$fk['REFERENCED_COLUMN_NAME']===$key[4]);if($found===[])$issues[]='Missing/different relationship: '.$name.'.'.$key[1];if(($deleteRules[$name][$key[1]]??null)!=='RESTRICT')$issues[]='Unexpected relationship deletion rule: '.$name.'.'.$key[1];}
    }
    $missing=array_values(array_diff(array_keys($expected),array_keys($tables)));
    if(in_array('--plan',$argv,true)){
        $plan="-- wELYU wellness extension. PREPARED ONLY. Explicit approval before import.\n-- Existing tables, records, passwords and historical assessments are preserved.\nUSE `well_yu_db`;\n\n";
        if($issues!==[])$plan.="-- BLOCKED: review the inspection issues; no DDL generated.\n";
        elseif($missing===[])$plan.="-- All wellness extension tables exist. No schema commands needed.\n";
        else foreach($missing as$name)$plan.=$expected[$name]['ddl']."\n\n";
        file_put_contents($root.'/database/well-yu/wellness-completion.sql',$plan);
    }
    echo json_encode(['database'=>'well_yu_db','expected_extension_tables'=>count($expected),'present_extension_tables'=>count($expected)-count($missing),'expected_columns'=>$columnCount,'expected_foreign_keys'=>$fkCount,'missing_tables'=>$missing,'issues'=>$issues,'plan_file'=>in_array('--plan',$argv,true)?'database/well-yu/wellness-completion.sql':null,'database_write_statements_executed'=>0],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    exit($missing===[]&&$issues===[]?0:2);
}catch(Throwable$error){fwrite(STDERR,"Inspection failed; no database writes executed. ".($error instanceof PDOException?'Check private credentials and database availability.':$error->getMessage())."\n");exit(1);}
