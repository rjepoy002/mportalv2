<?php
/** Pure v2 proof protocol. Key bytes are injected from private server memory, never persisted. */
final class NewConnectionReviewKeys
{
    public const TTL=300;
    public function __construct(private string $current,private array $keys,private array $revoked=[],private int $skew=0,private array $previousLimits=[])
    {
        if(count($keys)<1||count($keys)>3||$skew<0||$skew>30||!isset($keys[$current])||in_array($current,$revoked,true))throw new InvalidArgumentException('Invalid review keyring.');
        foreach($keys as $id=>$key)if(!preg_match('/^[A-Za-z0-9_-]{1,32}$/D',(string)$id)||!is_string($key)||strlen($key)<32||strlen($key)>128)throw new InvalidArgumentException('Invalid review key.');
        foreach($keys as $id=>$key)if($id!==$current&&!in_array($id,$revoked,true)){
            $limit=$previousLimits[$id]??null;
            if(!is_array($limit)||!is_int($limit['issued_through']??null)||!is_int($limit['verify_until']??null)||$limit['issued_through']<0||$limit['verify_until']<=$limit['issued_through']||$limit['verify_until']-$limit['issued_through']>self::TTL)throw new InvalidArgumentException('Previous keys need fixed issuance and verification cutoffs.');
        }
    }
    private function encode(string $v): string {return rtrim(strtr(base64_encode($v),'+/','-_'),'=');}
    public function issue(array $binding,int $now): string
    {
        $payload=$this->encode(NewConnectionApplicantContract::canonical(['protocol'=>2,'kid'=>$this->current,'binding'=>$binding,'issued'=>$now,'expires'=>$now+self::TTL,'nonce'=>bin2hex(random_bytes(16))]));
        return $payload.'.'.$this->encode(hash_hmac('sha256',$payload,$this->keys[$this->current],true));
    }
    public function verify(string $proof,array $binding,int $now): string
    {
        if(strlen($proof)>8192||!preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]{43})$/D',$proof,$m))throw new NewConnectionDenied('Review proof invalid.');
        try{$data=json_decode(base64_decode(strtr($m[1],'-_','+/'),true)?:'',true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new NewConnectionDenied('Review proof invalid.');}
        $kid=$data['kid']??null;
        if(!is_string($kid)||!isset($this->keys[$kid])||in_array($kid,$this->revoked,true)||($data['protocol']??null)!==2||!hash_equals($this->encode(hash_hmac('sha256',$m[1],$this->keys[$kid],true)),$m[2]))throw new NewConnectionDenied('Review key unknown, revoked or invalid.');
        if(!is_int($data['issued']??null)||!is_int($data['expires']??null)||!is_array($data['binding']??null)||$data['expires']-$data['issued']!==self::TTL||$data['issued']>$now+$this->skew||$now>=$data['expires']||!hash_equals(NewConnectionApplicantContract::canonical($binding),NewConnectionApplicantContract::canonical($data['binding'])))throw new NewConnectionConflict('Review expired or changed. Review again.');
        if($kid!==$this->current&&($data['issued']>$this->previousLimits[$kid]['issued_through']||$now>=$this->previousLimits[$kid]['verify_until']))throw new NewConnectionDenied('Previous key verification window closed.');
        return $kid;
    }
}
