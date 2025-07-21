<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/otp_mailer.php';

// Clear any existing verification session data when accessing registration page
if (!$_POST && (isset($_SESSION['verify_email']) || isset($_SESSION['verify_user_type']))) {
    unset($_SESSION['verify_email']);
    unset($_SESSION['verify_user_type']);
    unset($_SESSION['registration_data']);
}

$error = '';
$success = '';
$step = 1; // 1: Registration form, 2: OTP verification

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == '1') {
        // Step 1: Registration
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $skills = sanitizeInput($_POST['skills']);
        $interests = sanitizeInput($_POST['interests']);
        $availability = sanitizeInput($_POST['availability']);
        $college = sanitizeInput($_POST['college']);
        $contact = sanitizeInput($_POST['contact']);
        
        // Validation
        if (empty($name) || empty($email) || empty($password) || empty($skills) || empty($interests) || empty($availability) || empty($college) || empty($contact)) {
            $error = 'All fields are required.';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $error = 'Email address already registered.';
                } else {
                    // Generate OTP
                    $otp = generateOTP();
                    $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Store registration data in session temporarily
                    $_SESSION['registration_data'] = [
                        'name' => $name,
                        'email' => $email,
                        'password' => $hashed_password,
                        'skills' => $skills,
                        'interests' => $interests,
                        'availability' => $availability,
                        'college' => $college,
                        'contact' => $contact,
                        'otp' => $otp,
                        'otp_expires' => $otp_expires
                    ];
                    
                    // Send OTP email
                    if (sendOTPEmail($email, $otp, $name)) {
                        // Set session data for verification
                        $_SESSION['verify_email'] = $email;
                        $_SESSION['verify_user_type'] = 'student';
                        
                        // Redirect to verification page
                        header('Location: ../verify_otp.php');
                        exit();
                    } else {
                        $error = 'Failed to send verification email. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Registration failed. Please try again.';
                error_log($e->getMessage());
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == '2') {
        // Step 2: OTP Verification
        $entered_otp = sanitizeInput($_POST['otp']);
        
        if (empty($entered_otp)) {
            $error = 'Please enter the OTP.';
            $step = 2;
        } elseif (!isset($_SESSION['registration_data'])) {
            $error = 'Session expired. Please register again.';
            $step = 1;
        } else {
            $reg_data = $_SESSION['registration_data'];
            
            if ($entered_otp === $reg_data['otp'] && strtotime($reg_data['otp_expires']) > time()) {
                try {
                    // Insert student into database
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
                    
                    // Clear registration data
                    unset($_SESSION['registration_data']);
                    
                    $success = 'Email verified successfully! You can now login to your account.';
                    // Redirect to login page after 3 seconds
                    header("refresh:3;url=../login.php");
                } catch (PDOException $e) {
                    $error = 'Failed to complete registration. Please try again.';
                    error_log($e->getMessage());
                    $step = 2;
                }
            } else {
                $error = 'Invalid or expired OTP. Please try again.';
                $step = 2;
            }
        }
    }
}

// Check if we have registration data in session (for OTP step)
if (isset($_SESSION['registration_data']) && $step == 1) {
    $step = 2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Skill2Startup</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Animated gradient background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -2;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Floating particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        /* Enhanced card styling */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        /* Header enhancement */
        header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <!-- Floating particles background -->
    <div class="particles" id="particles"></div>

    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1><a href="../index.php" style="color: white; text-decoration: none;">Skill2Startup</a></h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="../login.php">Login</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="card" style="max-width: 600px; margin: 2rem auto;">
                <div class="card-header">
                    <h2 class="card-title text-center">
                        <?php echo $step == 1 ? 'Student Registration' : 'Email Verification'; ?>
                    </h2>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <!-- Debug Information (remove in production) -->
                <?php if (isset($_GET['debug'])): ?>
                    <div class="alert alert-info">
                        <strong>Debug Info:</strong><br>
                        Step: <?php echo $step; ?><br>
                        POST data: <?php echo $_POST ? 'Yes' : 'No'; ?><br>
                        Session verify_email: <?php echo isset($_SESSION['verify_email']) ? $_SESSION['verify_email'] : 'Not set'; ?><br>
                        <a href="?">Hide Debug</a> | <a href="../debug.php">Full Session Debug</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary" style="font-size: 0.9em;">
                        Having issues? <a href="?debug=1">Show debug info</a> | <a href="../debug.php">Clear session</a>
                    </div>
                <?php endif; ?>

                <?php if ($step == 1): ?>
                    <!-- Registration Form -->
                    <form method="POST" action="">
                        <input type="hidden" name="step" value="1">
                        
                        <div class="form-group">
                            <label for="name">Full Name:</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address:</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password:</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="college">College/University:</label>
                            <input type="text" id="college" name="college" class="form-control" 
                                   value="<?php echo isset($_POST['college']) ? htmlspecialchars($_POST['college']) : ''; ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="contact">Contact Number:</label>
                            <input type="tel" id="contact" name="contact" class="form-control" 
                                   value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="skills">Skills:</label>
                            <textarea id="skills" name="skills" class="form-control" rows="3" 
                                      placeholder="e.g., Web Development, Python, Data Analysis, UI/UX Design" 
                                      required><?php echo isset($_POST['skills']) ? htmlspecialchars($_POST['skills']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="interests">Interests:</label>
                            <textarea id="interests" name="interests" class="form-control" rows="3" 
                                      placeholder="e.g., Machine Learning, E-commerce, FinTech, HealthTech" 
                                      required><?php echo isset($_POST['interests']) ? htmlspecialchars($_POST['interests']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="availability">Availability:</label>
                            <select id="availability" name="availability" class="form-control" required>
                                <option value="">Select availability</option>
                                <option value="Part-time" <?php echo (isset($_POST['availability']) && $_POST['availability'] === 'Part-time') ? 'selected' : ''; ?>>Part-time</option>
                                <option value="Full-time" <?php echo (isset($_POST['availability']) && $_POST['availability'] === 'Full-time') ? 'selected' : ''; ?>>Full-time</option>
                                <option value="Internship" <?php echo (isset($_POST['availability']) && $_POST['availability'] === 'Internship') ? 'selected' : ''; ?>>Internship</option>
                                <option value="Project-based" <?php echo (isset($_POST['availability']) && $_POST['availability'] === 'Project-based') ? 'selected' : ''; ?>>Project-based</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Register</button>
                    </form>
                <?php else: ?>
                    <!-- OTP Verification Form -->
                    <div class="text-center mb-3">
                        <p>We've sent a 6-digit verification code to your email address.</p>
                        <p><strong>Please enter the OTP below:</strong></p>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="step" value="2">
                        
                        <div class="form-group text-center">
                            <label for="otp">Enter OTP:</label>
                            <input type="text" id="otp" name="otp" class="form-control" 
                                   style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem; max-width: 200px; margin: 0 auto;" 
                                   maxlength="6" pattern="[0-9]{6}" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Verify Email</button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <p><small>Didn't receive the OTP? <a href="register.php">Try again</a></small></p>
                    </div>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <p>Already have an account? <a href="../login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Skill2Startup. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
    <script>
        // Create floating particles
        function createParticles() {
            const particleContainer = document.getElementById('particles');
            
            for (let i = 0; i < 30; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Random size and position
                const size = Math.random() * 6 + 3;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 15 + 20) + 's';
                
                particleContainer.appendChild(particle);
            }
        }

        // Initialize particles when page loads
        window.addEventListener('load', () => {
            createParticles();
        });
    </script>
</body>
</html>
