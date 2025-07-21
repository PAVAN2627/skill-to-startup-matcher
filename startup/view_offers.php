<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is logged in as startup
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'startup') {
    header('Location: ../login.php');
    exit();
}

$startup_id = $_SESSION['user_id'];

// Handle offer withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_offer'])) {
    $offer_id = (int)$_POST['offer_id'];
    
    $stmt = $pdo->prepare("
        UPDATE job_offers 
        SET status = 'withdrawn', updated_at = NOW() 
        WHERE id = ? AND startup_id = ? AND status = 'pending'
    ");
    $result = $stmt->execute([$offer_id, $startup_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        $_SESSION['success_message'] = "Job offer has been successfully withdrawn.";
    } else {
        $_SESSION['error_message'] = "Failed to withdraw offer. The offer may have already been responded to.";
    }
    
    header('Location: view_offers.php');
    exit();
}

// Get all job offers sent by this startup
$stmt = $pdo->prepare("
    SELECT jo.*, st.name as student_name, st.email as student_email, st.contact as student_contact,
           st.college, st.skills, jp.title as job_post_title
    FROM job_offers jo 
    JOIN students st ON jo.student_id = st.id 
    LEFT JOIN job_posts jp ON jo.job_id = jp.id
    WHERE jo.startup_id = ? 
    ORDER BY jo.created_at DESC
");
$stmt->execute([$startup_id]);
$offers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Job Offers - Startup Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .offers-container {
            max-width: 1200px;
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
        .offer-card.withdrawn {
            border-left-color: #6c757d;
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
        .skills-display {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        .skill-tag {
            background: #007bff;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
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
                        <li><a href="browse_students.php">Browse Students</a></li>
                        <li><a href="post_opportunity.php">Post Job/Opportunity</a></li>
                        <li><a href="manage_jobs.php">Manage Posts</a></li>
                        <li><a href="view_applications.php">View Applications</a></li>
                        <li><a href="view_offers.php" class="active">Manage Offers</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <div class="offers-container">
                <h2>💼 Manage Job Offers</h2>
                <p>Track and manage job offers sent to students</p>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error_message']; ?>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-bar">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($offers); ?></div>
                        <div>Total Offers Sent</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($offers, fn($o) => $o['status'] === 'pending')); ?></div>
                        <div>Pending Response</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($offers, fn($o) => $o['status'] === 'accepted')); ?></div>
                        <div>Accepted</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($offers, fn($o) => $o['status'] === 'rejected')); ?></div>
                        <div>Declined</div>
                    </div>
                </div>

                <?php if (empty($offers)): ?>
                    <div class="offer-card">
                        <h4>No Job Offers Sent Yet</h4>
                        <p>You haven't sent any job offers yet. Start by browsing talented students!</p>
                        <a href="browse_students.php" class="btn btn-primary">👥 Browse Students</a>
                    </div>
                <?php else: ?>

                <?php foreach ($offers as $offer): ?>
                    <div class="offer-card <?php echo $offer['status']; ?>">
                        <div class="offer-header">
                            <div>
                                <h4>💼 <?php echo htmlspecialchars($offer['title']); ?></h4>
                                <h5 style="color: #007bff; margin: 5px 0;">
                                    👤 <?php echo htmlspecialchars($offer['student_name']); ?>
                                </h5>
                                <p style="color: #666; margin: 5px 0;">
                                    📧 <?php echo htmlspecialchars($offer['student_email']); ?>
                                    <?php if (isset($offer['student_contact']) && $offer['student_contact']): ?>
                                        • 📱 <?php echo htmlspecialchars($offer['student_contact']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="status-badge status-<?php echo $offer['status']; ?>">
                                <?php echo ucfirst($offer['status']); ?>
                            </span>
                        </div>

                        <div class="offer-meta">
                            <div>
                                <strong>📅 Sent:</strong> <?php echo date('M j, Y g:i A', strtotime($offer['created_at'])); ?>
                            </div>
                            <?php if ($offer['responded_at']): ?>
                                <div>
                                    <strong>💬 Responded:</strong> <?php echo date('M j, Y g:i A', strtotime($offer['responded_at'])); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong>💼 Type:</strong> <?php echo ucfirst($offer['offer_type']); ?>
                            </div>
                            <?php if ($offer['deadline']): ?>
                                <div>
                                    <strong>⏰ Deadline:</strong> <?php echo date('M j, Y', strtotime($offer['deadline'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Student Information -->
                        <div style="margin: 15px 0; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <strong>👤 Student Profile:</strong>
                            <div style="margin-top: 10px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                <?php if (isset($offer['college']) && $offer['college']): ?>
                                    <div><strong>🏫 College:</strong> <?php echo htmlspecialchars($offer['college']); ?></div>
                                <?php endif; ?>
                                <?php if (isset($offer['student_contact']) && $offer['student_contact']): ?>
                                    <div><strong>📱 Contact:</strong> <?php echo htmlspecialchars($offer['student_contact']); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($offer['skills']): ?>
                                <div style="margin-top: 10px;">
                                    <strong>💡 Skills:</strong>
                                    <div class="skills-display">
                                        <?php 
                                        $skills = explode(',', $offer['skills']);
                                        foreach ($skills as $skill): 
                                            $skill = trim($skill);
                                            if (!empty($skill)):
                                        ?>
                                            <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Offer Details -->
                        <div style="margin: 15px 0;">
                            <strong>📝 Offer Details:</strong>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 5px;">
                                <?php echo nl2br(htmlspecialchars($offer['description'])); ?>
                            </div>
                        </div>

                        <!-- Salary & Location -->
                        <?php if ($offer['salary_range'] || $offer['location']): ?>
                            <div style="margin: 15px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <?php if ($offer['salary_range']): ?>
                                    <div>
                                        <strong>💰 Salary:</strong> <?php echo htmlspecialchars($offer['salary_range']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($offer['location']): ?>
                                    <div>
                                        <strong>📍 Location:</strong> <?php echo htmlspecialchars($offer['location']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Student Response -->
                        <?php if ($offer['student_response']): ?>
                            <div style="margin: 15px 0;">
                                <strong>💬 Student's Response:</strong>
                                <div style="background: #e9ecef; padding: 15px; border-radius: 8px; margin-top: 5px; border-left: 3px solid #007bff;">
                                    <?php echo nl2br(htmlspecialchars($offer['student_response'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <a href="mailto:<?php echo htmlspecialchars($offer['student_email']); ?>" class="btn btn-secondary">
                                📧 Contact Student
                            </a>
                            
                            <?php if ($offer['status'] === 'pending'): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                    <button type="submit" name="withdraw_offer" class="btn btn-warning" 
                                            onclick="return confirm('Are you sure you want to withdraw this offer?')">
                                        🚫 Withdraw Offer
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($offer['status'] === 'accepted'): ?>
                                <span class="btn btn-success" style="cursor: default;">
                                    🎉 Offer Accepted!
                                </span>
                            <?php elseif ($offer['status'] === 'rejected'): ?>
                                <span class="btn btn-secondary" style="cursor: default;">
                                    😔 Offer Declined
                                </span>
                            <?php elseif ($offer['status'] === 'withdrawn'): ?>
                                <span class="btn btn-secondary" style="cursor: default;">
                                    🚫 Offer Withdrawn
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php endif; ?>

                <!-- Quick Actions -->
                <div style="text-align: center; margin-top: 30px;">
                    <a href="browse_students.php" class="btn btn-primary">👥 Browse More Students</a>
                    <a href="dashboard.php" class="btn btn-secondary">📊 Back to Dashboard</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
