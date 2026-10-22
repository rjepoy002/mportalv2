<?php
/** Preparation adapter: deliberately disposable-only, with no login or connection factory. */
final class NewConnectionTrustedStagingContext
{
    public function __construct(private PDO $db,private array $config,private NewConnectionReviewKeys $keys)
    {
        if(($config['environment']??null)!=='isolated-staging-preparation'||($config['enabled']??false)!==true||($config['synthetic_only']??false)!==true)throw new NewConnectionDenied('Context disabled.');
        $schema=$config['schema']??'';
        if(!is_string($schema)||!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema)||$db->query('SELECT DATABASE()')->fetchColumn()!==$schema)throw new NewConnectionDenied('Unattested schema.');
        $origin=$config['origin']??'';$url=is_string($origin)?parse_url($origin):false;
        if(!$url||($url['scheme']??'')!=='https'||empty($url['host'])||isset($url['user'])||isset($url['pass'])||isset($url['path'])||isset($url['query'])||isset($url['fragment']))throw new NewConnectionDenied('Trusted HTTPS origin required.');
        if(!is_string($config['base_path']??null)||!preg_match('~^(?:/[A-Za-z0-9_-]+)*$~D',$config['base_path']))throw new NewConnectionDenied('Invalid base path.');
        // Existing service enforces native prepares, exceptions, strict SQL and UTC.
        new NewConnectionDrafts($db,$schema,[]);
    }
    public function applicant(array $session): array
    {
        $id=filter_var($session['user_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$id)throw new NewConnectionDenied('Authentication required.');
        $q=$this->db->prepare('SELECT id,email,status,email_verified_at FROM users WHERE id=?');$q->execute([$id]);$user=$q->fetch(PDO::FETCH_ASSOC);
        if(!$user||$user['status']!=='active'||empty($user['email_verified_at'])||!str_ends_with($user['email'],'@example.invalid'))throw new NewConnectionDenied('Eligible synthetic identity required.');
        return $user;
    }
    public function boundary(string $origin,mixed $csrf): void
    {
        if(!hash_equals($this->config['origin'],$origin)||!portalAuthCsrfValid($csrf))throw new NewConnectionDenied('Request denied.');
    }
    public function returnPath(string $path): string
    {
        $safe=$this->config['base_path'].'/new-connection.php';
        return $path===$safe?$path:$safe;
    }
    public function service(): NewConnectionStage1Submission
    {
        return new NewConnectionStage1Submission($this->db,$this->config['schema'],new NewConnectionPublication($this->db,$this->config['schema']),$this->keys);
    }
}
