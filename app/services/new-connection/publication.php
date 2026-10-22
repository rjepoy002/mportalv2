<?php
require_once __DIR__.'/drafts.php';

/** Internal synthetic governance registry and transaction-bound provider. No connection factory. */
final class NewConnectionPublication
{
    public const KINDS=['localities','eligibility','premises','occupancy','rules','contract'];
    public function __construct(private PDO $pdo,private string $schema)
    {
        if(!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new NewConnectionDenied('Disposable governance schema required.');
        // Reuse strict/native PDO requirements without copying production configuration.
        new NewConnectionDrafts($pdo,$schema,[]);
    }
    public function matches(PDO $pdo,string $schema): bool {return $pdo===$this->pdo&&$schema===$this->schema;}
    private function table(string $name): string {return '`'.$this->schema.'`.`new_connection_'.$name.'`';}
    private function query(string $sql,array $p=[]): PDOStatement {$q=$this->pdo->prepare($sql);$q->execute($p);return $q;}
    private function transaction(callable $work): mixed
    {
        if($this->pdo->inTransaction())throw new LogicException('Governance command owns its transaction.');$this->pdo->beginTransaction();
        try{$r=$work();$this->pdo->commit();return $r;}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function actor(int $actor,bool $publisher=true): void
    {
        $u=$this->query('SELECT status,email_verified_at FROM `'.$this->schema.'`.users WHERE id=? FOR UPDATE',[$actor])->fetch(PDO::FETCH_ASSOC);
        if(!$u||$u['status']!=='active'||empty($u['email_verified_at']))throw new NewConnectionDenied('Governance identity denied.');
        if(!$publisher)return;
        $q=$this->query('SELECT a.id FROM '.$this->table('staff_assignments').' a JOIN '.$this->table('role_permissions').' rp ON rp.role_id=a.role_id JOIN '.$this->table('permissions')." p ON p.id=rp.permission_id WHERE a.user_id=? AND a.office_scope='PUBLICATION' AND p.code='publication.manage' AND a.granted_at<=UTC_TIMESTAMP(6) AND a.revoked_at IS NULL AND (a.expires_at IS NULL OR a.expires_at>UTC_TIMESTAMP(6)) ORDER BY a.id FOR UPDATE",[$actor]);
        if(!$q->fetchColumn())throw new NewConnectionDenied('Explicit publication assignment required.');
    }
    private function text(string $version,string $evidence): void
    {
        if(!preg_match('/^TEST-[A-Za-z0-9._-]{1,90}$/D',$version)||!preg_match('/^TEST-EVIDENCE-[A-Za-z0-9._-]{1,130}$/D',$evidence))throw new InvalidArgumentException('Synthetic version and evidence reference required.');
    }
    private function head(): array
    {
        if(!$this->pdo->inTransaction())throw new LogicException('Publication lock requires caller transaction.');
        $h=$this->query("SELECT * FROM ".$this->table('publication_heads')." WHERE scope='STAGE1' FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
        if(!$h)throw new NewConnectionDenied('Publication head missing.');return $h;
    }
    private function audit(int $actor,string $action,int $entity): void
    {
        $this->query('INSERT INTO '.$this->table('audit_events')." (actor_user_id,action_code,entity_type,entity_id,safe_metadata_json,occurred_at) VALUES(?,?,'publication',?,'{}',UTC_TIMESTAMP(6))",[$actor,$action,$entity]);
    }
    public function registerNotice(int $actor,string $code,string $version,string $content,string $language,string $evidence): int
    {
        $this->text($version,$evidence);
        if(!in_array($code,NewConnectionApplicantContract::ACKS,true)||!in_array($language,['en','fil'],true)||$content===''||strlen($content)>32768||!mb_check_encoding($content,'UTF-8')||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$content))throw new InvalidArgumentException('Invalid synthetic notice.');
        return $this->transaction(function()use($actor,$code,$version,$content,$language,$evidence){
            $this->actor($actor);$this->head();
            $this->query('INSERT INTO '.$this->table('notice_versions').' (notice_code,version,content_utf8,content_sha256,language,approval_evidence,approved_by,publisher_id,approved_at,effective_at,created_at) VALUES(?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))',[$code,$version,$content,hash('sha256',$content),$language,$evidence,$actor,$actor]);
            $id=(int)$this->pdo->lastInsertId();$this->audit($actor,'publication.notice.registered',$id);return $id;
        });
    }
    private function document(string $kind,array $doc): void
    {
        $required=match($kind){'localities'=>['provinces','cities','barangays'],'eligibility'=>['barangays'],'premises','occupancy'=>['codes'],'rules'=>['connections','connection_categories_by_premises','occupancy_other_codes'],'contract'=>['contract_version','snapshot_schema','workflow_version','synthetic_only']};
        $keys=array_keys($doc);sort($keys);sort($required);if($keys!==$required)throw new InvalidArgumentException('Closed catalog document required.');
        if($kind==='contract'){
            if(!is_string($doc['contract_version'])||strlen($doc['contract_version'])>100||!is_int($doc['snapshot_schema'])||!is_int($doc['workflow_version'])||$doc['synthetic_only']!==true)throw new InvalidArgumentException('Invalid compatibility record.');return;
        }
        foreach($doc as $map)if(!is_array($map))throw new InvalidArgumentException('Catalog maps required.');
        if($kind==='localities'){
            foreach(['provinces','cities','barangays'] as $level)foreach($doc[$level] as $code=>$row){
                if(!is_string($code)||!preg_match('/^[A-Za-z0-9_-]{1,32}$/D',$code)||!is_array($row)||!is_string($row['name']??null)||mb_strlen($row['name'])>100||$row['name']==='')throw new InvalidArgumentException('Invalid locality.');
                $fields=array_keys($row);sort($fields);$expected=$level==='provinces'?['name']:($level==='cities'?['name','province']:['city','name']);if($fields!==$expected)throw new InvalidArgumentException('Invalid locality fields.');
                if($level==='cities'&&$row['province']!==null&&(!is_string($row['province'])||!isset($doc['provinces'][$row['province']])))throw new InvalidArgumentException('Invalid province relationship.');
                if($level==='barangays'&&(!is_string($row['city'])||!isset($doc['cities'][$row['city']])))throw new InvalidArgumentException('Invalid city relationship.');
            }
        }elseif($kind==='eligibility'){foreach($doc['barangays'] as $code=>$flag)if(!is_string($code)||!is_bool($flag))throw new InvalidArgumentException('Invalid eligibility rule.');}
        elseif(in_array($kind,['premises','occupancy'],true)){foreach($doc['codes'] as $code=>$label)if(!is_string($code)||!preg_match('/^[A-Za-z0-9_-]{1,32}$/D',$code)||!is_string($label)||$label===''||mb_strlen($label)>100)throw new InvalidArgumentException('Invalid classification.');}
        else{
            foreach($doc['connections'] as $code=>$label)if(!is_string($code)||!preg_match('/^[A-Za-z0-9_-]{1,32}$/D',$code)||!is_string($label)||$label===''||mb_strlen($label)>100)throw new InvalidArgumentException('Invalid connection classification.');
            if(!array_is_list($doc['occupancy_other_codes']))throw new InvalidArgumentException('Occupancy rule list required.');
            foreach($doc['connection_categories_by_premises'] as $premise=>$codes)if(!is_string($premise)||!is_array($codes)||!array_is_list($codes)||count($codes)===0||count(array_filter($codes,'is_string'))!==count($codes))throw new InvalidArgumentException('Invalid conditional rules.');
            foreach($doc['occupancy_other_codes'] as $code)if(!is_string($code))throw new InvalidArgumentException('Invalid occupancy rule.');
        }
    }
    public function registerCatalog(int $actor,string $kind,string $version,array $document,string $evidence): int
    {
        $this->text($version,$evidence);if(!in_array($kind,self::KINDS,true))throw new InvalidArgumentException('Unknown catalog kind.');
        $this->document($kind,$document);$json=NewConnectionApplicantContract::canonical($document);if(strlen($json)>65536)throw new InvalidArgumentException('Catalog too large.');
        return $this->transaction(function()use($actor,$kind,$version,$json,$evidence){
            $this->actor($actor);$this->head();$this->query('INSERT INTO '.$this->table('catalog_versions').' (kind,version,content_json,content_sha256,approval_evidence,approved_by,publisher_id,approved_at,effective_at,created_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))',[$kind,$version,$json,hash('sha256',$json),$evidence,$actor,$actor]);
            $id=(int)$this->pdo->lastInsertId();$this->audit($actor,'publication.catalog.registered',$id);return $id;
        });
    }
    private function catalogRows(array $ids,bool $active): array
    {
        $keys=array_keys($ids);$required=self::KINDS;sort($keys);sort($required);if($keys!==$required)throw new InvalidArgumentException('Six independent catalog versions required.');
        $rows=[];foreach($ids as $kind=>$id){if(!is_int($id)||$id<1)throw new InvalidArgumentException('Invalid catalog identifier.');
            $row=$this->query('SELECT c.*,r.catalog_id AS retired,(c.effective_at<=UTC_TIMESTAMP(6) AND c.approved_at<=UTC_TIMESTAMP(6)) AS effective FROM '.$this->table('catalog_versions').' c LEFT JOIN '.$this->table('catalog_retirements').' r ON r.catalog_id=c.id WHERE c.id=? AND c.kind=?',[$id,$kind])->fetch(PDO::FETCH_ASSOC);
            if(!$row||($active&&($row['retired']!==null||!(bool)$row['effective']))||!hash_equals($row['content_sha256'],hash('sha256',$row['content_json'])))throw new NewConnectionDenied('Catalog unavailable.');$row['document']=json_decode($row['content_json'],true,32,JSON_THROW_ON_ERROR);$this->document($kind,$row['document']);$rows[$kind]=$row;
        }return $rows;
    }
    private function compose(array $rows): array
    {
        $docs=array_map(static fn($r)=>$r['document'],$rows);$compat=$docs['contract'];
        if($compat['contract_version']!==NewConnectionApplicantContract::VERSION||$compat['snapshot_schema']!==1||$compat['workflow_version']!==1)throw new NewConnectionDenied('Unsupported applicant contract.');
        foreach($docs['eligibility']['barangays'] as $code=>$flag)if(!isset($docs['localities']['barangays'][$code]))throw new InvalidArgumentException('Unknown eligibility locality.');
        foreach($docs['localities']['barangays'] as $code=>$row)if(!array_key_exists($code,$docs['eligibility']['barangays']))throw new InvalidArgumentException('Explicit eligibility required.');
        foreach($docs['rules']['connection_categories_by_premises'] as $premise=>$codes){if(!isset($docs['premises']['codes'][$premise]))throw new InvalidArgumentException('Unknown premise rule.');foreach($codes as $code)if(!isset($docs['rules']['connections'][$code]))throw new InvalidArgumentException('Unknown conditional category.');}
        foreach($docs['rules']['occupancy_other_codes'] as $code)if(!isset($docs['occupancy']['codes'][$code]))throw new InvalidArgumentException('Unknown occupancy rule.');
        $versions=[];$hashes=[];foreach($rows as $kind=>$row){$versions[$kind]=$row['version'];$hashes[$kind]=$row['content_sha256'];}
        return $docs['localities']+['version'=>$versions['localities'],'versions'=>$versions,'component_hashes'=>$hashes,
            'premises'=>$docs['premises']['codes'],'occupancies'=>$docs['occupancy']['codes'],'connections'=>$docs['rules']['connections'],
            'eligibility'=>$docs['eligibility']['barangays'],'rules'=>$docs['rules']];
    }
    public function createBundle(int $actor,string $version,array $catalogIds,array $noticeVersions,string $evidence): int
    {
        $this->text($version,$evidence);
        return $this->transaction(function()use($actor,$version,$catalogIds,$noticeVersions,$evidence){
            $this->actor($actor);$this->head();$catalogs=$this->catalogRows($catalogIds,true);$this->compose($catalogs);
            $codes=array_keys($noticeVersions);$required=NewConnectionApplicantContract::ACKS;sort($codes);sort($required);if($codes!==$required)throw new InvalidArgumentException('Exactly four notices required.');
            $manifest=['contract_version'=>NewConnectionApplicantContract::VERSION,'catalogs'=>[],'notices'=>[],'approval_evidence'=>$evidence];
            foreach($catalogs as $kind=>$row)$manifest['catalogs'][$kind]=['id'=>(int)$row['id'],'version'=>$row['version'],'sha256'=>$row['content_sha256'],'approval_evidence'=>$row['approval_evidence']];
            foreach($noticeVersions as $code=>$v){if(!is_string($v))throw new InvalidArgumentException('Notice version required.');$row=$this->query('SELECT * FROM '.$this->table('notice_versions').' WHERE notice_code=? AND version=?',[$code,$v])->fetch(PDO::FETCH_ASSOC);if(!$row)throw new NewConnectionDenied('Notice unavailable.');$manifest['notices'][$code]=['version'=>$v,'sha256'=>$row['content_sha256'],'approval_evidence'=>$row['approval_evidence']];}
            $json=NewConnectionApplicantContract::canonical($manifest);$this->query('INSERT INTO '.$this->table('publication_bundles').' (version,contract_version,manifest_json,manifest_sha256,approval_evidence,publisher_id,created_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP(6))',[$version,NewConnectionApplicantContract::VERSION,$json,hash('sha256',$json),$evidence,$actor]);$id=(int)$this->pdo->lastInsertId();
            foreach($catalogs as $kind=>$row)$this->query('INSERT INTO '.$this->table('bundle_catalogs').' VALUES(?,?,?,?)',[$id,$kind,$row['id'],$row['content_sha256']]);
            foreach($manifest['notices'] as $code=>$n)$this->query('INSERT INTO '.$this->table('bundle_notices').' VALUES(?,?,?,?)',[$id,$code,$n['version'],$n['sha256']]);
            $this->audit($actor,'publication.bundle.registered',$id);return $id;
        });
    }
    private function bundle(int $id,bool $active): array
    {
        $row=$this->query('SELECT * FROM '.$this->table('publication_bundles').' WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!$row||!hash_equals($row['manifest_sha256'],hash('sha256',$row['manifest_json'])))throw new NewConnectionDenied('Bundle unavailable.');$m=json_decode($row['manifest_json'],true,32,JSON_THROW_ON_ERROR);
        $links=$this->query('SELECT kind,catalog_id,content_sha256 FROM '.$this->table('bundle_catalogs').' WHERE bundle_id=?',[$id])->fetchAll(PDO::FETCH_ASSOC);$ids=[];
        foreach($links as $link){$kind=$link['kind'];if((int)($m['catalogs'][$kind]['id']??0)!==(int)$link['catalog_id']||($m['catalogs'][$kind]['sha256']??null)!==$link['content_sha256'])throw new NewConnectionDenied('Bundle catalog integrity failed.');$ids[$kind]=(int)$link['catalog_id'];}
        $catalogs=$this->catalogRows($ids,$active);foreach($catalogs as $kind=>$c)if($m['catalogs'][$kind]['version']!==$c['version'])throw new NewConnectionDenied('Bundle version integrity failed.');
        $links=$this->query('SELECT n.*,(n.effective_at<=UTC_TIMESTAMP(6) AND n.approved_at<=UTC_TIMESTAMP(6)) AS effective FROM '.$this->table('bundle_notices').' b JOIN '.$this->table('notice_versions').' n ON n.notice_code=b.notice_code AND n.version=b.version AND n.content_sha256=b.content_sha256 WHERE b.bundle_id=?',[$id])->fetchAll(PDO::FETCH_ASSOC);$notices=[];
        foreach($links as $n){if(($active&&!(bool)$n['effective'])||!hash_equals($n['content_sha256'],hash('sha256',$n['content_utf8']))||($m['notices'][$n['notice_code']]['version']??null)!==$n['version']||($m['notices'][$n['notice_code']]['sha256']??null)!==$n['content_sha256'])throw new NewConnectionDenied('Notice integrity failed.');$notices[$n['notice_code']]=['version'=>$n['version'],'published'=>true,'test_only'=>true,'content'=>$n['content_utf8']];}
        if(count($notices)!==4||count($m['catalogs'])!==6||$row['contract_version']!==NewConnectionApplicantContract::VERSION)throw new NewConnectionDenied('Incomplete bundle.');
        return ['environment'=>'synthetic_test','catalog_approved_for_test'=>true,'catalog'=>$this->compose($catalogs),'notices'=>$notices,'bundle_id'=>$id,'bundle_sha256'=>$row['manifest_sha256']];
    }
    /** No side connection: locks survive until the caller's draft/submission transaction ends. */
    public function __invoke(): array
    {
        if($this->pdo->inTransaction()){$h=$this->head();if($h['state']!=='published'||$h['bundle_id']===null)throw new NewConnectionDenied('Publication withdrawn.');return $this->bundle((int)$h['bundle_id'],true)+['epoch'=>(int)$h['epoch']];}
        // Constructor bootstrap only; withdrawal must not prevent reading prior receipts.
        $id=$this->query("SELECT bundle_id FROM ".$this->table('publication_heads')." WHERE scope='STAGE1'")->fetchColumn();if(!$id)throw new NewConnectionDenied('No bundle registered.');return $this->bundle((int)$id,false);
    }
    private function move(int $actor,array $h,string $action,string $state,?int $bundle,?int $catalog): int
    {
        $this->query('UPDATE '.$this->table('publication_heads')." SET epoch=epoch+1,bundle_id=?,state=?,last_action=?,catalog_id=?,updated_by=?,updated_at=UTC_TIMESTAMP(6) WHERE scope='STAGE1' AND epoch=?",[$bundle,$state,$action,$catalog,$actor,$h['epoch']]);
        $this->audit($actor,'publication.'.$action,$bundle??$catalog??0);return (int)$h['epoch']+1;
    }
    public function publish(int $actor,int $bundle,int $expectedEpoch): int
    {
        return $this->transaction(function()use($actor,$bundle,$expectedEpoch){$this->actor($actor);$h=$this->head();if((int)$h['epoch']!==$expectedEpoch)throw new NewConnectionConflict('Publication changed.');$this->bundle($bundle,true);return $this->move($actor,$h,'publish','published',$bundle,null);});
    }
    public function withdraw(int $actor,int $expectedEpoch): int
    {
        return $this->transaction(function()use($actor,$expectedEpoch){$this->actor($actor);$h=$this->head();if((int)$h['epoch']!==$expectedEpoch)throw new NewConnectionConflict('Publication changed.');return $this->move($actor,$h,'withdraw','withdrawn',$h['bundle_id']===null?null:(int)$h['bundle_id'],null);});
    }
    public function retireCatalog(int $actor,int $catalog,int $expectedEpoch,string $evidence): int
    {
        $this->text('TEST-retirement',$evidence);
        return $this->transaction(function()use($actor,$catalog,$expectedEpoch,$evidence){
            $this->actor($actor);$h=$this->head();if((int)$h['epoch']!==$expectedEpoch)throw new NewConnectionConflict('Publication changed.');
            if($h['state']==='published'&&$this->query('SELECT COUNT(*) FROM '.$this->table('bundle_catalogs').' WHERE bundle_id=? AND catalog_id=?',[$h['bundle_id'],$catalog])->fetchColumn())throw new NewConnectionDenied('Withdraw active bundle before retiring its catalog.');
            $this->query('INSERT INTO '.$this->table('catalog_retirements').' VALUES(?,?,?,UTC_TIMESTAMP(6))',[$catalog,$actor,$evidence]);return $this->move($actor,$h,'retire_catalog',$h['state'],$h['bundle_id']===null?null:(int)$h['bundle_id'],$catalog);
        });
    }
    public function historicalNotice(int $actor,string $code,string $version,string $sha256): array
    {
        return $this->transaction(function()use($actor,$code,$version,$sha256){$this->actor($actor,false);$row=$this->query('SELECT notice_code,version,content_utf8,content_sha256,language,approval_evidence,approved_at,effective_at,publisher_id FROM '.$this->table('notice_versions').' WHERE notice_code=? AND version=? AND content_sha256=?',[$code,$version,$sha256])->fetch(PDO::FETCH_ASSOC);if(!$row||!hash_equals(hash('sha256',$row['content_utf8']),$sha256))throw new NewConnectionDenied('Historical notice unavailable.');return $row;});
    }
    public function historicalCatalog(int $actor,int $id): array
    {
        return $this->transaction(function()use($actor,$id){$this->actor($actor,false);$row=$this->query('SELECT * FROM '.$this->table('catalog_versions').' WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);if(!$row||!hash_equals($row['content_sha256'],hash('sha256',$row['content_json'])))throw new NewConnectionDenied('Historical catalog unavailable.');return $row;});
    }
}
