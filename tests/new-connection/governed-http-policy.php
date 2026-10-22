<?php
// Private fixture library. Publication mutations are called only by the CLI harness.
if(!in_array(PHP_SAPI,['cli','cli-server'],true)){http_response_code(404);exit;}
function governedSeed(PDO $db,string $schema): void
{
    newConnectionGovernanceMigrate($db);
    $db->exec("INSERT INTO new_connection_roles(code,description) VALUES('test-publisher','Synthetic only')");
    $db->exec("INSERT INTO new_connection_permissions(code,description) VALUES('publication.manage','Synthetic only')");
    $db->exec('INSERT INTO new_connection_role_permissions VALUES(1,1)');
    $db->exec("INSERT INTO new_connection_staff_assignments(user_id,role_id,office_scope,granted_by,granted_at) VALUES(4,1,'PUBLICATION',4,UTC_TIMESTAMP(6))");
    $g=new NewConnectionPublication($db,$schema);$ids=[];$notices=[];
    foreach(require __DIR__.'/governance-fixtures.php' as $kind=>$doc)$ids[$kind]=$g->registerCatalog(4,$kind,'TEST-'.$kind.'-1',$doc,'TEST-EVIDENCE-http');
    foreach(NewConnectionApplicantContract::ACKS as $code){$notices[$code]='TEST-'.$code.'-1';$g->registerNotice(4,$code,$notices[$code],'Synthetic HTTP notice '.$code.'. Not official PALECO policy.','en','TEST-EVIDENCE-http');}
    $bundle=$g->createBundle(4,'TEST-http-1',$ids,$notices,'TEST-EVIDENCE-http');$g->publish(4,$bundle,0);
}
function governedChange(PDO $db,string $schema,string $action): void
{
    $g=new NewConnectionPublication($db,$schema);$head=$db->query('SELECT * FROM new_connection_publication_heads')->fetch();$epoch=(int)$head['epoch'];$bundle=(int)$head['bundle_id'];
    if($action==='withdraw'){$g->withdraw(4,$epoch);return;}
    if($action==='republish'){$g->publish(4,$bundle,$epoch);return;}
    $ids=[];$q=$db->prepare('SELECT kind,catalog_id FROM new_connection_bundle_catalogs WHERE bundle_id=?');$q->execute([$bundle]);foreach($q->fetchAll() as $row)$ids[$row['kind']]=(int)$row['catalog_id'];
    $notices=[];$q=$db->prepare('SELECT notice_code,version FROM new_connection_bundle_notices WHERE bundle_id=?');$q->execute([$bundle]);foreach($q->fetchAll() as $row)$notices[$row['notice_code']]=$row['version'];
    if($action==='notice'){$v='TEST-replaced-http-2';$g->registerNotice(4,'privacy_notice',$v,'Replacement synthetic HTTP notice. Not official policy.','en','TEST-EVIDENCE-http');$notices['privacy_notice']=$v;}
    elseif(in_array($action,['localities','premises'],true)){
        $docs=require __DIR__.'/governance-fixtures.php';
        if($action==='localities')$docs[$action]['cities']['TEST-C3']=['name'=>'Additional synthetic city','province'=>null];
        else $docs[$action]['codes']['warehouse']='Additional synthetic warehouse';
        $ids[$action]=$g->registerCatalog(4,$action,'TEST-'.$action.'-http-'.$epoch,$docs[$action],'TEST-EVIDENCE-http');
    }
    elseif(in_array($action,['rename','remove'],true)){
        $doc=json_decode($g->historicalCatalog(4,$ids['premises'])['content_json'],true,32,JSON_THROW_ON_ERROR);
        if($action==='rename')$doc['codes']['home']='Example home — renamed synthetic classification';else unset($doc['codes']['warehouse']);
        $ids['premises']=$g->registerCatalog(4,'premises','TEST-premises-'.$action.'-'.$epoch,$doc,'TEST-EVIDENCE-http');
    }
    elseif($action==='retire'){$docs=require __DIR__.'/governance-fixtures.php';$id=$g->registerCatalog(4,'localities','TEST-unused-http-'.$epoch,$docs['localities'],'TEST-EVIDENCE-http');$g->retireCatalog(4,$id,$epoch,'TEST-EVIDENCE-http');return;}
    else throw new InvalidArgumentException('Unknown harness action.');
    $next=$g->createBundle(4,'TEST-http-'.$action.'-'.$epoch,$ids,$notices,'TEST-EVIDENCE-http');$g->publish(4,$next,$epoch);
}
