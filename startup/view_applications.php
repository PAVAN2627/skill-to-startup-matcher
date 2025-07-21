<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/application_mailer.php';

// Check if user is logged in as startup
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'startup') {
    header('Location: ../login.php');
    exit();
}

$startup_id = $_SESSION['user_id'];

// Get all applications for this startup
$stmt = $pdo->prepare("
    SELECT a.*, s.name as student_name, s.email as student_email, s.skills, s.college, s.contact,
           jp.title as job_title, jp.type as job_type
    FROM applications a 
    JOIN students s ON a.student_id = s.id 
    LEFT JOIN job_posts jp ON a.job_id = jp.id
    WHERE a.startup_id = ? 
    ORDER BY a.applied_at DESC
");
$stmt->execute([$startup_id]);
$applications = $stmt->fetchAll();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $application_id = (int)$_POST['application_id'];
    $new_status = $_POST['status'];
    $notes = sanitizeInput($_POST['notes']);
    
    // Get application details before updating
    $app_details_stmt = $pdo->prepare("
        SELECT a.*, s.name as student_name, s.email as student_email, 
               su.org_name as startup_name, jp.title as job_title
        FROM applications a 
        JOIN students s ON a.student_id = s.id 
        JOIN startups su ON a.startup_id = su.id
        LEFT JOIN job_posts jp ON a.job_id = jp.id
        WHERE a.id = ? AND a.startup_id = ?
    ");
    $app_details_stmt->execute([$application_id, $startup_id]);
    $app_details = $app_details_stmt->fetch();
    
    if ($app_details) {
        // Update the application status
        $update_stmt = $pdo->prepare("
            UPDATE applications 
            SET status = ?, notes = ?, reviewed_at = NOW() 
            WHERE id = ? AND startup_id = ?
        ");
        $update_result = $update_stmt->execute([$new_status, $notes, $application_id, $startup_id]);
        
        if ($update_result) {
            // Send email notification for the status change
            try {
                $email_sent = sendApplicationUpdateEmail(
                    $app_details['student_email'],
                    $app_details['student_name'],
                    $app_details['startup_name'],
                    $app_details['job_title'],
                    $new_status,
                    $notes
                );
                
                if ($email_sent) {
                    $_SESSION['success_message'] = "Application status updated and email notification sent to student!";
                } else {
                    $_SESSION['warning_message'] = "Application status updated, but email notification failed to send.";
                }
            } catch (Exception $e) {
                error_log("Email notification error: " . $e->getMessage());
                $_SESSION['warning_message'] = "Application status updated, but email notification encountered an error.";
            }
        } else {
            $_SESSION['error_message'] = "Failed to update application status.";
        }
    } else {
        $_SESSION['error_message'] = "Application not found.";
    }
    
    header('Location: view_applications.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Applications - Startup Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .applications-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .application-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .application-card.pending {
            border-left-color: #ffc107;
        }
        .application-card.accepted {
            border-left-color: #28a745;
        }
        .application-card.rejected {
            border-left-color: #dc3545;
        }
        .application-card.interviewed {
            border-left-color: #17a2b8;
        }
        .student-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .resume-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .status-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
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
        .status-interviewed { background: #d1ecf1; color: #0c5460; }
        .cover-letter {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 3px solid #007bff;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9em;
        }
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #ddd;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .filter-tab.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
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
                        <li><a href="post_opportunity.php">Post Job/Opportunity</a></li>
                        <li><a href="manage_jobs.php">Manage Posts</a></li>
                        <li><a href="view_applications.php" class="active">View Applications</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745;">
                    ✅ <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['warning_message'])): ?>
                <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                    ⚠️ <?php echo htmlspecialchars($_SESSION['warning_message']); ?>
                </div>
                <?php unset($_SESSION['warning_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc3545;">
                    ❌ <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <div class="applications-container">
                <h2>📋 Student Applications</h2>
                <p>Review applications from students interested in your startup</p>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <div class="filter-tab active" onclick="filterApplications('all')">All (<?php echo count($applications); ?>)</div>
                    <div class="filter-tab" onclick="filterApplications('pending')">Pending</div>
                    <div class="filter-tab" onclick="filterApplications('interviewed')">Interviewed</div>
                    <div class="filter-tab" onclick="filterApplications('accepted')">Accepted</div>
                    <div class="filter-tab" onclick="filterApplications('rejected')">Rejected</div>
                </div>

                <?php if (empty($applications)): ?>
                    <div class="application-card">
                        <h4>No Applications Yet</h4>
                        <p>Students haven't applied to your startup yet. Try:</p>
                        <ul>
                            <li><a href="post_job.php">Post job opportunities</a> to attract students</li>
                            <li>Update your startup profile with more details</li>
                            <li>Ensure your startup is approved by admin</li>
                        </ul>
                    </div>
                <?php else: ?>

                <?php foreach ($applications as $app): ?>
                    <div class="application-card <?php echo $app['status']; ?>" data-status="<?php echo $app['status']; ?>">
                        <div class="application-header">
                            <div>
                                <h4>👤 <?php echo htmlspecialchars($app['student_name']); ?></h4>
                                <?php if ($app['job_title']): ?>
                                    <p style="color: #007bff; font-weight: bold;">
                                        Applied for: <?php echo htmlspecialchars($app['job_title']); ?> 
                                        (<?php echo ucfirst($app['job_type']); ?>)
                                    </p>
                                <?php endif; ?>
                                <p style="color: #666;">
                                    <?php echo htmlspecialchars($app['college']); ?> • <?php echo ucfirst($app['application_type']); ?>
                                </p>
                            </div>
                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                <?php echo ucfirst($app['status']); ?>
                            </span>
                        </div>

                        <div class="application-details">
                            <div>
                                <p><strong>📧 Email:</strong> <?php echo htmlspecialchars($app['student_email']); ?></p>
                                <p><strong>📞 Contact:</strong> <?php echo htmlspecialchars($app['contact']); ?></p>
                            </div>
                            <div>
                                <p><strong>💼 Application Type:</strong> <?php echo ucfirst($app['application_type']); ?></p>
                                <p><strong>📅 Applied:</strong> <?php echo date('M j, Y g:i A', strtotime($app['applied_at'])); ?></p>
                                <p><strong>🛠️ Skills:</strong> <?php echo htmlspecialchars($app['skills']); ?></p>
                            </div>
                        </div>

                        <!-- All Documents and Links Section -->
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 15px 0;">
                            <h5 style="margin-bottom: 15px;">📁 Documents & Profiles</h5>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                                
                                <!-- Resume -->
                                <?php if ($app['resume_path']): ?>
                                    <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
                                        <div style="font-size: 2em; margin-bottom: 10px;">📄</div>
                                        <strong>Resume</strong>
                                        <div style="margin-top: 10px;">
                                            <a href="<?php echo htmlspecialchars($app['resume_path']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                📥 Download
                                            </a>
                                        </div>
                                        <small style="color: #666; display: block; margin-top: 5px;">
                                            <?php echo basename($app['resume_path']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- GitHub Profile -->
                                <?php if ($app['github_profile']): ?>
                                    <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
                                        <div style="font-size: 2em; margin-bottom: 10px;">💻</div>
                                        <strong>GitHub Profile</strong>
                                        <div style="margin-top: 10px;">
                                            <a href="<?php echo htmlspecialchars($app['github_profile']); ?>" target="_blank" class="btn btn-sm" style="background: #333; color: white;">
                                                🔗 View GitHub
                                            </a>
                                        </div>
                                        <small style="color: #666; display: block; margin-top: 5px;">
                                            Coding projects & contributions
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- LinkedIn Profile -->
                                <?php if ($app['linkedin_profile']): ?>
                                    <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
                                        <div style="font-size: 2em; margin-bottom: 10px;">👔</div>
                                        <strong>LinkedIn Profile</strong>
                                        <div style="margin-top: 10px;">
                                            <a href="<?php echo htmlspecialchars($app['linkedin_profile']); ?>" target="_blank" class="btn btn-sm" style="background: #0077b5; color: white;">
                                                🔗 View LinkedIn
                                            </a>
                                        </div>
                                        <small style="color: #666; display: block; margin-top: 5px;">
                                            Professional network
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>                        <!-- Cover Letter -->
                        <?php if ($app['cover_letter']): ?>
                            <div class="cover-letter">
                                <h5>💌 Cover Letter</h5>
                                <p><?php echo nl2br(htmlspecialchars($app['cover_letter'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Status Update Section -->
                        <div class="status-section">
                            <h5>Update Application Status 
                                <span style="background: #e3f2fd; color: #1976d2; padding: 3px 8px; border-radius: 12px; font-size: 0.8em; margin-left: 10px;">
                                    📧 Auto-Email Notification
                                </span>
                            </h5>
                            <p style="font-size: 0.9em; color: #666; margin-bottom: 15px;">
                                💡 Student will automatically receive an email notification when status is updated
                            </p>
                            <form method="POST" style="display: flex; gap: 15px; align-items: end;">
                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                <input type="hidden" name="update_status" value="1">
                                
                                <div style="flex: 1;">
                                    <label>Status:</label>
                                    <select name="status" class="form-control" required onchange="updateEmailPreview(this.value)">
                                        <option value="pending" <?php echo $app['status'] === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                                        <option value="interviewed" <?php echo $app['status'] === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                                        <option value="accepted" <?php echo $app['status'] === 'accepted' ? 'selected' : ''; ?>>✅ Accepted</option>
                                        <option value="rejected" <?php echo $app['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                                
                                <div style="flex: 2;">
                                    <label>Notes (optional - included in email):</label>
                                    <textarea name="notes" class="form-control" placeholder="Add notes about this application... (This message will be included in the email to the student)" style="height: 60px;"><?php echo htmlspecialchars($app['notes']); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-sm" style="height: fit-content;">
                                    📧 Update & Notify
                                </button>
                            </form>
                            
                            <!-- Email Preview -->
                            <div id="email-preview" style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px; font-size: 0.9em; color: #666;">
                                📧 Email will be sent to: <strong><?php echo htmlspecialchars($app['student_email']); ?></strong>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="action-buttons">
                            <a href="mailto:<?php echo htmlspecialchars($app['student_email']); ?>" class="btn btn-sm btn-secondary">
                                📧 Email Student
                            </a>
                            <?php if ($app['contact']): ?>
                                <a href="tel:<?php echo htmlspecialchars($app['contact']); ?>" class="btn btn-sm btn-secondary">
                                    📞 Call Student
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function filterApplications(status) {
            const cards = document.querySelectorAll('.application-card');
            const tabs = document.querySelectorAll('.filter-tab');
            
            // Update active tab
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Show/hide cards
            cards.forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        function updateEmailPreview(status) {
            const preview = document.getElementById('email-preview');
            if (!preview) return;
            
            let message = '';
            switch(status) {
                case 'accepted':
                    message = '🎉 <strong>Acceptance Email</strong> - Congratulations message with next steps';
                    break;
                case 'interviewed':
                    message = '📞 <strong>Interview Email</strong> - Interview preparation details';
                    break;
                case 'rejected':
                    message = '📝 <strong>Update Email</strong> - Polite update with encouragement';
                    break;
                default:
                    message = '📋 <strong>Status Update Email</strong> - General status notification';
            }
            
            const emailSpan = preview.querySelector('strong');
            const studentEmail = emailSpan ? emailSpan.textContent : 'student';
            preview.innerHTML = `📧 ${message} will be sent to: <strong>${studentEmail}</strong>`;
        }
    </script>
</body>
</html>
