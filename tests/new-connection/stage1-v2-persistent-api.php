<?php
// Included only by the generated, already authenticated loopback fixture adapter.
function v2PersistentService(array $cfg):NewConnectionVersionedDrafts {
    require_once __DIR__.'/private/db-support.php';require_once __DIR__.'/app/services/new-connection/versioned-drafts.php';
    $context=require __DIR__.'/stage1-v2-data.php';$old=require __DIR__.'/prototype-data.php';$context['v1_catalog']=$old['catalog'];
    return new NewConnectionVersionedDrafts(draftTestConnection($cfg['schema']),$cfg['schema'],$context,new NewConnectionReviewKeys('fixture',['fixture'=>base64_decode($cfg['proof_key'],true)]));
}
function v2PersistentAction(array $cfg,object $command,int $actor):never {
    $service=v2PersistentService($cfg);$action=$command->action??'';$reference=$command->reference??($_SESSION['v2_reference']??null);
    try{
        if($action==='end-session'){$_SESSION=[];session_destroy();v2reply(200,['message'=>'Synthetic session ended. Database draft retained.']);}
        if($action==='list')v2reply(200,['drafts'=>$service->listOwn($actor)]);
        if($action==='profile-change'){$db=draftTestConnection($cfg['schema']);$db->beginTransaction();$q=$db->prepare('SELECT first_name FROM users WHERE id=? FOR UPDATE');$q->execute([$actor]);$name=$q->fetchColumn()==='Synthetic'?'Changed':'Synthetic';$q=$db->prepare('UPDATE users SET first_name=? WHERE id=?');$q->execute([$name,$actor]);$db->commit();v2reply(200,$service->profile($actor)+['message'=>'Synthetic database profile changed. Refresh the draft before saving.']);}
        if(($command->version??null)!==NewConnectionApplicantContractV2::VERSION)v2reply(409,['message'=>'Explicit executable v2 contract required.']);
        if($action==='create'){$r=$service->createVersioned($actor,$command->version,$command->key??'');$_SESSION['v2_reference']=$r['reference'];v2reply(200,$r+['message'=>'Persistent synthetic draft created. Resume to edit.']);}
        if($action==='validate'){$context=require __DIR__.'/stage1-v2-data.php';$context['profile']=$service->profile($actor)['profile'];$r=newConnectionValidateContract($command->version,json_encode($command->input??null),$context);v2reply($r['valid']?200:422,$r);}
        if(!is_string($reference))v2reply(400,['message'=>'Create or select a draft first.']);
        if($action==='resume'){$r=$service->retrieve($actor,$reference);$_SESSION['v2_reference']=$reference;v2reply(200,$r+['message'=>$r['profile_changed']?'Profile changed. Current identity refreshed; confirm and save.':'Persistent database draft resumed; review authorization not restored.']);}
        if(!is_object($command->expected??null))v2reply(400,['message'=>'Expected application and revision versions required.']);$expected=(array)$command->expected;
        if($action==='save')v2reply(200,$service->saveVersioned($actor,$reference,$command->version,$expected,$command->key??'',json_encode($command->input??null))+['message'=>'Saved to disposable database. No submission.']);
        if($action==='abandon'||$action==='convert'){
            if(!is_bool($command->confirmed??null))v2reply(400,['message'=>'Explicit boolean confirmation required.']);
            $r=$action==='abandon'?$service->abandon($actor,$reference,$expected,$command->key??'',$command->confirmed):$service->convert($actor,$reference,$expected,$command->key??'',$command->confirmed);
            if($action==='convert')$_SESSION['v2_reference']=$reference;v2reply(200,$r+['message'=>$action==='convert'?'Explicit conversion committed; new fields are incomplete.':'Draft abandoned. Historical revisions retained.']);
        }
        if($action==='review')v2reply(200,$service->review($actor,$reference,$expected));
        if($action==='check-proof'){if(!is_bool($command->confirmed??null)||!is_string($command->proof??null))v2reply(400,[]);v2reply(200,$service->checkReview($actor,$reference,$expected,$command->proof,$command->confirmed));}
        v2reply(404,[]);
    }catch(NewConnectionDraftInvalid $e){v2reply(422,['errors'=>$e->errors,'message'=>'Please correct the draft fields.']);}
    catch(NewConnectionDraftLimited $e){v2reply(429,['message'=>'Draft write limit reached.']);}
    catch(NewConnectionConflict $e){v2reply(409,['message'=>'Draft, profile or review changed. Resume/refresh; no automatic replay.']);}
    catch(NewConnectionDenied $e){v2reply(403,['message'=>'Draft unavailable.']);}
    catch(InvalidArgumentException|JsonException $e){v2reply(400,['message'=>'Invalid command or expected versions.']);}
}
