<?php

/** Module-only identity check. Does not load dashboard/account services. */
function newConnectionApplicant(array $session, callable $connection, ?callable $identityRepository = null): array
{
    $id = filter_var($session['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new RuntimeException('Please sign in to continue.', 401);
    }

    // Resolve lazily: unauthenticated requests never open a database connection.
    if ($identityRepository !== null) {
        // Trusted PHP dependency only; no request-selected schema or identity.
        $user = $identityRepository($id);
    } else {
    $pdo = $connection();
    $statement = $pdo->prepare('SELECT id, name, email, status, email_verified_at FROM mco_portal.users WHERE id = ? LIMIT 1');
    $statement->execute([$id]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
    }
    if (!is_array($user) || (int)($user['id'] ?? 0) !== $id || ($user['status'] ?? null) !== 'active' || empty($user['email_verified_at'])) {
        throw new RuntimeException('An active account with a verified email is required.', 403);
    }
    return $user;
}
