<?php

require_once __DIR__ . '/../helpers/session-security.php';
portalSessionStart();
header('Referrer-Policy: no-referrer');
if (!portalOAuthStateValid($_GET['state'] ?? null) || !is_string($_GET['code'] ?? null) || isset($_GET['error'])) {
    http_response_code(400);
    exit('Unable to complete Google sign-in. Please try again.');
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/google.php';
require_once __DIR__ . '/../../config/database.php';

// =========================================
// GOOGLE CLIENT
// =========================================

$client = new Google\Client();

$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

// =========================================
// CHECK GOOGLE RESPONSE
// =========================================

if (!isset($_GET['code'])) {
    die('Google authentication was cancelled or failed.');
}

try {

    // Exchange authorization code for access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        die('Google authentication failed.');
    }

    $client->setAccessToken($token['access_token']);

    // =========================================
    // GET GOOGLE USER INFORMATION
    // =========================================

    $googleService = new Google\Service\Oauth2($client);

    $userInfo = $googleService->userinfo->get();

    if (!portalGoogleIdentity($userInfo->id, $userInfo->email, $userInfo->verified_email)) {
        throw new RuntimeException('Google identity unavailable.');
    }

    $googleId = $userInfo->id;
    $email    = strtolower(trim($userInfo->email));
    $name     = trim($userInfo->name);

    // =========================================
    // CHECK IF USER EXISTS
    // =========================================

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE email = :email
        LIMIT 1
    ");

    $stmt->execute([
        'email' => $email
    ]);

    $user = $stmt->fetch();

    // =========================================
    // EXISTING USER
    // =========================================

    if ($user) {

        if ($user['status'] !== 'active') throw new RuntimeException('Sign-in unavailable.');

        // If Google ID is not connected yet,
        // connect this Google account.
        if (empty($user['google_id'])) {

            // Google is not authoritative for ownership of arbitrary third-party
            // mailboxes. Do not automatically link those existing portal users.
            if (!str_ends_with($email, '@gmail.com') && empty($userInfo->hd)) {
                throw new RuntimeException('Sign-in unavailable.');
            }

            $stmt = $pdo->prepare("
                UPDATE users
                SET google_id = :google_id,
                    email_verified_at = COALESCE(
                        email_verified_at,
                        NOW()
                    ),
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'google_id' => $googleId,
                'id'        => $user['id']
            ]);

        } elseif ($user['google_id'] !== $googleId) {

            // The email belongs to another Google account.
            throw new RuntimeException('Sign-in unavailable.');

        }

    }

    // =========================================
    // NEW USER
    // =========================================

    else {

        // Generate a random password hash.
        // Google users do not need to know this password.
        $randomPassword = bin2hex(random_bytes(32));

        $passwordHash = password_hash(
            $randomPassword,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            INSERT INTO users (
                email,
                google_id,
                name,
                password_hash,
                email_verified_at,
                status
            )
            VALUES (
                :email,
                :google_id,
                :name,
                :password_hash,
                NOW(),
                'active'
            )
        ");

        $stmt->execute([
            'email'        => $email,
            'google_id'    => $googleId,
            'name'         => $name,
            'password_hash'=> $passwordHash
        ]);

        $userId = $pdo->lastInsertId();

        // Get the newly created user
        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $userId
        ]);

        $user = $stmt->fetch();
    }

    // =========================================
    // CHECK ACCOUNT STATUS
    // =========================================

    if ($user['status'] !== 'active') {

        throw new RuntimeException('Sign-in unavailable.');
    }

    // =========================================
    // CREATE LOGIN SESSION
    // =========================================

    portalAuthenticated($user);
    $_SESSION['logged_in'] = true;

    // =========================================
    // REDIRECT AFTER LOGIN
    // =========================================

    header('Location: dashboard.php');
    exit;

} catch (Throwable $e) {

    error_log(
        'Google sign-in failed.'
    );

    die('Unable to complete Google login. Please try again.');
}
