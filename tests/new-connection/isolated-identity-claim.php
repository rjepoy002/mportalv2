<?php
/** Task 06H laboratory only. Never load from a web controller. No provider verifier. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/draft-test-support.php';

function identityClaimGuard(PDO $db): void
{
    if (!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',(string)$db->query('SELECT DATABASE()')->fetchColumn()))
        throw new LogicException('Disposable schema required');
    $db->exec("SET time_zone='+00:00'");
}

function identityClaimFixtureSchema(PDO $db): void
{
    identityClaimGuard($db);
    $ddl = [
        "CREATE TABLE synthetic_users (id BIGINT UNSIGNED PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE, google_subject VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE, status VARCHAR(16) NOT NULL, email_verified_at DATETIME NULL, password_available BOOLEAN NOT NULL DEFAULT 0, binding_origin VARCHAR(24) NOT NULL DEFAULT 'unknown', version INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE identity_mutex (id INT PRIMARY KEY) ENGINE=InnoDB",
        "INSERT INTO identity_mutex VALUES (1)",
        "CREATE TABLE link_proofs (digest CHAR(64) CHARACTER SET ascii PRIMARY KEY, actor BIGINT UNSIGNED NOT NULL, subject_digest CHAR(64) NOT NULL, email_digest CHAR(64) NOT NULL, session_digest CHAR(64) NOT NULL, expires BIGINT NOT NULL, consumed BOOLEAN NOT NULL DEFAULT 0, FOREIGN KEY(actor) REFERENCES synthetic_users(id)) ENGINE=InnoDB",
        "CREATE TABLE identity_events (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor BIGINT UNSIGNED NOT NULL, action VARCHAR(32) NOT NULL) ENGINE=InnoDB",
        "CREATE TABLE claim_control (id INT PRIMARY KEY, generation CHAR(32) NOT NULL, ready BOOLEAN NOT NULL, used INT NOT NULL, last_clock BIGINT NOT NULL, not_before BIGINT NOT NULL) ENGINE=InnoDB",
        "CREATE TABLE return_claims (digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, expires BIGINT NOT NULL, INDEX(expires)) ENGINE=InnoDB",
    ];
    foreach ($ddl as $sql) $db->exec($sql);
}

/** Test evidence object stands for validated OAuth state/exchange, never browser claims. */
final class SyntheticProviderEvidence
{
    public function __construct(public readonly string $subject, public readonly string $email,
        public readonly bool $emailVerified = true, public readonly bool $stateValidated = true,
        public readonly bool $exchangeValidated = true) {}
    public function valid(): bool
    {
        return $this->stateValidated && $this->exchangeValidated && $this->emailVerified
            && (bool)preg_match('/^[A-Za-z0-9_-]{1,255}$/D', $this->subject)
            && strlen($this->email)<=255 && filter_var($this->email,FILTER_VALIDATE_EMAIL)!==false;
    }
}

final class IsolatedGooglePolicy
{
    public function __construct(private PDO $db, private ?Closure $hook = null) { identityClaimGuard($db); }
    private function run(string $sql, array $args=[]): PDOStatement
    { $q=$this->db->prepare($sql); $q->execute($args); return $q; }
    private function eligible(array $u): bool
    { return $u['status']==='active' && $u['email_verified_at']!==null; }

    /** Fixture issuer called only after synthetic independent portal reauthentication + CSRF. */
    public function issueLinkProof(int $actor, SyntheticProviderEvidence $provider, string $session,
        bool $freshPortalReauthentication, bool $csrfValidated): string
    {
        if (!$provider->valid() || !$freshPortalReauthentication || !$csrfValidated || strlen($session)<16)
            throw new RuntimeException('Link proof unavailable');
        $u=$this->run('SELECT * FROM synthetic_users WHERE id=?',[$actor])->fetch();
        if (!$u || !$this->eligible($u)) throw new RuntimeException('Link proof unavailable');
        $token=bin2hex(random_bytes(32));
        $now=(int)$this->db->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
        $this->run('INSERT INTO link_proofs (digest,actor,subject_digest,email_digest,session_digest,expires) VALUES (?,?,?,?,?,?)',
            [hash('sha256',$token),$actor,hash('sha256',$provider->subject),hash('sha256',strtolower($provider->email)),hash('sha256',$session),$now+120]);
        return $token;
    }

