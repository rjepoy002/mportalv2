<?php
// In-memory contract model only. No infrastructure, DB, session driver or production adapter.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/app/helpers/auth-return-intent.php';
final class ProposedAtomicClaimMock
{
    public bool $available=true;public bool $loseAck=false;private array $claims=[];private int $lastClock;
    public function __construct(public int $clock,private int $capacity=2,private int $maxSkew=0){$this->lastClock=$clock;}
    public function claim(string $digest,int $expires,int $clientNow): bool
    {
        if(!$this->available)throw new RuntimeException('Unavailable');
        if(!preg_match('/^[a-f0-9]{64}$/D',$digest)||$this->clock<$this->lastClock||abs($this->clock-$clientNow)>$this->maxSkew||$expires>$this->clock+600+$this->maxSkew)throw new RuntimeException('Invalid clock or bounds');
        $this->lastClock=$this->clock;if($expires<=$this->clock)return false;
        foreach($this->claims as $key=>$until)if($until<=$this->clock)unset($this->claims[$key]);
        if(isset($this->claims[$digest]))return false;
        if(count($this->claims)>=$this->capacity)throw new RuntimeException('Capacity');
        $this->claims[$digest]=$expires;if($this->loseAck){$this->loseAck=false;throw new RuntimeException('Unknown result');}return true;
    }
    public function safeState(): array{return $this->claims;}
}
$checks=[];function claimCheck(bool $ok,string $label): void{global $checks;if(!$ok)throw new LogicException($label);$checks[]=$label;}
function claimFailure(callable $fn,string $label): void{try{$fn();}catch(RuntimeException $e){claimCheck(true,$label);return;}throw new LogicException($label);}
$gates=['environment_permitted'=>true,'feature_enabled'=>true];$store=new ProposedAtomicClaimMock(1000);$helper=new PortalAuthReturnIntent('https://synthetic.example.invalid',$gates,[$store,'claim']);$s=[];$f=$helper->begin($s,PortalAuthReturnIntent::DESTINATION,1000);$fork=$s;
claimCheck($helper->take($s,$f,1000)['status']==='module','First atomic claim permits module navigation');claimCheck($helper->take($fork,$f,1000)['status']==='expired','Forked pre-regeneration session cannot replay claim');
$other=new PortalAuthReturnIntent('https://synthetic.example.invalid',$gates,[$store,'claim']);$s=[];$f=$other->begin($s,PortalAuthReturnIntent::DESTINATION,1000);claimCheck($other->take($s,$f,1000)['status']==='module','Independent worker shares authoritative claim state');
$s=[];$f=$helper->begin($s,PortalAuthReturnIntent::DESTINATION,1000);claimFailure(fn()=>$helper->take($s,$f,1000),'Capacity exhaustion fails closed');
$store->clock=1600;claimCheck($helper->take($s,$f,1600)['status']==='expired','Exact helper deadline rejects before extending storage');$s=[];$f=$helper->begin($s,PortalAuthReturnIntent::DESTINATION,1600);claimCheck($helper->take($s,$f,1600)['status']==='module','Expired claims cleaned without refreshing old claims');
$store->available=false;$s=[];$f=$helper->begin($s,PortalAuthReturnIntent::DESTINATION,1600);claimFailure(fn()=>$helper->take($s,$f,1600),'Storage outage cannot become navigation success');$store->available=true;
$lost=new ProposedAtomicClaimMock(2000);$lost->loseAck=true;$h=new PortalAuthReturnIntent('https://synthetic.example.invalid',$gates,[$lost,'claim']);$s=[];$f=$h->begin($s,PortalAuthReturnIntent::DESTINATION,2000);claimFailure(fn()=>$h->take($s,$f,2000),'Unknown commit outcome propagates fail-closed');claimCheck($h->take($s,$f,2000)['status']==='expired','Lost acknowledgment retry cannot reclaim committed digest');
$fresh=[];$f=$h->begin($fresh,PortalAuthReturnIntent::DESTINATION,2000);claimCheck($h->take($fresh,$f,2000)['status']==='module','Explicit fresh flow can recover after unknown outcome');
claimFailure(fn()=>$lost->claim(hash('sha256','skew'),2500,1999),'Unapproved clock skew denied');$lost->clock=1999;claimFailure(fn()=>$lost->claim(hash('sha256','rollback'),2500,1999),'Clock regression denied');$lost->clock=2000;
claimCheck($lost->claim(hash('sha256','expired'),2000,2000)===false,'Backend independently rejects expired claim');claimFailure(fn()=>$lost->claim(hash('sha256','oversize-ttl'),9999,2000),'Backend rejects unbounded retention request');claimFailure(fn()=>$lost->claim('raw-flow',2500,2000),'Backend accepts only digests');
$skew=new ProposedAtomicClaimMock(3000,2,2);claimCheck($skew->claim(hash('sha256','approved-skew'),3500,2998),'Explicit mock skew bound accepted');claimFailure(fn()=>$skew->claim(hash('sha256','excess-skew'),3500,2997),'Beyond configured skew denied');
foreach($lost->safeState() as $key=>$expiry)claimCheck((bool)preg_match('/^[a-f0-9]{64}$/D',$key)&&is_int($expiry),'Claim metadata contains only digest and expiry');
$ordinary=new PortalAuthReturnIntent('https://synthetic.example.invalid',$gates);$empty=[];claimCheck($ordinary->take($empty,null,1000)['status']==='ordinary','Ordinary login does not require module claim storage');
file_put_contents(__DIR__.'/auth-claim-contract-results.json',json_encode(['date'=>'2026-10-21','checks'=>count($checks),'results'=>$checks,'implementation'=>'In-memory contract mock; NOT deployed shared storage','real_infrastructure_provisioned'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'Atomic claim contract passed ('.count($checks)." assertions; isolated in-memory model only).\n";
