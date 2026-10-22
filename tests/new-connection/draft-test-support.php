<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

/** No portal config is loaded. Bootstrap connects with NO default database. */
function draftTestPDO(?string $schema=null): PDO
{
    if($schema!==null&&!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new LogicException('Disposable schema required.');
    $host=getenv('MCO_NC_TEST_HOST')?:'127.0.0.1';
    if(!in_array($host,['127.0.0.1','localhost','::1'],true))throw new LogicException('Local disposable database only.');
    $port=getenv('MCO_NC_TEST_PORT')?:'3306';if(!ctype_digit($port))throw new LogicException('Invalid fixture port.');
    $dsn='mysql:host='.$host.';port='.$port.';charset=utf8mb4'.($schema===null?'':';dbname='.$schema);
    $pdo=new PDO($dsn,getenv('MCO_NC_TEST_USER')?:'root',getenv('MCO_NC_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}

/** Blocks live schema names, switching schemas and cross-schema writes. */
final class DraftTestPDO extends PDO
{
    public array $statements=[];
    public function __construct(private PDO $inner,private string $schema) {}
    private function guard(string $sql): void
    {
        if(preg_match('/\b(?:mco_portal|palecxzp_pal_db|nc_applications|nc_users|nc_list|master|member|billhistory|arledger)\b/i',$sql)||preg_match('/\b(?:CREATE|DROP)\s+DATABASE\b|\bUSE\s/i',$sql))throw new LogicException('Nonfixture SQL refused.');
        if($this->inner->query('SELECT DATABASE()')->fetchColumn()!==$this->schema)throw new LogicException('Fixture schema changed.');
        // Qualified table identifiers must refer to this exact fixture or read-only metadata.
        if(preg_match_all('/`([^`]+)`\s*\.\s*`?([a-z_][a-z0-9_]*)/i',$sql,$matches))foreach($matches[1] as $name)if($name!==$this->schema)throw new LogicException('Cross-schema query refused.');
        $this->statements[]=$sql;
    }
    public function exec(string $statement): int|false {$this->guard($statement);return $this->inner->exec($statement);}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false {$this->guard($query);return $fetchMode===null?$this->inner->query($query):$this->inner->query($query,$fetchMode,...$fetchModeArgs);}
    public function prepare(string $query,array $options=[]): PDOStatement|false {$this->guard($query);return $this->inner->prepare($query,$options);}
    public function beginTransaction(): bool {return $this->inner->beginTransaction();}
    public function commit(): bool {return $this->inner->commit();}
    public function rollBack(): bool {return $this->inner->rollBack();}
    public function inTransaction(): bool {return $this->inner->inTransaction();}
    public function lastInsertId(?string $name=null): string|false {return $this->inner->lastInsertId($name);}
    public function getAttribute(int $attribute): mixed {return $this->inner->getAttribute($attribute);}
}

function draftTestConnection(string $schema): DraftTestPDO { return new DraftTestPDO(draftTestPDO($schema),$schema); }

function draftTestOtherSchemaSignature(PDO $server): string
{
    // Metadata only: no customer rows or actual portal connection.
    $result=[];
    foreach(['TABLES'=>'TABLE_SCHEMA,TABLE_NAME,TABLE_TYPE,ENGINE,TABLE_COLLATION','COLUMNS'=>'TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA','STATISTICS'=>'TABLE_SCHEMA,TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME','TRIGGERS'=>'TRIGGER_SCHEMA,TRIGGER_NAME,ACTION_STATEMENT'] as $source=>$columns){
        $schemaColumn=$source==='TRIGGERS'?'TRIGGER_SCHEMA':'TABLE_SCHEMA';
        $result[]=$server->query('SELECT '.$columns.' FROM information_schema.'.$source.' WHERE '.$schemaColumn." NOT LIKE 'mco_nc_test_%' AND ".$schemaColumn." NOT IN ('information_schema','performance_schema','mysql','sys') ORDER BY ".$columns)->fetchAll();
    }
    return hash('sha256',json_encode($result,JSON_THROW_ON_ERROR));
}
