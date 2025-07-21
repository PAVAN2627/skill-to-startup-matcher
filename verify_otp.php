<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$error = '';
$success = '';

// Check if user came from registration
if (!isset($_SESSION['verify_email']) || !isset($_SESSION['verify_user_type'])) {
    // Clear any stale session data
    unset($_SESSION['verify_email']);
    unset($_SESSION['verify_user_type']);
    unset($_SESSION['registration_data']);
    
    // Redirect to login with message
    $_SESSION['error_message'] = 'Please complete registration first before email verification.';
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp']);
    $email = $_SESSION['verify_email'];
    $user_type = $_SESSION['verify_user_type'];
    
    if (empty($entered_otp)) {
        $error = 'Please enter the OTP.';
    } else {
        try {
            // Check if this is a new registration (data in session) or existing user verification
            if (isset($_SESSION['registration_data']) && $user_type === 'student') {
                // New student registration - verify OTP from session data
                $reg_data = $_SESSION['registration_data'];
                
                if ($reg_data['otp'] === $entered_otp && $reg_data['otp_expires'] > date('Y-m-d H:i:s')) {
                    // OTP is valid, insert user into database
                    $stmt = $pdo->prepare("INSERT INTO students (name, email, password, skills, interests, availability, college, contact, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([
                        $reg_data['name'],
                        $reg_data['email'],
                        $reg_data['password'],
                        $reg_data['skills'],
                        $reg_data['interests'],
                        $reg_data['availability'],
                        $reg_data['college'],
                        $reg_data['contact']
                    ]);
                    
                    // Clear session data
                    unset($_SESSION['verify_email']);
                    unset($_SESSION['verify_user_type']);
                    unset($_SESSION['registration_data']);
                    
                    $success = 'Registration completed successfully! You can now login.';
                    header("refresh:3;url=login.php");
                    
                } else {
                    if ($reg_data['otp_expires'] <= date('Y-m-d H:i:s')) {
                        $error = 'OTP has expired. Please request a new one.';
                    } else {
                        $error = 'Invalid OTP. Please check and try again.';
                    }
                }
            } else {
                // Existing user verification (startups or password reset)
                $table = ($user_type === 'student') ? 'students' : 'startups';
                
                // Check OTP and expiry in database
                $stmt = $pdo->prepare("SELECT * FROM $table WHERE email = ? AND otp = ? AND otp_expires > NOW()");
                $stmt->execute([$email, $entered_otp]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // OTP is valid, verify the user
                    $update_stmt = $pdo->prepare("UPDATE $table SET is_verified = 1, otp = NULL, otp_expires = NULL WHERE email = ?");
                    $update_stmt->execute([$email]);
                    
                    // Clear verification session data
                    unset($_SESSION['verify_email']);
                    unset($_SESSION['verify_user_type']);
                    
                    $success = 'Email verified successfully! You can now login.';
                    header("refresh:3;url=login.php");
                    
                } else {
                    // Check if OTP exists but expired
                    $check_stmt = $pdo->prepare("SELECT * FROM $table WHERE email = ? AND otp = ?");
                    $check_stmt->execute([$email, $entered_otp]);
                    $expired_user = $check_stmt->fetch();
                    
                    if ($expired_user) {
                        $error = 'OTP has expired. Please request a new one.';
                    } else {
                        $error = 'Invalid OTP. Please check and try again.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again.';
            error_log("OTP verification error: " . $e->getMessage());
        }
    }
}

// Handle resend OTP request
if (isset($_POST['resend_otp'])) {
    $email = $_SESSION['verify_email'];
    $user_type = $_SESSION['verify_user_type'];
    
    try {
        // Generate new OTP
        $new_otp = sprintf("%06d", mt_rand(1, 999999));
        $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        if (isset($_SESSION['registration_data']) && $user_type === 'student') {
            // New registration - update session data
            $_SESSION['registration_data']['otp'] = $new_otp;
            $_SESSION['registration_data']['otp_expires'] = $otp_expires;
            $name = $_SESSION['registration_data']['name'];
            
            // Send new OTP
            require_once 'includes/otp_mailer.php';
            if (sendOTPEmail($email, $new_otp, $name)) {
                $success = 'New OTP sent successfully! Check your email.';
            } else {
                $error = 'Failed to send OTP. Please try again.';
            }
        } else {
            // Existing user - update database
            $table = ($user_type === 'student') ? 'students' : 'startups';
            $name_field = ($user_type === 'student') ? 'name' : 'org_name';
            
            // Get user name
            $name_stmt = $pdo->prepare("SELECT $name_field as name FROM $table WHERE email = ?");
            $name_stmt->execute([$email]);
            $user_data = $name_stmt->fetch();
            
            if ($user_data) {
                // Update OTP in database
                $update_stmt = $pdo->prepare("UPDATE $table SET otp = ?, otp_expires = ? WHERE email = ?");
                $update_stmt->execute([$new_otp, $otp_expires, $email]);
                
                // Send new OTP
                require_once 'includes/otp_mailer.php';
                if (sendOTPEmail($email, $new_otp, $user_data['name'])) {
                    $success = 'New OTP sent successfully! Check your email.';
                } else {
                    $error = 'Failed to send OTP. Please try again.';
                }
            } else {
                $error = 'User not found. Please register again.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Failed to resend OTP. Please try again.';
        error_log("Resend OTP error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Skill to Startup Matcher</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verify-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 50px 40px;
            text-align: center;
            max-width: 450px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .verify-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .verify-header {
            margin-bottom: 40px;
        }

        .verify-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 35px;
            color: white;
        }

        .verify-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .verify-subtitle {
            color: #666;
            font-size: 16px;
            line-height: 1.5;
        }

        .verify-email {
            color: #667eea;
            font-weight: 600;
            word-break: break-all;
        }

        .otp-form {
            margin: 40px 0;
        }

        .otp-input-container {
            margin-bottom: 30px;
        }

        .otp-label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .otp-input {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 24px;
            text-align: center;
            letter-spacing: 8px;
            font-weight: bold;
            color: #333;
            background: #f8f9fa;
            transition: all 0.3s ease;
            outline: none;
        }

        .otp-input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .otp-input.complete {
            border-color: #28a745;
            background: #f8fff9;
        }

        .verify-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .verify-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .verify-btn:active {
            transform: translateY(0);
        }

        .verify-btn.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 500;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .resend-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e1e5e9;
        }

        .resend-text {
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .resend-btn {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-right: 10px;
        }

        .resend-btn:hover {
            background: #667eea;
            color: white;
        }

        .back-btn {
            background: transparent;
            color: #6c757d;
            border: 2px solid #6c757d;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .back-btn:hover {
            background: #6c757d;
            color: white;
            text-decoration: none;
        }

        .loading {
            display: none;
            margin-top: 20px;
        }

        .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            .verify-container {
                padding: 30px 25px;
                margin: 10px;
            }
            
            .verify-title {
                font-size: 24px;
            }
            
            .otp-input {
                font-size: 20px;
                letter-spacing: 6px;
            }
        }

        .success-animation {
            animation: successPulse 0.6s ease-out;
        }

        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-header">
            <div class="verify-icon">📧</div>
            <h1 class="verify-title">Verify Your Email</h1>
            <p class="verify-subtitle">
                We've sent a 6-digit code to<br>
                <span class="verify-email"><?php echo htmlspecialchars($_SESSION['verify_email']); ?></span>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($success); ?>
                <div class="loading">
                    <div class="spinner"></div>
                    <p style="margin-top: 10px; font-size: 14px;">Redirecting to login...</p>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" class="otp-form" id="otpForm">
                <div class="otp-input-container">
                    <label for="otp" class="otp-label">Enter Verification Code</label>
                    <input type="text" 
                           id="otp" 
                           name="otp" 
                           class="otp-input"
                           maxlength="6" 
                           pattern="[0-9]{6}" 
                           placeholder="000000" 
                           required 
                           autocomplete="one-time-code">
                </div>

                <button type="submit" class="verify-btn" id="verifyBtn">
                    🔒 Verify Email
                </button>
            </form>

            <div class="resend-section">
                <p class="resend-text">Didn't receive the code?</p>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="resend_otp" class="resend-btn">
                        📤 Resend Code
                    </button>
                </form>
                <a href="login.php" class="back-btn">← Back to Login</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-focus on OTP input
        const otpInput = document.getElementById('otp');
        const verifyBtn = document.getElementById('verifyBtn');
        
        if (otpInput) {
            otpInput.focus();
            
            // Only allow numbers
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Update button state based on input length
                if (this.value.length === 6) {
                    this.classList.add('complete');
                    verifyBtn.classList.add('success');
                    verifyBtn.innerHTML = '✅ Verify Now';
                } else {
                    this.classList.remove('complete');
                    verifyBtn.classList.remove('success');
                    verifyBtn.innerHTML = '🔒 Verify Email';
                }
            });

            // Auto-submit when 6 digits are entered (optional)
            otpInput.addEventListener('input', function(e) {
                if (this.value.length === 6) {
                    // Optional: Auto-submit after a short delay
                    setTimeout(() => {
                        if (this.value.length === 6) {
                            verifyBtn.classList.add('success-animation');
                        }
                    }, 100);
                }
            });
        }

        // Handle form submission with loading state
        const form = document.getElementById('otpForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const btn = document.getElementById('verifyBtn');
                btn.innerHTML = '⏳ Verifying...';
                btn.disabled = true;
            });
        }

        // Show loading animation for success redirect
        <?php if ($success): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const loading = document.querySelector('.loading');
                if (loading) {
                    loading.style.display = 'block';
                }
            });
        <?php endif; ?>

        // Prevent paste of non-numeric content
        otpInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const numericPaste = paste.replace(/[^0-9]/g, '').substring(0, 6);
            this.value = numericPaste;
            
            // Trigger input event to update UI
            this.dispatchEvent(new Event('input'));
        });
    </script>
</body>
</html>