    /** No user creation, portal verification writes, password writes or actual session issuance. */
    public function decide(SyntheticProviderEvidence $provider, string $purpose='login', ?int $actor=null,
        ?string $proof=null, string $session=''): array
    {
        if (!$provider->valid()) return ['decision'=>'deny_provider'];
        if (!in_array($purpose,['login','link'],true)) return ['decision'=>'deny_purpose'];
        try {
            $this->db->beginTransaction();
            // Conservative lab serialization avoids lock-order ambiguity across subject/email/actor.
            $this->db->query('SELECT id FROM identity_mutex WHERE id=1 FOR UPDATE')->fetch();
            if ($this->hook) ($this->hook)('locked');
            $bound=$this->run('SELECT * FROM synthetic_users WHERE google_subject=? FOR UPDATE',[$provider->subject])->fetch();
            $email=$this->run('SELECT * FROM synthetic_users WHERE email=? FOR UPDATE',[strtolower($provider->email)])->fetch();
            $u=$purpose==='link' ? $this->run('SELECT * FROM synthetic_users WHERE id=? FOR UPDATE',[$actor])->fetch() : ($bound?:$email);
            $decision='portal_enrollment_required';
            if ($bound && (($purpose==='link' && (int)$bound['id']!==$actor) || ($email && $email['id']!==$bound['id']))) $decision='binding_conflict';
            elseif ($u) {
                if ($u['status']!=='active') $decision='deny_status';
                elseif ($u['email_verified_at']===null) $decision='portal_verification_required';
                elseif ($u['google_subject']!==null && $u['google_subject']!==$provider->subject) $decision='binding_conflict';
                elseif (strtolower($u['email'])!==strtolower($provider->email)) $decision='portal_email_review_required';
                elseif ($purpose==='login') $decision=$bound?'authenticate':'explicit_portal_link_required';
                else {
                    $p=$this->run('SELECT * FROM link_proofs WHERE digest=? FOR UPDATE',[hash('sha256',$proof??'')])->fetch();
                    $now=(int)$this->db->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
                    if (!$p || (int)$p['actor']!==$actor || $p['consumed'] || (int)$p['expires']<=$now
                        || !hash_equals($p['subject_digest'],hash('sha256',$provider->subject))
                        || !hash_equals($p['email_digest'],hash('sha256',strtolower($provider->email)))
                        || !hash_equals($p['session_digest'],hash('sha256',$session))) $decision='explicit_portal_link_required';
                    elseif ($bound) $decision='already_bound';
                    else {
                        $q=$this->run("UPDATE synthetic_users SET google_subject=?,binding_origin='explicit',version=version+1 WHERE id=? AND google_subject IS NULL AND status='active' AND email_verified_at IS NOT NULL AND version=?",[$provider->subject,$actor,$u['version']]);
                        if ($q->rowCount()!==1) throw new RuntimeException('Conditional binding conflict');
                        $this->run('UPDATE link_proofs SET consumed=1 WHERE digest=?',[hash('sha256',$proof)]);
                        $this->run("INSERT INTO identity_events(actor,action) VALUES (?,'explicit_google_link')",[$actor]);
                        if ($this->hook) ($this->hook)('linked');
                        $decision='linked';
                    }
                }
            }
            $this->db->commit();
            return ['decision'=>$decision,'new_connection_eligible'=>in_array($decision,['authenticate','linked','already_bound'],true)];
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw new RuntimeException('Identity operation unavailable',0,$e); }
    }

    /** Aggregate read-only inventory; never infers password usability from hash presence. */
    public function compatibility(): array
    {
        $counts=['google_only'=>0,'previous_auto_link'=>0,'unverified'=>0,'no_password_option'=>0];
        foreach ($this->db->query('SELECT google_subject,email_verified_at,password_available,binding_origin FROM synthetic_users')->fetchAll() as $u) {
            if ($u['google_subject']!==null && !$u['password_available']) $counts['google_only']++;
            if ($u['binding_origin']==='auto') $counts['previous_auto_link']++;
            if ($u['email_verified_at']===null) $counts['unverified']++;
            if (!$u['password_available']) $counts['no_password_option']++;
        }
        return $counts;
    }
}

/** Durable first-consumer contract; laboratory implementation, NOT deployable configuration. */
final class IsolatedSqlReturnClaims
{
    public function __construct(private PDO $db, private string $generation, private int $capacity=32,
        private int $maxSkew=2, private int $cleanupBatch=8, private ?Closure $hook=null) {
        if (!preg_match('/^[a-f0-9]{32}$/D',$generation) || $capacity<1 || $cleanupBatch<1 || $cleanupBatch>100 || $maxSkew<0 || $maxSkew>30)
            throw new InvalidArgumentException('Claim configuration unavailable');
        identityClaimGuard($db);
    }
    public function claim(string $digest,int $expires,int $requestNow): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$digest)) throw new RuntimeException('Claim unavailable');
        try {
            $this->db->beginTransaction();
            $c=$this->db->query('SELECT * FROM claim_control WHERE id=1 FOR UPDATE')->fetch();
            $now=(int)$this->db->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
            if (!$c || !$c['ready'] || !hash_equals($c['generation'],$this->generation) || $now<(int)$c['not_before']
                || $now<(int)$c['last_clock'] || abs($now-$requestNow)>$this->maxSkew || $expires>$now+600+$this->maxSkew)
                throw new RuntimeException('Claim unavailable');
            // Counter detects missing rows, but cannot detect a coherent rollback of the entire store.
            if ((int)$this->db->query('SELECT COUNT(*) FROM return_claims')->fetchColumn()!==(int)$c['used']) throw new RuntimeException('Claim unavailable');
            $q=$this->db->prepare('DELETE FROM return_claims WHERE expires<=? ORDER BY expires LIMIT '.$this->cleanupBatch);
            $q->execute([$now]); $removed=$q->rowCount();
            if ($this->hook) ($this->hook)('cleanup');
            $q=$this->db->prepare('UPDATE claim_control SET used=used-?,last_clock=? WHERE id=1');$q->execute([$removed,$now]);
            $q=$this->db->prepare('SELECT digest FROM return_claims WHERE digest=?');$q->execute([$digest]);
            if ($expires<=$now || $q->fetchColumn()) { $this->db->commit();return false; }
            if ((int)$c['used']-$removed >= $this->capacity) throw new RuntimeException('Claim unavailable');
            $q=$this->db->prepare('INSERT INTO return_claims(digest,expires) VALUES (?,?)');$q->execute([$digest,$expires]);
            $this->db->exec('UPDATE claim_control SET used=used+1 WHERE id=1');
            if ($this->hook) ($this->hook)('before_commit');
            $this->db->commit();
            // Injection models lost acknowledgment AFTER actual commit; never return true on uncertainty.
            if ($this->hook) ($this->hook)('after_commit');
            return true;
        } catch (Throwable $e) {
            try { if ($this->db->inTransaction()) $this->db->rollBack(); } catch (Throwable) {}
            throw new RuntimeException('Claim unavailable',0,$e);
        }
    }
}
