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

// Get all job posts for this startup
$stmt = $pdo->prepare("
    SELECT jp.*, 
           COUNT(a.id) as application_count
    FROM job_posts jp 
    LEFT JOIN applications a ON jp.startup_id = a.startup_id
    WHERE jp.startup_id = ? 
    GROUP BY jp.id
    ORDER BY jp.created_at DESC
");
$stmt->execute([$startup_id]);
$job_posts = $stmt->fetchAll();

// Handle job status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job_status'])) {
    $job_id = (int)$_POST['job_id'];
    $new_status = $_POST['status'];
    
    try {
        $update_stmt = $pdo->prepare("UPDATE job_posts SET status = ? WHERE id = ? AND startup_id = ?");
        $result = $update_stmt->execute([$new_status, $job_id, $startup_id]);
        
        if ($result && $update_stmt->rowCount() > 0) {
            $_SESSION['success_message'] = "Status updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update status.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating status: " . $e->getMessage();
    }
    
    header('Location: manage_jobs.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - Startup Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .jobs-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .job-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .job-card.active {
            border-left-color: #28a745;
        }
        .job-card.closed {
            border-left-color: #dc3545;
            opacity: 0.8;
        }
        .job-card.draft {
            border-left-color: #ffc107;
        }
        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .job-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .job-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-closed { background: #f8d7da; color: #721c24; }
        .status-draft { background: #fff3cd; color: #856404; }
        .job-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
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
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9em;
        }
        .type-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            display: inline-block;
        }
        .type-job { background: #d4edda; color: #155724; }
        .type-internship { background: #d1ecf1; color: #0c5460; }
        .type-hackathon { background: #ffeaa7; color: #856404; }
        .type-workshop { background: #e2e3f3; color: #383d41; }
        .type-event { background: #f8d7da; color: #721c24; }
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
                        <li><a href="post_job.php">Post Job</a></li>
                        <li><a href="view_applications.php">Applications</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <div class="jobs-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <h2>📋 Manage Posts</h2>
                    <div style="display: flex; gap: 10px;">
                        <a href="post_job.php?type=job" class="btn btn-success">💼 Post Job</a>
                        <a href="post_job.php?type=internship" class="btn" style="background: #20c997; color: white;">🎓 Post Internship</a>
                        <a href="post_job.php?type=hackathon" class="btn" style="background: #fd7e14; color: white;">🚀 Create Hackathon</a>
                        <a href="post_job.php?type=workshop" class="btn" style="background: #6f42c1; color: white;">🎯 Host Workshop</a>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <?php unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-bar">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($job_posts); ?></div>
                        <div>Total Posts</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($job_posts, fn($j) => $j['status'] === 'active')); ?></div>
                        <div>Active Posts</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo array_sum(array_column($job_posts, 'application_count')); ?></div>
                        <div>Total Applications</div>
                    </div>
                </div>

                <?php if (empty($job_posts)): ?>
                    <div class="job-card">
                        <h4>No Job Posts Yet</h4>
                        <p>Start attracting students by posting your first opportunity!</p>
                        <a href="post_job.php" class="btn btn-primary">🚀 Create Your First Post</a>
                    </div>
                <?php else: ?>

                <?php foreach ($job_posts as $job): ?>
                    <div class="job-card <?php echo $job['status']; ?>">
                        <div class="job-header">
                            <div>
                                <h4><?php echo htmlspecialchars($job['title']); ?></h4>
                                <p style="color: #666; margin: 5px 0;">
                                    <span class="type-badge type-<?php echo $job['type']; ?>">
                                        <?php 
                                        $type_icons = [
                                            'job' => '💼',
                                            'internship' => '🎓',
                                            'hackathon' => '🚀',
                                            'workshop' => '🎯',
                                            'event' => '📅'
                                        ];
                                        echo $type_icons[$job['type']] ?? '📝';
                                        ?> <?php echo ucfirst($job['type']); ?>
                                    </span> • 
                                    Posted <?php echo date('M j, Y', strtotime($job['created_at'])); ?>
                                </p>
                            </div>
                            <span class="status-badge status-<?php echo $job['status']; ?>">
                                <?php echo ucfirst($job['status']); ?>
                            </span>
                        </div>

                        <div class="job-meta">
                            <div class="job-meta-item">
                                <strong>📍 Location:</strong> <?php echo htmlspecialchars($job['location'] ?: 'Not specified'); ?>
                            </div>
                            <div class="job-meta-item">
                                <strong>💰 Compensation:</strong> <?php echo htmlspecialchars($job['salary'] ?: 'Not specified'); ?>
                            </div>
                            <div class="job-meta-item">
                                <strong>⏱️ Duration:</strong> <?php echo htmlspecialchars($job['duration'] ?: 'Not specified'); ?>
                            </div>
                            <div class="job-meta-item">
                                <strong>📅 Deadline:</strong> 
                                <?php if ($job['deadline'] && $job['deadline'] !== '0000-00-00'): ?>
                                    <?php echo date('M j, Y', strtotime($job['deadline'])); ?>
                                    <?php if (strtotime($job['deadline']) < time()): ?>
                                        <span style="color: #dc3545; font-weight: bold;">(Expired)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    No deadline
                                <?php endif; ?>
                            </div>
                            <div class="job-meta-item">
                                <strong>👥 Applications:</strong> <?php echo $job['application_count']; ?>
                            </div>
                        </div>

                        <div>
                            <strong>Description:</strong>
                            <p style="margin: 10px 0;"><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                        </div>

                        <div>
                            <strong>Requirements:</strong>
                            <p style="margin: 10px 0;"><?php echo nl2br(htmlspecialchars($job['requirements'] ?: 'None specified')); ?></p>
                        </div>

                        <!-- Status Update -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                            <form method="POST" style="display: flex; gap: 15px; align-items: end;">
                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                <input type="hidden" name="update_job_status" value="1">
                                
                                <div>
                                    <label>Status:</label>
                                    <select name="status" class="form-control" required>
                                        <option value="active" <?php echo $job['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="closed" <?php echo $job['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        <option value="draft" <?php echo $job['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
                            </form>
                        </div>

                        <div class="job-actions">
                            <a href="view_applications.php" class="btn btn-sm btn-primary">
                                👥 View Applications (<?php echo $job['application_count']; ?>)
                            </a>
                            <button onclick="shareJob(<?php echo $job['id']; ?>, '<?php echo htmlspecialchars($job['title']); ?>')" class="btn btn-sm btn-secondary">
                                📤 Share Job
                            </button>
                            <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-secondary">
                                ✏️ Edit
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function shareJob(jobId, jobTitle) {
            const shareUrl = window.location.origin + '/skill-to-startup-matcher/student/browse_jobs.php#job-' + jobId;
            
            if (navigator.share) {
                navigator.share({
                    title: jobTitle,
                    text: 'Check out this opportunity: ' + jobTitle,
                    url: shareUrl
                });
            } else {
                // Fallback to copying to clipboard
                navigator.clipboard.writeText(shareUrl).then(() => {
                    alert('Job link copied to clipboard!');
                });
            }
        }
    </script>
</body>
</html>
