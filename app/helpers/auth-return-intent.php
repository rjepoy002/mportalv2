<?php
declare(strict_types=1);

/** Closed navigation hint only. Not authentication, eligibility, CSRF or a workflow capability.
 * Unwired foundation: callers must supply trusted server configuration/session state.
 */
final class PortalAuthReturnIntent
{
    public const DESTINATION='new_connection.home';
    public const SESSION_KEY='portal_auth_return';
    public const TTL=600;
    private string $base;
    private bool $enabled;
    private ?Closure $claim;

    public function __construct(string $canonicalBase,array $gates,?callable $atomicClaim=null)
    {
        $url=parse_url($canonicalBase);
        if(!$url||!filter_var($canonicalBase,FILTER_VALIDATE_URL)||empty($url['host'])||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])||preg_match('/[\x00-\x20\x7f\\\\%]/',$canonicalBase)||!preg_match('~^(?:/[A-Za-z0-9_-]+)*/?$~D',$url['path']??''))throw new InvalidArgumentException('Invalid canonical authentication URL.');
        $local=in_array(strtolower($url['host']),['localhost','127.0.0.1','[::1]'],true);
        $httpFixture=($gates['environment']??null)==='isolated-auth-test'&&$local;
        if(($url['scheme']??'')!=='https'&&!(($url['scheme']??'')==='http'&&$httpFixture))throw new InvalidArgumentException('Trusted HTTPS URL required.');
        $this->base=rtrim($canonicalBase,'/');
        $this->enabled=($gates['environment_permitted']??false)===true&&($gates['feature_enabled']??false)===true;
        $this->claim=$atomicClaim===null?null:Closure::fromCallable($atomicClaim);
    }

    public function begin(array &$session,mixed $destination=self::DESTINATION,?int $now=null): ?string
    {
        if($destination!==self::DESTINATION)throw new InvalidArgumentException('Unknown authentication destination.');
        if(!$this->enabled)return null;
        $now??=time();if($now<0||$now>PHP_INT_MAX-self::TTL)throw new InvalidArgumentException('Invalid clock.');
        $flow=bin2hex(random_bytes(16));
        $session[self::SESSION_KEY]=['purpose'=>'portal.auth.return.v1','destination'=>self::DESTINATION,'flow_id'=>$flow,'issued_at'=>$now,'expires_at'=>$now+self::TTL];
        return $flow;
    }

    public function match(array $session,mixed $flow,?int $now=null): array
    {
        if($flow===null)return ['status'=>'ordinary'];
        $expired=['status'=>'expired'];$record=$session[self::SESSION_KEY]??null;$now??=time();
        if(!$this->enabled||!is_string($flow)||!preg_match('/^[a-f0-9]{32}$/D',$flow)||!is_array($record))return $expired;
        $keys=array_keys($record);sort($keys);
        if($keys!==['destination','expires_at','flow_id','issued_at','purpose']||($record['purpose']??null)!=='portal.auth.return.v1'||($record['destination']??null)!==self::DESTINATION||!is_string($record['flow_id']??null)||!hash_equals($record['flow_id'],$flow)||!is_int($record['issued_at']??null)||!is_int($record['expires_at']??null)||$record['issued_at']<0||$record['issued_at']>PHP_INT_MAX-self::TTL||$record['expires_at']!==$record['issued_at']+self::TTL||$now<$record['issued_at']||$now>=$record['expires_at'])return $expired;
        return ['status'=>'module','destination'=>self::DESTINATION];
    }

    public function take(array &$session,mixed $flow,?int $now=null): array
    {
        $result=$this->match($session,$flow,$now);
        if($result['status']==='module'){
            if($this->claim===null)throw new LogicException('Atomic return-intent claim provider required.');
            // A waiting request can retain the old session inode across session regeneration.
            // Claim outside that session, using only a digest and fixed expiry, before auth replaces it.
            if(($this->claim)(hash('sha256','portal.auth.return.v1:'.$flow),$session[self::SESSION_KEY]['expires_at'],$now??time())!==true)$result=['status'=>'expired'];
        }
        // An unrelated/stale tab must not consume a newer tab's active flow.
        $record=$session[self::SESSION_KEY]??null;$stored=is_array($record)?($record['flow_id']??null):null;
        if(is_string($flow)&&is_string($stored)&&hash_equals($stored,$flow))unset($session[self::SESSION_KEY]);
        return $result;
    }

    public function target(array $outcome): string
    {
        if($outcome===['status'=>'ordinary'])return 'dashboard.php';
        if($outcome===['status'=>'expired'])return $this->base.'/index.php?auth_context=expired';
        if($outcome===['status'=>'module','destination'=>self::DESTINATION])return $this->base.'/new-connection.php';
        throw new InvalidArgumentException('Invalid authentication outcome.');
    }
}
