<?php

/** Archive only this user's portal association; operational records are read-only. */
function unlinkPalecoAccount(PDO $pdo, int $userId, string $acctNo, string $token, string $csrf): bool
{
    if ($userId <= 0 || $acctNo === '' || $csrf === '' || !hash_equals($csrf, $token)) {
        throw new DomainException('Invalid account unlink request.');
    }
    $pdo->beginTransaction();
    try {
        $user = $pdo->prepare('SELECT id FROM mco_portal.users WHERE id = ? FOR UPDATE');
        $user->execute([$userId]);
        if (!$user->fetchColumn()) throw new DomainException('Invalid account unlink request.');
        $find = $pdo->prepare('SELECT id, status, is_primary FROM mco_portal.paleco_accounts WHERE user_id = ? AND AcctNo = ? FOR UPDATE');
        $find->execute([$userId, $acctNo]);
        $link = $find->fetch(PDO::FETCH_ASSOC);
        if (!$link) throw new DomainException('That account is not linked to your profile.');
        if ($link['status'] !== 'active') {
            $pdo->commit();
            return false;
        }
        $update = $pdo->prepare("UPDATE mco_portal.paleco_accounts SET status = 'archived', is_primary = 0 WHERE id = ? AND user_id = ?");
        $update->execute([$link['id'], $userId]);
        $next = $pdo->prepare("SELECT id, is_primary FROM mco_portal.paleco_accounts WHERE user_id = ? AND status = 'active' ORDER BY is_primary DESC, id ASC LIMIT 1 FOR UPDATE");
        $next->execute([$userId]);
        $remaining = $next->fetch(PDO::FETCH_ASSOC);
        if ($remaining && !$remaining['is_primary']) {
            $primary = $pdo->prepare('UPDATE mco_portal.paleco_accounts SET is_primary = 1 WHERE id = ? AND user_id = ?');
            $primary->execute([$remaining['id'], $userId]);
        }
        // A pre-unlink challenge must not silently restore the removed association.
        $clear = $pdo->prepare('DELETE FROM mco_portal.account_link_challenges WHERE user_id = ? AND AcctNo = ?');
        $clear->execute([$userId, $acctNo]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function maskPalecoAccountNumber(string $value): string
{
    $value = trim($value);
    return $value === '' ? '****' : str_repeat('*', max(4, strlen($value) - 4)) . substr($value, -4);
}
