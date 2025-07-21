<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

$error = '';
$startup = null;

// Check if user provided email parameter or is logged in
$email = '';
if (isset($_GET['email'])) {
    $email = sanitizeInput($_GET['email']);
} elseif (isset($_SESSION['user_email']) && $_SESSION['user_type'] === 'startup') {
    $email = $_SESSION['user_email'];
}

if ($email) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM startups WHERE email = ?");
        $stmt->execute([$email]);
        $startup = $stmt->fetch();
    } catch (PDOException $e) {
        $error = 'Failed to load startup status.';
        error_log($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Status - Skill2Startup</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
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
                    <h2 class="card-title text-center">Account Status</h2>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php elseif (!$startup): ?>
                    <div class="alert alert-warning">
                        <h4>Account Not Found</h4>
                        <p>We couldn't find an account with the provided information.</p>
                        <p><a href="register.php" class="btn btn-primary">Register New Account</a></p>
                    </div>
                <?php else: ?>
                    <div class="text-center mb-3">
                        <h3><?php echo htmlspecialchars($startup['org_name']); ?></h3>
                        <p class="text-muted"><?php echo htmlspecialchars($startup['email']); ?></p>
                    </div>

                    <div class="dashboard-grid mb-3">
                        <div class="stat-card">
                            <div class="stat-label">Email Status</div>
                            <div class="stat-number" style="font-size: 1.5rem;">
                                <span class="badge badge-<?php echo $startup['is_verified'] ? 'approved' : 'pending'; ?>">
                                    <?php echo $startup['is_verified'] ? 'Verified' : 'Not Verified'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Account Status</div>
                            <div class="stat-number" style="font-size: 1.5rem;">
                                <?php if ($startup['is_approved'] == 1): ?>
                                    <span class="badge badge-approved">Approved</span>
                                <?php elseif ($startup['is_approved'] == 0): ?>
                                    <span class="badge badge-pending">Pending Review</span>
                                <?php else: ?>
                                    <span class="badge badge-rejected">Rejected</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!$startup['is_verified']): ?>
                        <div class="alert alert-warning">
                            <h4>Email Verification Required</h4>
                            <p>Please check your email and verify your account before proceeding.</p>
                        </div>
                    <?php elseif ($startup['is_approved'] == 0): ?>
                        <div class="alert alert-info">
                            <h4>Account Under Review</h4>
                            <p>Thank you for registering! Your startup account is currently being reviewed by our admin team.</p>
                            <p><strong>What happens next?</strong></p>
                            <ul>
                                <li>Our team will review your submitted documents</li>
                                <li>The review process typically takes 1-3 business days</li>
                                <li>You'll receive an email notification once your account is approved</li>
                                <li>After approval, you can login and access all features</li>
                            </ul>
                        </div>
                    <?php elseif ($startup['is_approved'] == 1): ?>
                        <div class="alert alert-success">
                            <h4>Account Approved!</h4>
                            <p>Congratulations! Your startup account has been approved and is ready to use.</p>
                            <p><a href="../login.php" class="btn btn-primary">Login to Dashboard</a></p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <h4>Account Rejected</h4>
                            <p>Unfortunately, your startup account application has been rejected.</p>
                            <p>This could be due to:</p>
                            <ul>
                                <li>Incomplete or invalid documentation</li>
                                <li>Information that couldn't be verified</li>
                                <li>Non-compliance with our platform guidelines</li>
                            </ul>
                            <p>Please contact our support team for more information or to appeal this decision.</p>
                        </div>
                    <?php endif; ?>

                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-top: 2rem;">
                        <h5>Account Details</h5>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                            <div>
                                <strong>Domain:</strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($startup['domain']); ?></span>
                            </div>
                            <div>
                                <strong>Registration Date:</strong><br>
                                <span class="text-muted"><?php echo date('M d, Y', strtotime($startup['created_at'])); ?></span>
                            </div>
                        </div>
                        <div style="margin-top: 1rem;">
                            <strong>Description:</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($startup['description']); ?></span>
                        </div>
                    </div>

                    <?php if ($startup['is_verified'] && $startup['is_approved'] == 0): ?>
                        <div class="text-center mt-3">
                            <p><small>Want to check your status again? <a href="javascript:location.reload()">Refresh this page</a></small></p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Skill2Startup. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
    
    <?php if ($startup && $startup['is_verified'] && $startup['is_approved'] == 0): ?>
    <script>
        // Auto-refresh every 30 seconds if pending approval
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
    <?php endif; ?>
</body>
</html>
