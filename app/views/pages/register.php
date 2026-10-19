

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Create Account | PALECO MCO Portal</title>

    <link
        rel="icon"
        type="image/png"
        href="assets/images/logo.png"
    >

    <!-- Google Font -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <!-- Register CSS -->
    <link
        rel="stylesheet"
        href="assets/css/register.css"
    >

</head>


<body>


<div class="page">


    <!-- =========================================
         LEFT BRANDING PANEL
    ========================================== -->

    <section class="branding-panel">


        <div class="brand-content">


            <!-- LOGO -->

            <div class="logo-wrapper">

                <img
                    src="assets/images/logo.png"
                    alt="Palawan Electric Cooperative Logo"
                    class="logo"
                >

                <div>

                    <div class="brand-name">
                        PALAWAN ELECTRIC COOPERATIVE (PALECO)
                    </div>

                    <div class="brand-subtitle">
                        KM 3.35, North National Highway,
                        Barangay Tiniguiban,
                        Puerto Princesa City
                    </div>

                </div>

            </div>


            <!-- HERO -->

            <div class="hero">

                <h1>

                    STAY CONNECTED.

                    <span>
                        STAY INFORMED.
                    </span>

                </h1>


                <div class="green-line"></div>


                <p>

                    Create your PALECO Member-Consumer
                    Online Portal account and conveniently
                    manage your electricity services online.

                </p>

            </div>

        </div>


        <!-- FEATURES -->

        <div class="features">


            <div class="feature">

                <i class="bi bi-person-check"></i>

                <strong>
                    Easy Access
                </strong>

                <span>
                    Manage your account anytime.
                </span>

            </div>


            <div class="feature">

                <i class="bi bi-shield-check"></i>

                <strong>
                    Secure
                </strong>

                <span>
                    Your account is protected.
                </span>

            </div>


            <div class="feature">

                <i class="bi bi-envelope-check"></i>

                <strong>
                    Verified Email
                </strong>

                <span>
                    Confirm your email securely.
                </span>

            </div>


        </div>


    </section>



    <!-- =========================================
         RIGHT REGISTRATION PANEL
    ========================================== -->

    <main class="register-panel">


        <div class="register-container">


            <div class="register-card">


                <!-- =================================
                     REGISTRATION FORM
                ================================== -->

                <div
                    class="registration-section"
                    id="registrationSection"
                >


                    <div class="register-header">

                        <div class="eyebrow">
                            Member-Consumer Registration
                        </div>

                        <h2>
                            Create Account
                        </h2>

                        <p>
                            Enter your details to get started.
                        </p>

                    </div>


                    <!-- PHP ERRORS -->

                    <?php if (!empty($errors)): ?>

                        <div class="error-box">

                            <i class="bi bi-exclamation-circle"></i>

                            <div>

                                <?php foreach ($errors as $error): ?>

                                    <div>
                                        <?= htmlspecialchars($error) ?>
                                    </div>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <form
                        id="registerForm"
                        action="register.php"
                        method="POST"
                        novalidate
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="register"
                        >

                        <!-- STRUCTURED PROFILE NAME -->
                        <div id="nameError" class="field-error" aria-live="polite"></div>

                        <div class="form-group"><label class="form-label" for="name">First Name</label><div class="input-wrapper"><input class="form-control" id="name" name="first_name" maxlength="25" required  value="<?= htmlspecialchars(is_string($_POST['first_name'] ?? null) ? $_POST['first_name'] : '', ENT_QUOTES, 'UTF-8') ?>"></div></div>
                        <div class="form-group"><label class="form-label" for="last_name">Last Name</label><div class="input-wrapper"><input class="form-control" id="last_name" name="last_name" maxlength="25" required  value="<?= htmlspecialchars(is_string($_POST['last_name'] ?? null) ? $_POST['last_name'] : '', ENT_QUOTES, 'UTF-8') ?>"></div></div>
                        <div class="form-group"><label class="form-label" for="middle_name">Middle Name (optional)</label><div class="input-wrapper"><input class="form-control" id="middle_name" name="middle_name" maxlength="25"   value="<?= htmlspecialchars(is_string($_POST['middle_name'] ?? null) ? $_POST['middle_name'] : '', ENT_QUOTES, 'UTF-8') ?>"></div></div>
                        <div class="form-group"><label class="form-label" for="extension_name">Name Extension (optional)</label><div class="input-wrapper"><input class="form-control" id="extension_name" name="extension_name" maxlength="25"   value="<?= htmlspecialchars(is_string($_POST['extension_name'] ?? null) ? $_POST['extension_name'] : '', ENT_QUOTES, 'UTF-8') ?>"></div></div>
                        <div class="form-group"><label class="form-label" for="contact_number">Contact Number (optional)</label><div class="input-wrapper"><input class="form-control" id="contact_number" name="contact_number" maxlength="11"  inputmode="numeric" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars(is_string($_POST['contact_number'] ?? null) ? $_POST['contact_number'] : '', ENT_QUOTES, 'UTF-8') ?>"></div></div>
                        <div class="form-group">

                            <label
                                for="email"
                                class="form-label"
                            >
                                Email Address
                            </label>


                            <div class="input-wrapper">

                                <i class="bi bi-envelope"></i>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    placeholder="Enter your email address"
                                    autocomplete="email"
                                    maxlength="254"
                                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                                    required
                                >

                            </div>


                            <div class="field-help">
                                We'll send a verification code to this email.
                            </div>


                            <div
                                class="field-error"
                                id="emailError"
                            ></div>

                        </div>



                        <!-- PASSWORD -->

                        <div class="form-group">

                            <label
                                for="password"
                                class="form-label"
                            >
                                Password
                            </label>


                            <div class="input-wrapper">

                                <i class="bi bi-lock"></i>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    placeholder="Create a password"
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


                            <!-- PASSWORD STRENGTH -->

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



                        <!-- CONFIRM PASSWORD -->

                        <div class="form-group">

                            <label
                                for="confirm_password"
                                class="form-label"
                            >
                                Confirm Password
                            </label>


                            <div class="input-wrapper">

                                <i class="bi bi-lock-fill"></i>

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



                        <!-- TERMS -->

                        <label class="terms">

                            <input
                                type="checkbox"
                                id="terms"
                                name="terms"
                                required
                            >

                            <span>

                                I agree to the
                                <a href="#">
                                    Terms of Use
                                </a>
                                and
                                <a href="#">
                                    Privacy Policy
                                </a>.

                            </span>

                        </label>


                        <div
                            class="field-error"
                            id="termsError"
                        ></div>



                        <!-- CREATE ACCOUNT -->

                        <button
                            type="submit"
                            class="btn-register"
                        >

                            Create Account

                            <i class="bi bi-arrow-right"></i>

                        </button>


                    </form>


                    <!-- LOGIN LINK -->

                    <div class="login-link">

                        Already have an account?

                        <a href="index.php">
                            Sign in
                        </a>

                    </div>


                </div>



                <!-- =================================
                     OTP VERIFICATION
                ================================== -->

                <div
                    class="otp-section"
                    id="otpSection"
                    hidden
                >


                    <div class="otp-icon">

                        <i class="bi bi-envelope-check"></i>

                    </div>


                    <div class="register-header">

                        <div class="eyebrow">
                            Email Verification
                        </div>

                        <h2>
                            Verify Your Email
                        </h2>

                        <p>

                            We've sent a 6-digit
                            verification code to

                        </p>


                        <strong
                            id="otpEmail"
                            class="otp-email"
                        >
                            your email address
                        </strong>

                    </div>


                    <!-- OTP FORM -->

                    <form
                        id="otpForm"
                        action="register.php"
                        method="POST"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="verify_otp"
                        >

                        <div class="otp-inputs">

                            <input
                                type="text"
                                maxlength="1"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                class="otp-input"
                            >

                            <input
                                type="text"
                                maxlength="1"
                                inputmode="numeric"
                                class="otp-input"
                            >

                            <input
                                type="text"
                                maxlength="1"
                                inputmode="numeric"
                                class="otp-input"
                            >

                            <input
                                type="text"
                                maxlength="1"
                                inputmode="numeric"
                                class="otp-input"
                            >

                            <input
                                type="text"
                                maxlength="1"
                                inputmode="numeric"
                                class="otp-input"
                            >

                            <input
                                type="text"
                                maxlength="1"
                                inputmode="numeric"
                                class="otp-input"
                            >

                            <input
                                type="hidden"
                                name="otp"
                                id="otpCode"
                            >

                        </div>

                        <div
                            class="otp-error"
                            id="otpError"
                        >
                            <?= htmlspecialchars($otpError) ?>
                        </div>


                        <button
                            type="submit"
                            class="btn-register"
                        >

                            Verify Email

                            <i class="bi bi-check-lg"></i>

                        </button>


                    </form>


                    <!-- RESEND -->

                    <div class="resend-section">

                        <span>
                            Didn't receive the code?
                        </span>


                        <button
                            type="button"
                            id="resendOtp"
                            class="resend-button"
                        >
                            Resend Code
                        </button>


                        <div
                            id="resendTimer"
                            class="resend-timer"
                        ></div>

                    </div>


                    <button
                        type="button"
                        id="backToRegistration"
                        class="back-button"
                    >

                        <i class="bi bi-arrow-left"></i>

                        Back to registration

                    </button>


                </div>


            </div>


            <!-- FOOTER -->

            <div class="footer">

                © 2026 Palawan Electric Cooperative
                (PALECO).
                All rights reserved.

            </div>


        </div>


    </main>


</div>



<!-- Register JavaScript -->

<script src="assets/js/register.js"></script>

<?php if ($showOtp): ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        showOtpSection();

        startResendTimer(
            <?= (int)$resendSeconds ?>
        );

    });
</script>

<?php endif; ?>

</body>

</html>
