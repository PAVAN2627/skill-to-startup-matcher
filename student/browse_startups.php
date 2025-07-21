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

// Get all approved startups with application status
$stmt = $pdo->prepare("
    SELECT s.*, 
           COUNT(a.id) as application_count,
           MAX(CASE WHEN a.student_id = ? THEN 1 ELSE 0 END) as already_applied
    FROM startups s 
    LEFT JOIN applications a ON s.id = a.startup_id
    WHERE s.is_verified = 1 AND s.is_approved = 1
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$stmt->execute([$student_id]);
$startups = $stmt->fetchAll();

// Get recent job posts with application status
$jobs_stmt = $pdo->prepare("
    SELECT jp.*, s.org_name as startup_name,
           MAX(CASE WHEN a.student_id = ? THEN a.status ELSE NULL END) as application_status,
           MAX(CASE WHEN a.student_id = ? THEN 1 ELSE 0 END) as already_applied
    FROM job_posts jp 
    JOIN startups s ON jp.startup_id = s.id
    LEFT JOIN applications a ON jp.id = a.job_id AND a.student_id = ?
    WHERE jp.status = 'active' AND s.is_verified = 1 AND s.is_approved = 1
    AND (jp.deadline IS NULL OR jp.deadline >= CURDATE())
    GROUP BY jp.id
    ORDER BY jp.created_at DESC
    LIMIT 20
");
$jobs_stmt->execute([$student_id, $student_id, $student_id]);
$recent_jobs = $jobs_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Startups - Student Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .browse-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .startup-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
            transition: transform 0.3s ease;
        }
        .startup-card:hover {
            transform: translateY(-2px);
        }
        .startup-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .startup-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .startup-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .job-section {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .job-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 3px solid #2196f3;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            border: 2px solid #ddd;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .tab.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .applied-badge {
            background: #28a745;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-accepted {
            background: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid transparent;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
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
                        <li><a href="browse_startups.php" class="active">Browse Startups</a></li>
                        <li><a href="browse_jobs.php">Browse Jobs</a></li>
                        <li><a href="my_applications.php">My Applications</a></li>
                        <li><a href="offers.php">Job Offers</a></li>
                        <li><a href="profile.php">👤 Profile</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container browse-container" style="margin-top: 20px;">
            <h2>🚀 Discover Startups & Opportunities</h2>
            <p style="color: #666; margin-bottom: 20px;">Find exciting startups and apply for jobs, hackathons, and events</p>
            
            <!-- Error/Success Messages -->
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <?php if ($_GET['error'] === 'already_applied'): ?>
                        ⚠️ You have already applied for this opportunity. 
                        <?php if (isset($_GET['status'])): ?>
                            Current status: <strong><?php echo ucfirst(htmlspecialchars($_GET['status'])); ?></strong>
                        <?php endif; ?>
                    <?php elseif ($_GET['error'] === 'job_not_found'): ?>
                        ❌ The requested opportunity was not found or is no longer available.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="showSection('startups')">Browse Startups</div>
                <div class="tab" onclick="showSection('jobs')">Jobs & Opportunities</div>
            </div>

            <!-- Startups Section -->
            <div id="startups-section">
                <div class="startups-grid">
                    <?php if (empty($startups)): ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <p>No approved startups found.</p>
                            <p><small>Check back later for new startups.</small></p>
                        </div>
                    <?php else: ?>

                    <?php foreach ($startups as $startup): ?>
                        <div class="startup-card">
                            <div class="startup-header">
                                <div>
                                    <h4><?php echo htmlspecialchars($startup['org_name']); ?></h4>
                                    <p style="color: #666; margin: 5px 0;">
                                        <?php echo htmlspecialchars($startup['domain'] ?: 'General'); ?> • 
                                        Startup
                                        <?php if ($startup['already_applied']): ?>
                                            <span class="applied-badge">✓ Applied</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div>
                                    <?php if ($startup['already_applied']): ?>
                                        <button class="btn btn-secondary" disabled>Already Applied</button>
                                    <?php else: ?>
                                        <a href="apply_startup.php?startup_id=<?php echo $startup['id']; ?>" class="btn btn-primary">
                                            🚀 Apply Now
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="startup-meta">
                                <div class="startup-meta-item">
                                    <strong>🏢 Domain:</strong> <?php echo htmlspecialchars($startup['domain'] ?: 'Not specified'); ?>
                                </div>
                                <div class="startup-meta-item">
                                    <strong>📧 Contact:</strong> <?php echo htmlspecialchars($startup['email']); ?>
                                </div>
                                <div class="startup-meta-item">
                                    <strong>📊 Applications:</strong> <?php echo $startup['application_count']; ?> students applied
                                </div>
                                <div class="startup-meta-item">
                                    <strong>✅ Status:</strong> 
                                    <span style="color: #28a745;">Verified & Approved</span>
                                </div>
                            </div>

                            <div>
                                <strong>About:</strong>
                                <p style="margin: 10px 0;"><?php echo nl2br(htmlspecialchars($startup['description'] ?: 'No description provided')); ?></p>
                            </div>

                            <div>
                                <strong>Business Domain:</strong>
                                <p style="margin: 10px 0; color: #007bff; font-weight: 500;"><?php echo htmlspecialchars($startup['domain'] ?: 'General'); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php endif; ?>
                </div>
            </div>

            <!-- Jobs Section -->
            <div id="jobs-section" style="display: none;">
                <div class="job-section">
                    <h3>🆕 Job Opportunities & Events</h3>
                    <?php if (empty($recent_jobs)): ?>
                        <div style="text-align: center; padding: 20px; color: #666;">
                            <p>No job opportunities found.</p>
                            <p><small>Check back later for new opportunities.</small></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_jobs as $job): ?>
                            <div class="job-item">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h4><?php echo htmlspecialchars($job['title']); ?></h4>
                                        <p><strong>Company:</strong> <?php echo htmlspecialchars($job['startup_name']); ?></p>
                                        <p><strong>Type:</strong> 
                                            <?php 
                                            $type_icons = [
                                                'job' => '💼',
                                                'hackathon' => '🚀',
                                                'event' => '🎯',
                                                'workshop' => '📚'
                                            ];
                                            echo ($type_icons[$job['type']] ?? '💼') . ' ' . ucfirst($job['type']); 
                                            ?>
                                        </p>
                                        <?php if ($job['location']): ?>
                                            <p><strong>📍 Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($job['deadline']): ?>
                                            <p><strong>⏰ Deadline:</strong> <?php echo date('M d, Y', strtotime($job['deadline'])); ?></p>
                                        <?php endif; ?>
                                        <small>Posted: <?php echo date('M d, Y', strtotime($job['created_at'])); ?></small>
                                        
                                        <!-- Application Status -->
                                        <?php if ($job['already_applied']): ?>
                                            <div style="margin-top: 10px;">
                                                <span class="status-badge status-<?php echo $job['application_status']; ?>">
                                                    Application Status: <?php echo ucfirst($job['application_status']); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if ($job['already_applied']): ?>
                                            <button class="btn btn-secondary" disabled>
                                                Already Applied (<?php echo ucfirst($job['application_status']); ?>)
                                            </button>
                                        <?php else: ?>
                                            <a href="apply_job.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary">
                                                Apply Now
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="margin-top: 10px;">
                                    <p><?php echo nl2br(htmlspecialchars(substr($job['description'], 0, 300))); ?>
                                    <?php if (strlen($job['description']) > 300): ?>...<?php endif; ?></p>
                                </div>
                                
                                <?php if ($job['requirements']): ?>
                                    <div style="margin-top: 10px;">
                                        <strong>Requirements:</strong>
                                        <p style="margin: 5px 0; color: #666;"><?php echo htmlspecialchars($job['requirements']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Skill2Startup. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function showSection(section) {
            // Hide all sections
            document.getElementById('startups-section').style.display = 'none';
            document.getElementById('jobs-section').style.display = 'none';
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            
            // Show selected section and activate tab
            if (section === 'startups') {
                document.getElementById('startups-section').style.display = 'block';
                document.querySelectorAll('.tab')[0].classList.add('active');
            } else if (section === 'jobs') {
                document.getElementById('jobs-section').style.display = 'block';
                document.querySelectorAll('.tab')[1].classList.add('active');
            }
        }
    </script>
</body>
</html>
