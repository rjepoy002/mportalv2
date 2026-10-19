<?php

session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/mailer.php';

$errors = [];
$otpError = '';
$showOtp = false;

$otpExpiresAt = null;
$resendAvailableAt = null;
$resendSeconds = 60;

// Keep OTP section visible while registration is pending
// only on a normal page load or OTP verification request.

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' ||
    ($_POST['action'] ?? '') === 'verify_otp'
) {

    if (isset($_SESSION['registration'])) {

        $showOtp = true;

        $stmt = $pdo->prepare("
            SELECT expires_at, resend_available_at
            FROM email_verifications
            WHERE email = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            $_SESSION['registration']['email']
        ]);

        $verification = $stmt->fetch();

        if ($verification) {

            $otpExpiresAt =
                $verification['expires_at'];

            $resendAvailableAt =
                $verification['resend_available_at'];

            if ($resendAvailableAt) {

                $resendSeconds = max(
                    0,
                    strtotime($resendAvailableAt) - time()
                );
            }
        }
    }
}


// =====================================================
// REGISTRATION
// =====================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'register'
) {

    require_once __DIR__ . '/../services/ebills-enrollment.php';
    $structuredProfile = [];
    try {
        $structuredProfile = portalProfileValues($_POST);
        $name = implode(' ', array_filter([$structuredProfile['first_name'], $structuredProfile['middle_name'], $structuredProfile['last_name'], $structuredProfile['extension_name']]));
    } catch (DomainException $e) {
        $errors[] = $e->getMessage();
        $name = '';
    }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';


    // ---------------------------------------------
    // NAME
    // ---------------------------------------------

    if ($name === '') {

        $errors[] = 'Please enter your first and last names.';

    } elseif (mb_strlen($name) > 150) {

        $errors[] = 'Your name is too long.';
    }


    // ---------------------------------------------
    // EMAIL
    // ---------------------------------------------

    if ($email === '') {

        $errors[] = 'Please enter your email address.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errors[] = 'Please enter a valid email address.';

    } elseif (strlen($email) > 255) {

        $errors[] = 'Your email address is too long.';
    }


    // ---------------------------------------------
    // PASSWORD
    // ---------------------------------------------

    if ($password === '') {

        $errors[] = 'Please enter a password.';

    } elseif (strlen($password) < 8) {

        $errors[] = 'Password must be at least 8 characters.';

    } elseif (!preg_match('/[A-Za-z]/', $password)) {

        $errors[] = 'Password must contain at least one letter.';

    } elseif (!preg_match('/[0-9]/', $password)) {

        $errors[] = 'Password must contain at least one number.';
    }


    // ---------------------------------------------
    // CONFIRM PASSWORD
    // ---------------------------------------------

    if ($password !== $confirmPassword) {

        $errors[] = 'Passwords do not match.';
    }


    // ---------------------------------------------
    // TERMS
    // ---------------------------------------------

    if (!isset($_POST['terms'])) {

        $errors[] =
            'Please agree to the Terms of Use and Privacy Policy.';
    }


    // ---------------------------------------------
    // CHECK EMAIL
    // ---------------------------------------------

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $errors[] =
                'An account with this email address already exists.';
        }
    }


    // ---------------------------------------------
    // CREATE OTP
    // ---------------------------------------------

    if (empty($errors)) {

        try {

            $otp = (string) random_int(100000, 999999);

            $otpHash = password_hash(
                $otp,
                PASSWORD_DEFAULT
            );

            $expiresAt = date(
                'Y-m-d H:i:s',
                time() + 600
            );

            $resendAvailableAt = date(
                'Y-m-d H:i:s',
                time() + 60
            );

            $otpExpiresAt = $expiresAt;
            $resendSeconds = 60;

            // Remove previous verification codes

            $stmt = $pdo->prepare("
                DELETE FROM email_verifications
                WHERE email = ?
            ");

            $stmt->execute([$email]);


            // Store new OTP

            $stmt = $pdo->prepare("
                INSERT INTO email_verifications
                (
                    email,
                    otp_hash,
                    expires_at,
                    resend_available_at
                )
                VALUES
                (?, ?, ?, ?)
            ");

            $stmt->execute([
                $email,
                $otpHash,
                $expiresAt,
                $resendAvailableAt
            ]);


            // Store registration information
            // temporarily in the session

            $_SESSION['registration'] = [
                ...$structuredProfile,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash(
                    $password,
                    PASSWORD_DEFAULT
                )
            ];


            // Send OTP email

            $emailSent = sendEmail(
                $email,
                $name,
                'PALECO MCO Portal - Email Verification',
                '
                    <div style="
                        font-family: Arial, sans-serif;
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 30px;
                    ">

                        <h2>
                            Verify Your Email
                        </h2>

                        <p>
                            Hello ' .
                            htmlspecialchars($name) .
                            ',
                        </p>

                        <p>
                            Thank you for registering
                            for the PALECO MCO Portal.
                        </p>

                        <p>
                            Your verification code is:
                        </p>

                        <div style="
                            font-size: 32px;
                            font-weight: bold;
                            letter-spacing: 8px;
                            margin: 25px 0;
                        ">
                            ' . $otp . '
                        </div>

                        <p>
                            This code will expire in
                            <strong>10 minutes</strong>.
                        </p>

                        <p>
                            If you did not request this
                            verification code, you may
                            safely ignore this email.
                        </p>

                        <p>
                            PALECO MCO Portal
                        </p>

                    </div>
                ',
                'Your PALECO MCO Portal verification code is: '
                . $otp
                . "\n\n"
                . 'This code will expire in 10 minutes.'
            );


            if (!$emailSent) {

                // Remove OTP if email failed

                $stmt = $pdo->prepare("
                    DELETE FROM email_verifications
                    WHERE email = ?
                ");

                $stmt->execute([$email]);

                unset($_SESSION['registration']);

                $errors[] =
                    'We could not send the verification email. '
                    . 'Please try again.';
            } else {

                $showOtp = true;
            }


        } catch (Throwable $e) {

            error_log(
                'Registration error: ' . $e->getMessage()
            );

            unset($_SESSION['registration']);

            $errors[] =
                'Something went wrong while creating '
                . 'your verification request. Please try again.';
        }
    }
}

// =====================================================
// RESEND OTP
// =====================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'resend_otp'
) {

    header('Content-Type: application/json');

    if (!isset($_SESSION['registration'])) {

        echo json_encode([
            'success' => false,
            'message' =>
                'Your registration session has expired. Please register again.'
        ]);

        exit;
    }


    $registration = $_SESSION['registration'];

    $email = $registration['email'];
    $name  = $registration['name'];


    // ---------------------------------------------
    // Get current verification record
    // ---------------------------------------------

    $stmt = $pdo->prepare("
        SELECT *
        FROM email_verifications
        WHERE email = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$email]);

    $verification = $stmt->fetch();


    if (!$verification) {

        echo json_encode([
            'success' => false,
            'message' =>
                'Verification request not found. Please register again.'
        ]);

        exit;
    }


    // ---------------------------------------------
    // Check resend cooldown
    // ---------------------------------------------

    if (
        $verification['resend_available_at'] !== null &&
        strtotime($verification['resend_available_at']) > time()
    ) {

        $remaining = max(
            1,
            strtotime($verification['resend_available_at']) - time()
        );

        echo json_encode([
            'success' => false,
            'message' =>
                'Please wait before requesting another code.',
            'remaining' => $remaining
        ]);

        exit;
    }


    try {

        // -----------------------------------------
        // Generate new OTP
        // -----------------------------------------

        $otp = (string) random_int(100000, 999999);

        $otpHash = password_hash(
            $otp,
            PASSWORD_DEFAULT
        );


        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + 600
        );

        $resendAvailableAt = date(
            'Y-m-d H:i:s',
            time() + 60
        );


        // -----------------------------------------
        // Update verification record
        // -----------------------------------------

        $stmt = $pdo->prepare("
            UPDATE email_verifications
            SET
                otp_hash = ?,
                expires_at = ?,
                resend_available_at = ?,
                attempts = 0,
                verified_at = NULL
            WHERE id = ?
        ");

        $stmt->execute([
            $otpHash,
            $expiresAt,
            $resendAvailableAt,
            $verification['id']
        ]);


        // -----------------------------------------
        // Send email
        // -----------------------------------------

        $emailSent = sendEmail(
            $email,
            $name,
            'PALECO MCO Portal - New Verification Code',

            '
                <div style="
                    font-family: Arial, sans-serif;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 30px;
                ">

                    <h2>
                        Your New Verification Code
                    </h2>

                    <p>
                        Hello ' .
                        htmlspecialchars($name) .
                        ',
                    </p>

                    <p>
                        You requested a new verification
                        code for your PALECO MCO Portal
                        registration.
                    </p>

                    <p>
                        Your new verification code is:
                    </p>

                    <div style="
                        font-size: 32px;
                        font-weight: bold;
                        letter-spacing: 8px;
                        margin: 25px 0;
                    ">
                        ' . $otp . '
                    </div>

                    <p>
                        This code will expire in
                        <strong>10 minutes</strong>.
                    </p>

                    <p>
                        PALECO MCO Portal
                    </p>

                </div>
            ',

            'Your new PALECO MCO Portal verification code is: '
            . $otp
            . "\n\n"
            . 'This code will expire in 10 minutes.'
        );


        if (!$emailSent) {

            echo json_encode([
                'success' => false,
                'message' =>
                    'We could not send the verification email. Please try again.'
            ]);

            exit;
        }


        echo json_encode([
            'success' => true,
            'message' =>
                'A new verification code has been sent to your email.',
            'remaining' => 60
        ]);

        exit;


    } catch (Throwable $e) {

        error_log(
            'Resend OTP error: ' . $e->getMessage()
        );

        echo json_encode([
            'success' => false,
            'message' =>
                'Something went wrong. Please try again.'
        ]);

        exit;
    }
}


