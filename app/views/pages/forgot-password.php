

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Forgot Password | PALECO MCO Portal</title>

    <link
        rel="stylesheet"
        href="assets/css/forgot-password.css"
    >

</head>

<body>

    <div class="page">

        <!-- LEFT BRANDING -->
        <section class="branding-panel">

            <div class="brand-content">

                <div class="logo-wrapper">

                    <img
                        src="assets/images/logo.png"
                        alt="PALECO MCO Portal Logo"
                        class="logo"
                    >

                    <div>
                        <div class="brand-name">
                            PALECO MCO Portal
                        </div>

                        <div class="brand-subtitle">
                            Management & Coordination Office
                        </div>
                    </div>

                </div>


                <div class="hero">

                    <h1>
                        Get back to
                        <span>your account.</span>
                    </h1>

                    <div class="green-line"></div>

                    <p>
                        Forgot your password? Don't worry.
                        We'll help you securely regain access
                        to your PALECO MCO Portal account.
                    </p>

                </div>

            </div>

        </section>


        <!-- RIGHT FORM -->
        <section class="login-panel">

            <div class="login-container">

                <div class="login-card">

                    <div class="login-header">

                        <div class="eyebrow">
                            Account Recovery
                        </div>

                        <h2>
                            Forgot Password?
                        </h2>

                        <p>
                            Enter your email address and we'll
                            send you a secure link to reset your password.
                        </p>

                    </div>


                    <?php if ($message !== ''): ?>

                        <div class="<?= $messageType === 'success'
                            ? 'login-success'
                            : 'login-error' ?>">

                            <i class="fa-solid <?= $messageType === 'success'
                                ? 'fa-circle-check'
                                : 'fa-circle-exclamation' ?>"></i>

                            <span>
                                <?= htmlspecialchars($message) ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <form
                        method="POST"
                        action=""
                        id="forgotPasswordForm"
                    >
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-group">

                            <label
                                for="email"
                                class="form-label"
                            >
                                Email Address
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-regular fa-envelope"></i>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    placeholder="Enter your email address"
                                    value="<?= htmlspecialchars(
                                        $email
                                    ) ?>"
                                    required
                                    autocomplete="email"
                                >

                            </div>

                        </div>


                        <button
                            type="submit"
                            class="btn-signin"
                            id="submitButton"
                        >
                            Send Reset Link
                        </button>

                    </form>


                    <div class="register">

                        <a href="index.php">
                            ← Back to Login
                        </a>

                    </div>


                    <div class="footer">

                        PALECO MCO Portal &copy; <?= date('Y') ?>

                    </div>

                </div>

            </div>

        </section>

    </div>


    <script
        src="assets/js/forgot-password.js">
    </script>

</body>

</html>
