

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Reset Password | PALECO MCO Portal
    </title>


    <!-- System font stack avoids external font requests. -->








    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css"
    >


    <!-- Reset Password CSS -->

    <link
        rel="stylesheet"
        href="assets/css/reset-password.css"
    >

</head>


<body>


<div class="page">


    <!-- =========================================
         LEFT BRANDING
    ========================================== -->

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


            <!-- HERO -->

            <div class="hero">

                <h1>

                    Create a

                    <span>
                        new password.
                    </span>

                </h1>


                <div class="green-line"></div>


                <p>

                    Choose a strong password to keep
                    your PALECO MCO Portal account secure.

                </p>

            </div>


        </div>


    </section>



    <!-- =========================================
         RIGHT RESET PASSWORD PANEL
    ========================================== -->

    <section class="login-panel">


        <div class="login-container">


            <div class="login-card">


                <?php if ($user): ?>


                    <!-- =================================
                         HEADER
                    ================================== -->

                    <div class="login-header">

                        <div class="eyebrow">
                            Account Recovery
                        </div>


                        <h2>
                            Reset Password
                        </h2>


                        <p>
                            Create a new password for your
                            PALECO MCO Portal account.
                        </p>

                    </div>



                    <!-- =================================
                         ERROR MESSAGE
                    ================================== -->

                    <?php if ($message !== ''): ?>

                        <div class="login-error">

                            <i class="bi bi-exclamation-circle"></i>

                            <span>
                                <?= htmlspecialchars($message) ?>
                            </span>

                        </div>

                    <?php endif; ?>



                    <!-- =================================
                         RESET FORM
                    ================================== -->

                    <form
                        method="POST"
                        action=""
                        id="resetPasswordForm"
                        novalidate
                    >
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">


                        <!-- Keep reset token during POST -->

                        <input
                            type="hidden"
                            name="token"
                            value="<?= htmlspecialchars($token) ?>"
                        >



                        <!-- =================================
                             NEW PASSWORD
                        ================================== -->

                        <div class="form-group">

                            <label
                                for="password"
                                class="form-label"
                            >
                                New Password
                            </label>

                            <div class="input-wrapper">

                                <i class="bi bi-lock input-icon"></i>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    placeholder="Create a new password"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    id="passwordToggle"
                                    aria-label="Show password"
                                    tabindex="-1"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>

                            </div>


                            <div class="password-strength">

                                <div class="strength-bars">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>

                                <span
                                    id="strengthText"
                                    class="strength-text"
                                >
                                    Enter a password
                                </span>

                            </div>


                            <div class="password-requirements">

                                <div id="lengthRequirement">
                                    <i class="bi bi-circle"></i>
                                    At least 8 characters
                                </div>

                                <div id="numberRequirement">
                                    <i class="bi bi-circle"></i>
                                    At least one number
                                </div>

                                <div id="letterRequirement">
                                    <i class="bi bi-circle"></i>
                                    At least one letter
                                </div>

                            </div>


                            <div
                                class="field-error"
                                id="passwordError"
                            ></div>

                        </div>

                        <!-- =================================
                            CONFIRM PASSWORD
                        ================================== -->

                        <div class="form-group">

                            <label
                                for="confirm_password"
                                class="form-label"
                            >
                                Confirm Password
                            </label>

                            <div class="input-wrapper">

                                <i class="bi bi-lock-fill input-icon"></i>

                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    class="form-control"
                                    placeholder="Re-enter your password"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    id="confirmPasswordToggle"
                                    aria-label="Show password"
                                    tabindex="-1"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>

                            </div>

                            <div
                                class="field-error"
                                id="confirmPasswordError"
                            ></div>

                        </div>


                        <!-- =================================
                            RESET BUTTON
                        ================================== -->

                        <button
                            type="submit"
                            class="btn-signin"
                            id="submitButton"
                        >
                            Reset Password
                            <i class="bi bi-arrow-right"></i>
                        </button>


                    </form>



                    <!-- BACK TO LOGIN -->

                    <div class="register">

                        <a href="index.php">

                            <i class="bi bi-arrow-left"></i>

                            Back to Login

                        </a>

                    </div>



                <?php else: ?>


                    <!-- =================================
                         INVALID / EXPIRED LINK
                    ================================== -->

                    <div class="login-header">

                        <div class="eyebrow">
                            Account Recovery
                        </div>


                        <h2>
                            Link Unavailable
                        </h2>


                        <p>
                            This password reset link is invalid
                            or has expired.
                        </p>

                    </div>


                    <a
                        href="forgot-password.php"
                        class="btn-signin button-link"
                    >

                        Request New Reset Link

                    </a>


                    <div class="register">

                        <a href="index.php">

                            <i class="bi bi-arrow-left"></i>

                            Back to Login

                        </a>

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </section>


</div>



<!-- Reset Password JavaScript -->

<script
    src="assets/js/reset-password.js"
></script>


</body>

</html>
