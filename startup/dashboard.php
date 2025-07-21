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

try {
    // Get startup data
    $stmt = $pdo->prepare("SELECT * FROM startups WHERE id = ?");
    $stmt->execute([$startup_id]);
    $startup = $stmt->fetch();
    
    // Get applications for this startup
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE startup_id = ?");
    $stmt->execute([$startup_id]);
    $total_applications = $stmt->fetchColumn();
    
    // Get applications by status
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM applications WHERE startup_id = ? GROUP BY status");
    $stmt->execute([$startup_id]);
    $application_stats = [];
    while ($row = $stmt->fetch()) {
        $application_stats[$row['status']] = $row['count'];
    }
    
    // Get job posts count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_posts WHERE startup_id = ?");
    $stmt->execute([$startup_id]);
    $total_job_posts = $stmt->fetchColumn();
    
    // Get active job posts count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_posts WHERE startup_id = ? AND status = 'active'");
    $stmt->execute([$startup_id]);
    $active_job_posts = $stmt->fetchColumn();
    
    // Get recent applications
    $stmt = $pdo->prepare("
        SELECT a.*, s.name as student_name, s.email as student_email, s.skills, s.college
        FROM applications a 
        JOIN students s ON a.student_id = s.id 
        WHERE a.startup_id = ? 
        ORDER BY a.applied_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$startup_id]);
    $recent_applications = $stmt->fetchAll();
    
    // Get offers statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_offers WHERE startup_id = ?");
    $stmt->execute([$startup_id]);
    $total_offers_sent = $stmt->fetchColumn();
    
    // Get offers by status
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM job_offers WHERE startup_id = ? GROUP BY status");
    $stmt->execute([$startup_id]);
    $offer_stats = [];
    while ($row = $stmt->fetch()) {
        $offer_stats[$row['status']] = $row['count'];
    }
    
    // Get recent job posts
    $stmt = $pdo->prepare("
        SELECT jp.*, COUNT(a.id) as application_count 
        FROM job_posts jp 
        LEFT JOIN applications a ON jp.id = a.job_id 
        WHERE jp.startup_id = ? 
        GROUP BY jp.id 
        ORDER BY jp.created_at DESC 
        LIMIT 4
    ");
    $stmt->execute([$startup_id]);
    $recent_jobs = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Failed to load dashboard data.';
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Startup Dashboard - Skill to Startup Matcher</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .action-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #007bff;
        }
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .action-card.post-job {
            border-left-color: #28a745;
        }
        .action-card.hackathon {
            border-left-color: #fd7e14;
        }
        .action-card.event {
            border-left-color: #6f42c1;
        }
        .action-card.internship {
            border-left-color: #20c997;
        }
        .action-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        .section-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .application-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            transition: background-color 0.3s ease;
        }
        .application-item:hover {
            background-color: #f8f9fa;
        }
        .job-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            border-left: 3px solid #007bff;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 15px;
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
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .quick-stat {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
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
                        <li><a href="dashboard.php" class="active">Dashboard</a></li>
                        <li><a href="browse_students.php">Browse Students</a></li>
                        <li><a href="post_opportunity.php">Post Job/Opportunity</a></li>
                        <li><a href="manage_jobs.php">Manage Posts</a></li>
                        <li><a href="view_applications.php">View Applications</a></li>
                        <li><a href="view_offers.php">Manage Offers</a></li>
                        <li><a href="profile.php">🏢 Company Profile</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <h2>🚀 Welcome back, <?php echo htmlspecialchars($startup['org_name']); ?>!</h2>
            <p style="color: #666; margin-bottom: 30px;">Manage your opportunities and connect with talented students</p>
            
            <!-- Platform Statistics -->
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_applications; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $application_stats['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $application_stats['accepted'] ?? 0; ?></div>
                    <div class="stat-label">Accepted Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $active_job_posts; ?></div>
                    <div class="stat-label">Active Job Posts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_offers_sent; ?></div>
                    <div class="stat-label">Offers Sent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $offer_stats['accepted'] ?? 0; ?></div>
                    <div class="stat-label">Offers Accepted</div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="action-grid">
                <div class="action-card post-job">
                    <div class="action-icon">👥</div>
                    <h3>Browse Students</h3>
                    <p>Discover talented students and send them personalized job offers</p>
                    <a href="browse_students.php" class="btn btn-primary">Browse Students</a>
                </div>
                
                <div class="action-card post-job">
                    <div class="action-icon">💼</div>
                    <h3>Post Job Opportunities</h3>
                    <p>Create full-time and part-time job postings to attract talented students</p>
                    <a href="post_job.php?type=job" class="btn btn-success">Post Job</a>
                </div>
                
                <div class="action-card internship">
                    <div class="action-icon">🎓</div>
                    <h3>Post Internships</h3>
                    <p>Offer internship opportunities for students to gain experience</p>
                    <a href="post_job.php?type=internship" class="btn" style="background: #20c997; color: white;">Post Internship</a>
                </div>
                
                <div class="action-card hackathon">
                    <div class="action-icon">🚀</div>
                    <h3>Organize Hackathons</h3>
                    <p>Host exciting hackathons and coding competitions to discover talent</p>
                    <a href="post_job.php?type=hackathon" class="btn" style="background: #fd7e14; color: white;">Create Hackathon</a>
                </div>
                
                <div class="action-card event">
                    <div class="action-icon">🎯</div>
                    <h3>Host Events & Workshops</h3>
                    <p>Organize workshops, webinars, and networking events for students</p>
                    <a href="post_job.php?type=workshop" class="btn" style="background: #6f42c1; color: white;">Create Workshop</a>
                </div>
                
                <div class="action-card event">
                    <div class="action-icon">📅</div>
                    <h3>Host Events</h3>
                    <p>Create networking events, seminars, and other professional gatherings</p>
                    <a href="post_job.php?type=event" class="btn" style="background: #e83e8c; color: white;">Create Event</a>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Recent Applications -->
            <div class="section-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>📋 Recent Applications</h3>
                    <a href="view_applications.php" class="btn btn-primary">View All</a>
                </div>
                
                <?php if (empty($recent_applications)): ?>
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <p>No applications yet.</p>
                        <p><small>Students will be able to apply once they discover your profile.</small></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_applications as $app): ?>
                        <div class="application-item">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <h4><?php echo htmlspecialchars($app['student_name']); ?></h4>
                                    <p><strong>College:</strong> <?php echo htmlspecialchars($app['college']); ?></p>
                                    <?php if ($app['skills']): ?>
                                        <p><strong>Skills:</strong> <?php echo htmlspecialchars($app['skills']); ?></p>
                                    <?php endif; ?>
                                    <small>Applied: <?php echo date('M d, Y', strtotime($app['applied_at'])); ?></small>
                                </div>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent Job Posts -->
            <div class="section-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>💼 Recent Job Posts</h3>
                    <a href="manage_jobs.php" class="btn btn-primary">Manage All</a>
                </div>
                
                <?php if (empty($recent_jobs)): ?>
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <p>No job posts yet.</p>
                        <a href="post_job.php" class="btn btn-success">Create Your First Job Post</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_jobs as $job): ?>
                        <div class="job-item">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <h4><?php echo htmlspecialchars($job['title']); ?></h4>
                                    <p><strong>Type:</strong> <?php echo ucfirst($job['type']); ?></p>
                                    <?php if ($job['location']): ?>
                                        <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                                    <?php endif; ?>
                                    <small>Posted: <?php echo date('M d, Y', strtotime($job['created_at'])); ?></small>
                                </div>
                                <div style="text-align: right;">
                                    <span class="status-badge status-<?php echo $job['status']; ?>">
                                        <?php echo ucfirst($job['status']); ?>
                                    </span>
                                    <div style="margin-top: 5px;">
                                        <small><?php echo $job['application_count']; ?> applications</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="section-card">
                <h3>⚡ Quick Actions</h3>
                <div class="dashboard-grid">
                    <div style="text-align: center; padding: 1rem;">
                        <a href="browse_students.php" class="btn btn-primary" style="width: 100%;">👥 Browse Students</a>
                    </div>
                    <div style="text-align: center; padding: 1rem;">
                        <a href="view_applications.php" class="btn btn-success" style="width: 100%;">📋 View Applications</a>
                    </div>
                    <div style="text-align: center; padding: 1rem;">
                        <a href="post_job.php" class="btn" style="background: #fd7e14; color: white; width: 100%;">💼 Post New Job</a>
                    </div>
                    <div style="text-align: center; padding: 1rem;">
                        <a href="view_offers.php" class="btn" style="background: #6f42c1; color: white; width: 100%;">📨 Manage Offers</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Skill2Startup. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
