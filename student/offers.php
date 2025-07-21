<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

$success_message = '';
$error_message = '';

// Handle offer response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond_to_offer'])) {
    $offer_id = (int)$_POST['offer_id'];
    $response = $_POST['response']; // 'accepted' or 'rejected'
    $student_response = trim($_POST['student_response']);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE job_offers 
            SET status = ?, student_response = ?, responded_at = NOW() 
            WHERE id = ? AND student_id = ?
        ");
        $stmt->execute([$response, $student_response, $offer_id, $student_id]);
        
        if ($response === 'accepted') {
            $success_message = "🎉 Offer accepted successfully! The startup has been notified.";
        } else {
            $success_message = "✅ Offer declined. Thank you for your response.";
        }
    } catch (PDOException $e) {
        $error_message = "❌ Error updating offer response. Please try again.";
    }
}

// Get all job offers for this student
$stmt = $pdo->prepare("
    SELECT jo.*, s.org_name, s.domain, s.email as startup_email,
           jp.title as job_post_title, jp.type as job_post_type
    FROM job_offers jo 
    JOIN startups s ON jo.startup_id = s.id 
    LEFT JOIN job_posts jp ON jo.job_id = jp.id
    WHERE jo.student_id = ? 
    ORDER BY jo.created_at DESC
");
$stmt->execute([$student_id]);
$offers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Offers - Student Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .offers-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .offer-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .offer-card.pending {
            border-left-color: #ffc107;
        }
        .offer-card.accepted {
            border-left-color: #28a745;
        }
        .offer-card.rejected {
            border-left-color: #dc3545;
        }
        .offer-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-accepted { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-withdrawn { background: #e2e3e5; color: #383d41; }
        .offer-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }
        .response-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .action-buttons .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .response-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
            border: 2px solid #007bff;
        }
        .response-form h5 {
            color: #007bff;
            margin-bottom: 15px;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            resize: vertical;
        }
        .deadline-warning {
            color: #dc3545;
            font-weight: bold;
        }
        .deadline-soon {
            color: #ffc107;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1><a href="../index.php" style="color: white; text-decoration: none;">Skill to Startup Matcher</a></h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="browse_startups.php">Browse Startups</a></li>
                        <li><a href="browse_jobs.php">Browse Jobs</a></li>
                        <li><a href="my_applications.php">My Applications</a></li>
                        <li><a href="offers.php" class="active">Job Offers</a></li>
                        <li><a href="profile.php">👤 Profile</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <div class="offers-container">
                <h2>💼 Job Offers</h2>
                <p>View and respond to job offers from startups</p>

                <!-- Success/Error Messages -->
                <?php if ($success_message): ?>
                    <div style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-bar">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($offers); ?></div>
                        <div>Total Offers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($offers, fn($o) => $o['status'] === 'pending')); ?></div>
                        <div>Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($offers, fn($o) => $o['status'] === 'accepted')); ?></div>
                        <div>Accepted</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($offers, fn($o) => $o['deadline'] && strtotime($o['deadline']) > time() && $o['status'] === 'pending')); ?></div>
                        <div>Active</div>
                    </div>
                </div>

                <?php if (empty($offers)): ?>
                    <div class="offer-card">
                        <h4>No Job Offers Yet</h4>
                        <p>You haven't received any job offers yet. Keep applying and building your profile!</p>
                        <a href="browse_jobs.php" class="btn btn-primary">🔍 Browse Jobs</a>
                        <a href="browse_startups.php" class="btn btn-secondary">🚀 Browse Startups</a>
                    </div>
                <?php else: ?>

                <?php foreach ($offers as $offer): ?>
                    <?php
                    // Check deadline status
                    $deadline_status = '';
                    if ($offer['deadline']) {
                        $days_left = ceil((strtotime($offer['deadline']) - time()) / (60 * 60 * 24));
                        if ($days_left < 0 && $offer['status'] === 'pending') {
                            $deadline_status = 'expired';
                        } elseif ($days_left <= 3 && $offer['status'] === 'pending') {
                            $deadline_status = 'urgent';
                        } elseif ($days_left <= 7 && $offer['status'] === 'pending') {
                            $deadline_status = 'soon';
                        }
                    }
                    ?>
                    
                    <div class="offer-card <?php echo $offer['status']; ?>">
                        <div class="offer-header">
                            <div>
                                <h4>💼 <?php echo htmlspecialchars($offer['title']); ?></h4>
                                <h5 style="color: #007bff; margin: 5px 0;">
                                    🏢 <?php echo htmlspecialchars($offer['org_name']); ?>
                                </h5>
                                <p style="color: #666; margin: 5px 0;">
                                    <?php echo htmlspecialchars($offer['domain']); ?> • 
                                    <?php echo ucfirst($offer['offer_type']); ?>
                                    <?php if ($offer['location']): ?>
                                        • <?php echo htmlspecialchars($offer['location']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <span class="status-badge status-<?php echo $offer['status']; ?>">
                                    <?php echo ucfirst($offer['status']); ?>
                                </span>
                                <?php if ($deadline_status === 'expired'): ?>
                                    <div class="deadline-warning" style="margin-top: 5px; font-size: 0.8em;">
                                        ⏰ EXPIRED
                                    </div>
                                <?php elseif ($deadline_status === 'urgent'): ?>
                                    <div class="deadline-warning" style="margin-top: 5px; font-size: 0.8em;">
                                        🚨 <?php echo $days_left; ?> days left
                                    </div>
                                <?php elseif ($deadline_status === 'soon'): ?>
                                    <div class="deadline-soon" style="margin-top: 5px; font-size: 0.8em;">
                                        ⏳ <?php echo $days_left; ?> days left
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="offer-meta">
                            <div>
                                <strong>📅 Received:</strong> <?php echo date('M j, Y g:i A', strtotime($offer['created_at'])); ?>
                            </div>
                            <div>
                                <strong>📧 Contact:</strong> <?php echo htmlspecialchars($offer['startup_email']); ?>
                            </div>
                            <?php if ($offer['salary_range']): ?>
                                <div>
                                    <strong>💰 Salary:</strong> <?php echo htmlspecialchars($offer['salary_range']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($offer['deadline']): ?>
                                <div>
                                    <strong>⏰ Deadline:</strong> <?php echo date('M j, Y', strtotime($offer['deadline'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Job Description -->
                        <div style="margin: 15px 0;">
                            <strong>📝 Description:</strong>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 5px; border-left: 3px solid #007bff;">
                                <?php echo nl2br(htmlspecialchars($offer['description'])); ?>
                            </div>
                        </div>

                        <!-- Requirements -->
                        <?php if ($offer['requirements']): ?>
                            <div style="margin: 15px 0;">
                                <strong>📋 Requirements:</strong>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 5px;">
                                    <?php echo nl2br(htmlspecialchars($offer['requirements'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Benefits -->
                        <?php if ($offer['benefits']): ?>
                            <div style="margin: 15px 0;">
                                <strong>🎁 Benefits & Perks:</strong>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 5px;">
                                    <?php echo nl2br(htmlspecialchars($offer['benefits'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Student Response -->
                        <?php if ($offer['student_response']): ?>
                            <div style="margin: 15px 0;">
                                <strong>💬 Your Response:</strong>
                                <div style="background: #e9ecef; padding: 15px; border-radius: 8px; margin-top: 5px;">
                                    <?php echo nl2br(htmlspecialchars($offer['student_response'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Response Form (for pending offers) -->
                        <?php if ($offer['status'] === 'pending' && $deadline_status !== 'expired'): ?>
                            <div class="response-form">
                                <h5>📋 Respond to this Offer</h5>
                                <form method="POST" action="">
                                    <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                    
                                    <div style="margin-bottom: 15px;">
                                        <label for="student_response_<?php echo $offer['id']; ?>">Your Message (Optional):</label>
                                        <textarea id="student_response_<?php echo $offer['id']; ?>" name="student_response" 
                                                  class="form-control" rows="3" 
                                                  placeholder="Add a personal message or ask questions..."></textarea>
                                    </div>

                                    <div class="action-buttons">
                                        <button type="submit" name="respond_to_offer" value="offer_<?php echo $offer['id']; ?>" 
                                                onclick="this.form.response.value='accepted'" class="btn btn-success">
                                            ✅ Accept Offer
                                        </button>
                                        <button type="submit" name="respond_to_offer" value="offer_<?php echo $offer['id']; ?>" 
                                                onclick="this.form.response.value='rejected'" class="btn btn-danger">
                                            ❌ Decline Offer
                                        </button>
                                        <a href="mailto:<?php echo htmlspecialchars($offer['startup_email']); ?>" class="btn btn-secondary">
                                            📧 Contact Startup
                                        </a>
                                        <input type="hidden" name="response" value="">
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Action buttons for responded offers -->
                            <div class="action-buttons">
                                <a href="mailto:<?php echo htmlspecialchars($offer['startup_email']); ?>" class="btn btn-secondary">
                                    📧 Contact Startup
                                </a>
                                <?php if ($offer['status'] === 'accepted'): ?>
                                    <span class="btn btn-sm btn-success" style="cursor: default;">
                                        🎉 Offer Accepted!
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php endif; ?>

                <!-- Quick Actions -->
                <div style="text-align: center; margin-top: 30px;">
                    <a href="browse_jobs.php" class="btn btn-primary">🔍 Browse More Jobs</a>
                    <a href="dashboard.php" class="btn btn-secondary">📊 Back to Dashboard</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
