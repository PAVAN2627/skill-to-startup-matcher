<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/otp_mailer.php';

$error = '';
$success = '';
$step = 1; // 1: Registration form, 2: OTP verification

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == '1') {
        // Step 1: Registration
        $org_name = sanitizeInput($_POST['org_name']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $domain = sanitizeInput($_POST['domain']);
        $description = sanitizeInput($_POST['description']);
        
        // File upload handling
        $id_proof = '';
        if (isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['id_proof']['type'], $allowed_types)) {
                $error = 'Only PDF, JPG, and PNG files are allowed for ID proof.';
            } elseif ($_FILES['id_proof']['size'] > $max_size) {
                $error = 'File size must be less than 5MB.';
            } else {
                $upload_dir = '../uploads/id_proofs/';
                $file_extension = pathinfo($_FILES['id_proof']['name'], PATHINFO_EXTENSION);
                $id_proof = uniqid() . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $id_proof;
                
                if (!move_uploaded_file($_FILES['id_proof']['tmp_name'], $upload_path)) {
                    $error = 'Failed to upload ID proof. Please try again.';
                }
            }
        } else {
            $error = 'ID proof is required.';
        }
        
        // Validation
        if (!$error) {
            if (empty($org_name) || empty($email) || empty($password) || empty($domain) || empty($description)) {
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
                    $stmt = $pdo->prepare("SELECT id FROM startups WHERE email = ?");
                    $stmt->execute([$email]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Email address already registered.';
                        // Delete uploaded file if email exists
                        if ($id_proof && file_exists($upload_path)) {
                            unlink($upload_path);
                        }
                    } else {
                        // Generate OTP
                        $otp = generateOTP();
                        $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Store registration data in session temporarily
                        $_SESSION['startup_registration_data'] = [
                            'org_name' => $org_name,
                            'email' => $email,
                            'password' => $hashed_password,
                            'domain' => $domain,
                            'description' => $description,
                            'id_proof' => $id_proof,
                            'otp' => $otp,
                            'otp_expires' => $otp_expires
                        ];
                        
                        // Send OTP email
                        if (sendOTPEmail($email, $otp, $org_name)) {
                            $step = 2;
                            $success = 'Registration successful! Please check your email for the OTP to verify your account.';
                        } else {
                            $error = 'Failed to send verification email. Please try again.';
                            // Delete uploaded file if email fails
                            if ($id_proof && file_exists($upload_path)) {
                                unlink($upload_path);
                            }
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Registration failed. Please try again.';
                    error_log($e->getMessage());
                    // Delete uploaded file on error
                    if ($id_proof && file_exists($upload_path)) {
                        unlink($upload_path);
                    }
                }
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == '2') {
        // Step 2: OTP Verification
        $entered_otp = sanitizeInput($_POST['otp']);
        
        if (empty($entered_otp)) {
            $error = 'Please enter the OTP.';
            $step = 2;
        } elseif (!isset($_SESSION['startup_registration_data'])) {
            $error = 'Session expired. Please register again.';
            $step = 1;
        } else {
            $reg_data = $_SESSION['startup_registration_data'];
            
            if ($entered_otp === $reg_data['otp'] && strtotime($reg_data['otp_expires']) > time()) {
                try {
                    // Insert startup into database
                    $stmt = $pdo->prepare("INSERT INTO startups (org_name, email, password, domain, description, id_proof, is_verified, is_approved) VALUES (?, ?, ?, ?, ?, ?, 1, 0)");
                    $stmt->execute([
                        $reg_data['org_name'],
                        $reg_data['email'],
                        $reg_data['password'],
                        $reg_data['domain'],
                        $reg_data['description'],
                        $reg_data['id_proof']
                    ]);
                    
                    // Clear registration data
                    unset($_SESSION['startup_registration_data']);
                    
                    $success = 'Email verified successfully! Your startup account is now pending admin approval. You will receive an email once approved.';
                    // Redirect to status page after 5 seconds
                    header("refresh:5;url=status.php");
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
if (isset($_SESSION['startup_registration_data']) && $step == 1) {
    $step = 2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Startup Registration - Skill2Startup</title>
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
                        <?php echo $step == 1 ? 'Startup Registration' : 'Email Verification'; ?>
                    </h2>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($step == 1): ?>
                    <!-- Registration Form -->
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="step" value="1">
                        
                        <div class="form-group">
                            <label for="org_name">Organization Name:</label>
                            <input type="text" id="org_name" name="org_name" class="form-control" 
                                   value="<?php echo isset($_POST['org_name']) ? htmlspecialchars($_POST['org_name']) : ''; ?>" 
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
                            <label for="domain">Domain/Industry:</label>
                            <input type="text" id="domain" name="domain" class="form-control" 
                                   value="<?php echo isset($_POST['domain']) ? htmlspecialchars($_POST['domain']) : ''; ?>" 
                                   placeholder="e.g., FinTech, HealthTech, E-commerce" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Company Description:</label>
                            <textarea id="description" name="description" class="form-control" rows="4" 
                                      maxlength="1000" 
                                      placeholder="Describe your startup, mission, and what kind of talent you're looking for..."
                                      required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="id_proof">ID Proof Upload:</label>
                            <div class="upload-area">
                                <input type="file" id="id_proof" name="id_proof" accept=".pdf,.jpg,.jpeg,.png" required>
                                <p style="margin-top: 0.5rem; color: #666;">
                                    Upload company registration certificate, business license, or other official documents (PDF, JPG, PNG - Max 5MB)
                                </p>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <strong>Note:</strong> Your startup account will be pending admin approval after email verification. 
                            You will receive an email notification once your account is approved.
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Register Startup</button>
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
