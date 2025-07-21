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

// Get all applications for this student - using safer query
$stmt = $pdo->prepare("
    SELECT a.*, 
           COALESCE(s.org_name, 'Unknown Company') as org_name, 
           COALESCE(s.domain, 'General') as domain, 
           COALESCE(s.email, 'N/A') as startup_email,
           COALESCE(jp.title, 'General Application') as job_title, 
           COALESCE(jp.type, 'general') as job_type, 
           COALESCE(jp.location, 'Not specified') as job_location,
           COALESCE(jp.description, 'No description available') as job_description, 
           COALESCE(jp.salary, 'Not specified') as job_salary
    FROM applications a 
    LEFT JOIN startups s ON a.startup_id = s.id 
    LEFT JOIN job_posts jp ON a.job_id = jp.id
    WHERE a.student_id = ? 
    ORDER BY a.applied_at DESC
");
$stmt->execute([$student_id]);
$applications = $stmt->fetchAll();

// If no applications found with JOIN, try raw applications
if (empty($applications)) {
    $raw_stmt = $pdo->prepare("SELECT * FROM applications WHERE student_id = ? ORDER BY applied_at DESC");
    $raw_stmt->execute([$student_id]);
    $raw_applications = $raw_stmt->fetchAll();
    
    if (!empty($raw_applications)) {
        // Convert raw applications to display format
        foreach ($raw_applications as $raw_app) {
            $applications[] = array_merge($raw_app, [
                'org_name' => 'Company (ID: ' . $raw_app['startup_id'] . ')',
                'domain' => 'General',
                'startup_email' => 'N/A',
                'job_title' => 'Application (Job ID: ' . $raw_app['job_id'] . ')',
                'job_type' => 'general',
                'job_location' => 'Not specified',
                'job_description' => 'Application submitted successfully',
                'job_salary' => 'Not specified'
            ]);
        }
    }
}

// Debug: Check if we have applications
$debug_mode = isset($_GET['debug']);
if ($debug_mode) {
    echo "<div style='background: #f0f0f0; padding: 20px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h3>Debug Information</h3>";
    echo "Student ID: " . $student_id . "<br>";
    echo "Number of applications found: " . count($applications) . "<br>";
    
    // Also check raw applications without joins
    $raw_stmt = $pdo->prepare("SELECT * FROM applications WHERE student_id = ?");
    $raw_stmt->execute([$student_id]);
    $raw_applications = $raw_stmt->fetchAll();
    echo "Raw applications count: " . count($raw_applications) . "<br>";
    
    if (count($raw_applications) > 0) {
        echo "<br><strong>Raw Applications:</strong><br>";
        foreach ($raw_applications as $raw_app) {
            echo "ID: {$raw_app['id']}, Job ID: {$raw_app['job_id']}, Startup ID: {$raw_app['startup_id']}, Status: {$raw_app['status']}, Date: {$raw_app['applied_at']}<br>";
        }
    }
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - Student Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .applications-container {
            max-width: 1000px;
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
        .application-header {
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
        .status-interviewed { background: #d1ecf1; color: #0c5460; }
        .application-meta {
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
        .notes-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .filter-btn {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .filter-btn:hover {
            background: #e9ecef;
            border-color: #007bff;
        }
        .filter-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .application-card {
            transition: all 0.3s ease;
        }
        .application-card.hidden {
            display: none;
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
                        <li><a href="my_applications.php" class="active">My Applications</a></li>
                        <li><a href="offers.php">Job Offers</a></li>
                        <li><a href="profile.php">👤 Profile</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <div class="applications-container">
                <h2>📋 My Applications</h2>
                <p>Track your startup applications and their status</p>

                <!-- Statistics -->
                <div class="stats-bar">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($applications); ?></div>
                        <div>Total Applications</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($applications, fn($a) => $a['status'] === 'pending')); ?></div>
                        <div>Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($applications, fn($a) => $a['status'] === 'interviewed')); ?></div>
                        <div>Interviewed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($applications, fn($a) => $a['status'] === 'accepted')); ?></div>
                        <div>Accepted</div>
                    </div>
                </div>

                <!-- Application Type Filters -->
                <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h4 style="margin-bottom: 15px;">🔍 Filter by Type</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                        <button onclick="filterApplications('all')" class="filter-btn active" id="filter-all">
                            📝 All Applications (<?php echo count($applications); ?>)
                        </button>
                        <button onclick="filterApplications('job')" class="filter-btn" id="filter-job">
                            💼 Jobs (<?php echo count(array_filter($applications, fn($a) => ($a['job_type'] ?? 'general') === 'job')); ?>)
                        </button>
                        <button onclick="filterApplications('internship')" class="filter-btn" id="filter-internship">
                            🎓 Internships (<?php echo count(array_filter($applications, fn($a) => ($a['job_type'] ?? 'general') === 'internship')); ?>)
                        </button>
                        <button onclick="filterApplications('hackathon')" class="filter-btn" id="filter-hackathon">
                            🚀 Hackathons (<?php echo count(array_filter($applications, fn($a) => ($a['job_type'] ?? 'general') === 'hackathon')); ?>)
                        </button>
                        <button onclick="filterApplications('workshop')" class="filter-btn" id="filter-workshop">
                            🎯 Workshops (<?php echo count(array_filter($applications, fn($a) => ($a['job_type'] ?? 'general') === 'workshop')); ?>)
                        </button>
                        <button onclick="filterApplications('event')" class="filter-btn" id="filter-event">
                            📅 Events (<?php echo count(array_filter($applications, fn($a) => ($a['job_type'] ?? 'general') === 'event')); ?>)
                        </button>
                    </div>
                </div>

                <?php if (empty($applications)): ?>
                    <div class="application-card">
                        <h4>No Applications Found</h4>
                        <?php if ($debug_mode): ?>
                            <p style="color: red;">Debug mode is ON - check the debug information above to see if applications exist in the database.</p>
                        <?php endif; ?>
                        <p>You haven't applied to any positions yet, or there might be a data issue.</p>
                        <div style="margin-top: 15px;">
                            <a href="browse_startups.php" class="btn btn-primary">🚀 Browse Opportunities</a>
                            <a href="my_applications.php?debug=1" class="btn btn-secondary">🔍 Debug Mode</a>
                        </div>
                    </div>
                <?php else: ?>

                <?php foreach ($applications as $app): ?>
                    <div class="application-card <?php echo $app['status']; ?>" data-type="<?php echo htmlspecialchars($app['job_type'] ?? 'general'); ?>">
                        <div class="application-header">
                            <div>
                                <h4>🏢 <?php echo htmlspecialchars($app['org_name']); ?></h4>
                                <?php if ($app['job_title']): ?>
                                    <h5 style="color: #007bff; margin: 5px 0;">
                                        <?php 
                                        $type_icons = [
                                            'job' => '💼',
                                            'internship' => '🎓',
                                            'hackathon' => '🚀',
                                            'workshop' => '🎯',
                                            'event' => '📅'
                                        ];
                                        echo $type_icons[$app['job_type']] ?? '📝';
                                        ?> <?php echo htmlspecialchars($app['job_title']); ?>
                                    </h5>
                                <?php endif; ?>
                                <p style="color: #666; margin: 5px 0;">
                                    <?php echo htmlspecialchars($app['domain'] ?? $app['industry']); ?> • 
                                    <?php echo ucfirst($app['application_type']); ?>
                                    <?php if ($app['job_location']): ?>
                                        • <?php echo htmlspecialchars($app['job_location']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                <?php echo ucfirst($app['status']); ?>
                            </span>
                        </div>

                        <div class="application-meta">
                            <div>
                                <strong>🆔 Application ID:</strong> <span style="font-family: monospace; background: #e9ecef; padding: 2px 6px; border-radius: 4px;"><?php echo htmlspecialchars($app['application_id'] ?? '#' . $app['id']); ?></span>
                            </div>
                            <div>
                                <strong>📅 Applied:</strong> <?php echo date('M j, Y g:i A', strtotime($app['applied_at'])); ?>
                            </div>
                            <div>
                                <strong>📧 Contact:</strong> <?php echo htmlspecialchars($app['startup_email']); ?>
                            </div>
                            <?php if ($app['reviewed_at']): ?>
                                <div>
                                    <strong>👀 Reviewed:</strong> <?php echo date('M j, Y g:i A', strtotime($app['reviewed_at'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Cover Letter -->
                        <?php if ($app['cover_letter']): ?>
                            <div style="margin: 15px 0;">
                                <strong>💌 Your Cover Letter:</strong>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 5px; border-left: 3px solid #007bff;">
                                    <?php echo nl2br(htmlspecialchars($app['cover_letter'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Resume -->
                        <?php if ($app['resume_path']): ?>
                            <div style="margin: 15px 0;">
                                <strong>📄 Resume:</strong>
                                <div style="margin-top: 5px;">
                                    <a href="<?php echo htmlspecialchars($app['resume_path']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                        📥 View My Resume
                                    </a>
                                    <small style="margin-left: 10px; color: #666;">
                                        <?php echo basename($app['resume_path']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Startup Notes -->
                        <?php if ($app['notes']): ?>
                            <div class="notes-section">
                                <strong>💬 Startup Notes:</strong>
                                <p style="margin: 10px 0;"><?php echo nl2br(htmlspecialchars($app['notes'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Actions -->
                        <div style="margin-top: 15px; display: flex; gap: 10px;">
                            <a href="mailto:<?php echo htmlspecialchars($app['startup_email']); ?>" class="btn btn-sm btn-secondary">
                                📧 Email Startup
                            </a>
                            <?php if ($app['status'] === 'accepted'): ?>
                                <span class="btn btn-sm btn-success" style="cursor: default;">
                                    🎉 Congratulations!
                                </span>
                            <?php elseif ($app['status'] === 'interviewed'): ?>
                                <span class="btn btn-sm btn-info" style="cursor: default;">
                                    📞 Interview Scheduled
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php endif; ?>

                <!-- Quick Actions -->
                <div style="text-align: center; margin-top: 30px;">
                    <a href="browse_startups.php" class="btn btn-primary">🚀 Apply to More Startups</a>
                    <a href="dashboard.php" class="btn btn-secondary">📊 Back to Dashboard</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        function filterApplications(type) {
            // Remove active class from all filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            document.getElementById('filter-' + type).classList.add('active');
            
            // Get all application cards
            const applicationCards = document.querySelectorAll('.application-card[data-type]');
            
            applicationCards.forEach(card => {
                if (type === 'all') {
                    card.classList.remove('hidden');
                } else {
                    const cardType = card.getAttribute('data-type');
                    if (cardType === type) {
                        card.classList.remove('hidden');
                    } else {
                        card.classList.add('hidden');
                    }
                }
            });
        }
    </script>
</body>
</html>