// =====================================================
// OTP VERIFICATION
// =====================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'verify_otp'
) {

    $otp = preg_replace(
        '/[^0-9]/',
        '',
        $_POST['otp'] ?? ''
    );


    if (!isset($_SESSION['registration'])) {

        $otpError =
            'Your registration session has expired. '
            . 'Please register again.';

    } elseif (strlen($otp) !== 6) {

        $otpError =
            'Please enter the 6-digit verification code.';

    } else {

        $registration = $_SESSION['registration'];


        $stmt = $pdo->prepare("
            SELECT *
            FROM email_verifications
            WHERE email = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            $registration['email']
        ]);

        $verification = $stmt->fetch();


        if (!$verification) {

            $otpError =
                'Verification code not found. '
                . 'Please request a new code.';

        } elseif ($verification['verified_at'] !== null) {

            $otpError =
                'This verification code has already been used.';

        } elseif (
            strtotime($verification['expires_at']) < time()
        ) {

            $otpError =
                'This verification code has expired. '
                . 'Please request a new code.';

        } elseif ($verification['attempts'] >= 5) {

            $otpError =
                'Too many incorrect attempts. '
                . 'Please request a new code.';

        } elseif (
            !password_verify(
                $otp,
                $verification['otp_hash']
            )
        ) {

            $stmt = $pdo->prepare("
                UPDATE email_verifications
                SET attempts = attempts + 1
                WHERE id = ?
            ");

            $stmt->execute([
                $verification['id']
            ]);

            $otpError =
                'Invalid verification code.';

        } else {

            // -----------------------------------------
            // OTP VERIFIED
            // -----------------------------------------

            $stmt = $pdo->prepare("
                INSERT INTO users
                    (
                        email,
                        name,
                        password_hash,
                        first_name, last_name, middle_name, extension_name, contact_number,
                        email_verified_at,
                        status
                    )
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
            ");

            $stmt->execute([
                $registration['email'],
                $registration['name'],
                $registration['password_hash'],
                $registration['first_name'] ?? null, $registration['last_name'] ?? null,
                $registration['middle_name'] ?? null, $registration['extension_name'] ?? null,
                $registration['contact_number'] ?? null
            ]);


            $stmt = $pdo->prepare("
                UPDATE email_verifications
                SET verified_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $verification['id']
            ]);


            // Clear temporary registration data

            unset($_SESSION['registration']);


            // Registration successful

            header('Location: index.php?registered=1');
            exit;
        }
    }
}
require __DIR__ . '/../views/pages/register.php';
