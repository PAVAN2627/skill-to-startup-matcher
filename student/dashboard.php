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

// Initialize default values
$my_applications_count = 0;
$status_counts = [];
$available_startups_count = 0;
$pending_offers_count = 0;
$recent_jobs = [];

try {
    // Get student data
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    // Get my applications count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $my_applications_count = $stmt->fetchColumn();
    
    // Get my applications by status
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM applications WHERE student_id = ? GROUP BY status");
    $stmt->execute([$student_id]);
    $status_counts = [];
    while ($row = $stmt->fetch()) {
        $status_counts[$row['status']] = $row['count'];
    }
    
    // Get recent applications
    $stmt = $pdo->prepare("
        SELECT a.*, s.org_name, s.domain, jp.title as job_title, jp.type as job_type
        FROM applications a 
        JOIN startups s ON a.startup_id = s.id 
        LEFT JOIN job_posts jp ON a.job_id = jp.id
        WHERE a.student_id = ? 
        ORDER BY a.applied_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$student_id]);
    $recent_applications = $stmt->fetchAll();
    
    // Get available startups count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM startups WHERE is_verified = 1 AND is_approved = 1");
    $stmt->execute();
    $available_startups_count = $stmt->fetchColumn();
    
    // Get pending job offers count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_offers WHERE student_id = ? AND status = 'pending'");
    $stmt->execute([$student_id]);
    $pending_offers_count = $stmt->fetchColumn();
    
    // Get recent job posts
    $stmt = $pdo->prepare("
        SELECT jp.*, s.org_name, s.logo, s.domain as startup_domain
        FROM job_posts jp 
        JOIN startups s ON jp.startup_id = s.id 
        WHERE jp.status = 'active' AND s.is_verified = 1 AND s.is_approved = 1
        AND (jp.deadline IS NULL OR jp.deadline >= CURDATE())
        ORDER BY jp.created_at DESC 
        LIMIT 6
    ");
    $stmt->execute();
    $recent_jobs = $stmt->fetchAll();
    
    // Get recommended jobs based on student skills
    $recommended_jobs = [];
    if (!empty($student['skills'])) {
        $student_skills = explode(',', $student['skills']);
        $student_skills = array_map('trim', $student_skills);
        $student_skills = array_filter($student_skills); // Remove empty values
        
        if (!empty($student_skills)) {
            // Create skill matching query
            $skill_conditions = [];
            $params = [];
            foreach ($student_skills as $skill) {
                $skill_conditions[] = "jp.required_skills LIKE ?";
                $params[] = "%$skill%";
            }
            
            $skill_where = implode(' OR ', $skill_conditions);
            
            $stmt = $pdo->prepare("
                SELECT jp.*, s.org_name, s.logo, s.domain as startup_domain
                FROM job_posts jp 
                JOIN startups s ON jp.startup_id = s.id 
                WHERE jp.status = 'active' AND s.is_verified = 1 AND s.is_approved = 1
                AND jp.type = 'job'
                AND (jp.deadline IS NULL OR jp.deadline >= CURDATE())
                AND ($skill_where)
                ORDER BY jp.created_at DESC 
                LIMIT 4
            ");
            $stmt->execute($params);
            $recommended_jobs = $stmt->fetchAll();
        }
    }
    
    // Get jobs by type
    $jobs_by_type = [];
    foreach ($recent_jobs as $job) {
        $jobs_by_type[$job['type']][] = $job;
    }
    
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
    <title>Student Dashboard - Skill to Startup Matcher</title>
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
        .opportunity-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .opportunity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .opportunity-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .opportunity-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .opportunity-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .type-internship {
            background: #e3f2fd;
            color: #1976d2;
        }
        .type-job {
            background: #e8f5e8;
            color: #2e7d32;
        }
        .type-hackathon {
            background: #fff3e0;
            color: #f57c00;
        }
        .type-workshop {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .type-event {
            background: #fce4ec;
            color: #c2185b;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .action-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .action-card:hover {
            transform: translateY(-3px);
        }
        .action-card h3 {
            margin-bottom: 10px;
            font-size: 1.3em;
        }
        .action-card.jobs {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .action-card.hackathons {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .action-card.internships {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .action-card.startups {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .recommended-job {
            background: linear-gradient(145deg, #fff 0%, #f8f9ff 100%);
            border: 2px solid #667eea !important;
            position: relative;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
        }
        .recommended-job:hover {
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
            transform: translateY(-3px);
        }
        .recommended-job .opportunity-type {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
                        <li><a href="browse_startups.php">Browse Startups</a></li>
                        <li><a href="browse_jobs.php">Browse Jobs</a></li>
                        <li><a href="my_applications.php">My Applications</a></li>
                        <li><a href="offers.php">Job Offers <?php if ($pending_offers_count > 0): ?><span style="background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.8em; margin-left: 5px;"><?php echo $pending_offers_count; ?></span><?php endif; ?></a></li>
                        <li><a href="profile.php">👤 Profile</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <h2>👋 Welcome back, <?php echo htmlspecialchars($student['name']); ?>!</h2>
            <p style="color: #666; margin-bottom: 30px;">Explore opportunities and grow your career</p>
            
            <!-- Pending Offers Alert -->
            <?php if ($pending_offers_count > 0): ?>
                <div style="background: linear-gradient(135deg, #ff6b6b, #feca57); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3); animation: pulse 2s infinite;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="font-size: 2.5em;">🎉</div>
                        <div>
                            <h3 style="margin: 0; font-size: 1.4em;">🚨 You have <?php echo $pending_offers_count; ?> pending job offer<?php echo $pending_offers_count > 1 ? 's' : ''; ?>!</h3>
                            <p style="margin: 5px 0; opacity: 0.9;">Startups are interested in you! Review and respond to your offers.</p>
                            <a href="offers.php" style="background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; font-weight: bold; border: 2px solid rgba(255,255,255,0.3); transition: all 0.3s ease;">
                                📋 View Offers →
                            </a>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes pulse {
                        0% { transform: scale(1); }
                        50% { transform: scale(1.02); }
                        100% { transform: scale(1); }
                    }
                </style>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $my_applications_count; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $status_counts['accepted'] ?? 0; ?></div>
                    <div class="stat-label">Accepted</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $status_counts['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pending_offers_count; ?></div>
                    <div class="stat-label">Job Offers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $available_startups_count; ?></div>
                    <div class="stat-label">Available Startups</div>
                </div>
            </div>

            <!-- My Applications Section -->
            <div class="opportunity-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>📋 My Applications</h3>
                    <a href="my_applications.php" class="btn btn-primary">View All Applications</a>
                </div>
                
                <?php if (empty($recent_applications)): ?>
                    <div style="text-align: center; padding: 30px; background: #f8f9fa; border-radius: 10px; color: #666;">
                        <div style="font-size: 2em; margin-bottom: 10px;">📝</div>
                        <h4>No Applications Yet</h4>
                        <p>You haven't applied to any startups yet. Start exploring opportunities!</p>
                        <div style="margin-top: 20px;">
                            <a href="browse_startups.php" class="btn btn-primary">🚀 Browse Startups</a>
                            <a href="browse_jobs.php" class="btn btn-success">💼 Browse Jobs</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 15px;">
                        <?php foreach ($recent_applications as $app): ?>
                            <div style="background: white; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; border-left: 4px solid 
                                <?php 
                                $colors = ['pending' => '#ffc107', 'accepted' => '#28a745', 'rejected' => '#dc3545', 'interviewed' => '#007bff'];
                                echo $colors[$app['status']] ?? '#6c757d';
                                ?>;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h4 style="margin: 0 0 5px 0;">🏢 <?php echo htmlspecialchars($app['org_name']); ?></h4>
                                        <?php if ($app['job_title']): ?>
                                            <h5 style="color: #007bff; margin: 5px 0;">
                                                <?php 
                                                $type_icons = ['job' => '💼', 'internship' => '🎓', 'hackathon' => '🚀', 'workshop' => '🎯', 'event' => '📅'];
                                                echo $type_icons[$app['job_type']] ?? '📝';
                                                ?> <?php echo htmlspecialchars($app['job_title']); ?>
                                            </h5>
                                        <?php endif; ?>
                                        <p style="color: #666; margin: 5px 0; font-size: 0.9em;">
                                            <?php echo htmlspecialchars($app['domain']); ?> • 
                                            Applied: <?php echo date('M j, Y', strtotime($app['applied_at'])); ?>
                                        </p>
                                    </div>
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: bold;
                                        background: <?php echo $colors[$app['status']] ?? '#6c757d'; ?>; 
                                        color: white;">
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Application Stats Summary -->
                    <div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px;">
                        <div style="text-align: center; padding: 15px; background: #fff3cd; border-radius: 8px;">
                            <div style="font-size: 1.5em; font-weight: bold; color: #856404;"><?php echo $status_counts['pending'] ?? 0; ?></div>
                            <div style="font-size: 0.9em; color: #856404;">Pending</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #d1ecf1; border-radius: 8px;">
                            <div style="font-size: 1.5em; font-weight: bold; color: #0c5460;"><?php echo $status_counts['interviewed'] ?? 0; ?></div>
                            <div style="font-size: 0.9em; color: #0c5460;">Interviewed</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #d4edda; border-radius: 8px;">
                            <div style="font-size: 1.5em; font-weight: bold; color: #155724;"><?php echo $status_counts['accepted'] ?? 0; ?></div>
                            <div style="font-size: 0.9em; color: #155724;">Accepted</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 8px;">
                            <div style="font-size: 1.5em; font-weight: bold; color: #721c24;"><?php echo $status_counts['rejected'] ?? 0; ?></div>
                            <div style="font-size: 0.9em; color: #721c24;">Rejected</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="action-card offers">
                    <div class="action-icon">📨</div>
                    <h3>Job Offers <?php if ($pending_offers_count > 0): ?><span style="background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.8em; margin-left: 5px;"><?php echo $pending_offers_count; ?></span><?php endif; ?></h3>
                    <p>View and respond to job offers sent directly by startups</p>
                    <a href="offers.php" class="btn btn-primary">View Offers</a>
                </div>
                
                <div class="action-card jobs">
                    <div class="action-icon">💼</div>
                    <h3>Browse Jobs</h3>
                    <p>Find full-time and part-time job opportunities from startups</p>
                    <a href="browse_jobs.php?type=job" class="btn btn-success">View Jobs</a>
                </div>
                
                <div class="action-card hackathons">
                    <div class="action-icon">🚀</div>
                    <h3>Join Hackathons</h3>
                    <p>Participate in exciting hackathons and coding competitions</p>
                    <a href="browse_jobs.php?type=hackathon" class="btn" style="background: #fd7e14; color: white;">Browse Hackathons</a>
                </div>
                
                <div class="action-card internships">
                    <div class="action-icon">🎓</div>
                    <h3>Find Internships</h3>
                    <p>Discover internship opportunities to gain valuable experience</p>
                    <a href="browse_jobs.php?type=internship" class="btn" style="background: #20c997; color: white;">View Internships</a>
                </div>
                
                <div class="action-card events">
                    <div class="action-icon">🎯</div>
                    <h3>Attend Events & Workshops</h3>
                    <p>Join workshops, webinars, and networking events</p>
                    <a href="browse_jobs.php?type=workshop" class="btn" style="background: #6f42c1; color: white;">Browse Events</a>
                </div>
                <div class="action-card hackathons">
                    <h3>🚀 Hackathons</h3>
                    <p>Participate in exciting hackathons and competitions</p>
                    <a href="browse_startups.php?tab=hackathons" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                        Join Hackathons
                    </a>
                </div>
                <div class="action-card internships">
                    <h3>🎓 Internships</h3>
                    <p>Gain experience with internship opportunities</p>
                    <a href="browse_startups.php?tab=internships" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                        Find Internships
                    </a>
                </div>
                <div class="action-card startups">
                    <h3>🏢 Startups</h3>
                    <p>Explore innovative startups and apply directly</p>
                    <a href="browse_startups.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                        Browse Startups
                    </a>
                </div>
            </div>
            
            <!-- Recommended Opportunities -->
            <div class="opportunity-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>⭐ Recommended for You</h3>
                    <a href="browse_jobs.php?type=job" class="btn btn-success">View All Jobs</a>
                </div>
                
                <?php if (empty($recommended_jobs)): ?>
                    <div style="text-align: center; padding: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; color: white;">
                        <div style="font-size: 2em; margin-bottom: 10px;">🎯</div>
                        <h4>No Skill-Matched Jobs Yet</h4>
                        <p>Update your skills in your profile to get personalized job recommendations!</p>
                        <div style="margin-top: 20px;">
                            <a href="profile.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                                Update Skills
                            </a>
                            <a href="browse_jobs.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                                Browse All Jobs
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                        <p style="color: white; margin: 0; text-align: center;">
                            <strong>🎯 Found <?php echo count($recommended_jobs); ?> job(s) matching your skills:</strong> 
                            <?php echo htmlspecialchars($student['skills']); ?>
                        </p>
                    </div>
                    
                    <div class="opportunity-grid">
                        <?php foreach ($recommended_jobs as $job): ?>
                            <div class="opportunity-card recommended-job" style="border: 2px solid #667eea; position: relative;">
                                <div style="position: absolute; top: -10px; right: 10px; background: #667eea; color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.8em; font-weight: bold;">
                                    ⭐ RECOMMENDED
                                </div>
                                <div class="opportunity-type type-job">
                                    💼 Job Opportunity
                                </div>
                                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                                    <div style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; border: 2px solid #e9ecef; flex-shrink: 0;">
                                        <?php if ($job['logo']): ?>
                                            <img src="../<?php echo htmlspecialchars($job['logo']); ?>" alt="<?php echo htmlspecialchars($job['org_name']); ?>" 
                                                 style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #666; font-size: 1.2em;">
                                                🏢
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h4 style="margin: 0;"><?php echo htmlspecialchars($job['title']); ?></h4>
                                        <p style="color: #666; margin: 5px 0; font-weight: bold;"><?php echo htmlspecialchars($job['org_name']); ?></p>
                                        <p style="color: #999; margin: 0; font-size: 0.9em;"><?php echo htmlspecialchars($job['startup_domain']); ?></p>
                                    </div>
                                </div>
                                <p style="margin: 10px 0;"><?php echo substr(htmlspecialchars($job['description']), 0, 120); ?>...</p>
                                
                                <div style="margin: 15px 0; font-size: 0.9em; color: #666;">
                                    <?php if ($job['location']): ?>
                                        <div>📍 <?php echo htmlspecialchars($job['location']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($job['salary']): ?>
                                        <div>💰 <?php echo htmlspecialchars($job['salary']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($job['required_skills']): ?>
                                        <div style="margin-top: 10px;">
                                            <strong>Required Skills:</strong><br>
                                            <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px;">
                                                <?php 
                                                $required_skills = explode(',', $job['required_skills']);
                                                $student_skills_array = explode(',', $student['skills']);
                                                $student_skills_array = array_map('trim', $student_skills_array);
                                                
                                                foreach ($required_skills as $skill): 
                                                    $skill = trim($skill);
                                                    if (!empty($skill)):
                                                        $is_match = in_array($skill, $student_skills_array);
                                                ?>
                                                    <span style="background: <?php echo $is_match ? '#28a745' : '#e9ecef'; ?>; 
                                                               color: <?php echo $is_match ? 'white' : '#666'; ?>; 
                                                               padding: 3px 8px; border-radius: 12px; font-size: 0.8em;">
                                                        <?php echo htmlspecialchars($skill); ?>
                                                        <?php if ($is_match): ?>✓<?php endif; ?>
                                                    </span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($job['deadline']): ?>
                                        <div style="margin-top: 8px; color: #dc3545;">⏰ Apply by: <?php echo date('M j, Y', strtotime($job['deadline'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="apply_job.php?id=<?php echo $job['id']; ?>" class="btn btn-primary" style="width: 100%;">
                                    🎯 Apply Now
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Latest Opportunities -->
            <div class="opportunity-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>🔥 Latest Opportunities</h3>
                    <a href="browse_startups.php" class="btn btn-primary">View All</a>
                </div>

                <?php if (empty($recent_jobs)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <h4>No opportunities available right now</h4>
                        <p>Check back later for new postings!</p>
                        <a href="browse_startups.php" class="btn btn-primary">Browse Startups</a>
                    </div>
                <?php else: ?>
                    <div class="opportunity-grid">
                        <?php foreach ($recent_jobs as $job): ?>
                            <div class="opportunity-card">
                                <div class="opportunity-type type-<?php echo str_replace('-', '', $job['type']); ?>">
                                    <?php echo ucfirst(str_replace('-', ' ', $job['type'])); ?>
                                </div>
                                
                                <!-- Startup Logo and Info -->
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
                                    <div style="width: 45px; height: 45px; border-radius: 50%; overflow: hidden; border: 2px solid #e9ecef; flex-shrink: 0;">
                                        <?php if ($job['logo']): ?>
                                            <img src="../<?php echo htmlspecialchars($job['logo']); ?>" alt="<?php echo htmlspecialchars($job['org_name']); ?>" 
                                                 style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #666; font-size: 1.1em;">
                                                🏢
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h4 style="margin: 0;"><?php echo htmlspecialchars($job['title']); ?></h4>
                                        <p style="color: #666; margin: 5px 0; font-weight: bold; font-size: 0.9em;"><?php echo htmlspecialchars($job['org_name']); ?></p>
                                        <p style="color: #999; margin: 0; font-size: 0.8em;"><?php echo htmlspecialchars($job['startup_domain']); ?></p>
                                    </div>
                                </div>

                                <!-- Event Images (for hackathons, workshops, events) -->
                                <?php if (($job['type'] === 'hackathon' || $job['type'] === 'workshop' || $job['type'] === 'event') && !empty($job['event_images'])): ?>
                                    <div style="margin-bottom: 15px;">
                                        <?php 
                                        $event_images = explode(',', $job['event_images']);
                                        $first_image = trim($event_images[0]);
                                        ?>
                                        <div style="width: 100%; height: 150px; border-radius: 8px; overflow: hidden; background: #f8f9fa; position: relative;">
                                            <img src="../<?php echo htmlspecialchars($first_image); ?>" alt="Event Image" 
                                                 style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php if (count($event_images) > 1): ?>
                                                <div style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.7); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8em;">
                                                    +<?php echo count($event_images) - 1; ?> more
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <p style="margin: 10px 0;"><?php echo substr(htmlspecialchars($job['description']), 0, 120); ?>...</p>
                                
                                <div style="margin: 15px 0; font-size: 0.9em; color: #666;">
                                    <?php if ($job['location']): ?>
                                        <div>📍 <?php echo htmlspecialchars($job['location']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($job['duration']): ?>
                                        <div>⏱️ <?php echo htmlspecialchars($job['duration']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($job['salary']): ?>
                                        <div>💰 <?php echo htmlspecialchars($job['salary']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($job['deadline']): ?>
                                        <div>📅 Deadline: <?php echo date('M j, Y', strtotime($job['deadline'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="apply_startup.php?startup_id=<?php echo $job['startup_id']; ?>" class="btn btn-primary btn-sm">
                                    Apply Now
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Skill to Startup Matcher. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
</body>
</html>
