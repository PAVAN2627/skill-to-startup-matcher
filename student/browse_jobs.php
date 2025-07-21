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

try {
    // Get student data
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    // Get job posts
    $filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $sql = "SELECT jp.*, s.org_name as startup_name, s.domain 
            FROM job_posts jp 
            JOIN startups s ON jp.startup_id = s.id 
            WHERE jp.status = 'active' AND s.is_verified = 1 AND s.is_approved = 1";
    
    $params = [];
    
    if ($filter_type !== 'all') {
        $sql .= " AND jp.type = ?";
        $params[] = $filter_type;
    }
    
    if ($search) {
        $sql .= " AND (jp.title LIKE ? OR jp.description LIKE ? OR s.org_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $sql .= " ORDER BY jp.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $job_posts = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Failed to load job posts.';
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Opportunities - Skill2Startup</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 15px;
            align-items: center;
        }
        .filter-tabs {
            display: flex;
            gap: 10px;
        }
        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #007bff;
            border-radius: 25px;
            text-decoration: none;
            color: #007bff;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .filter-tab.active, .filter-tab:hover {
            background: #007bff;
            color: white;
        }
        .opportunity-grid {
            display: grid;
            gap: 20px;
        }
        .opportunity-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            border-left: 4px solid #007bff;
        }
        .opportunity-card:hover {
            transform: translateY(-3px);
        }
        .opportunity-card.job { border-left-color: #28a745; }
        .opportunity-card.internship { border-left-color: #20c997; }
        .opportunity-card.hackathon { border-left-color: #fd7e14; }
        .opportunity-card.workshop { border-left-color: #6f42c1; }
        .opportunity-card.event { border-left-color: #dc3545; }
        
        .opportunity-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .opportunity-type {
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
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
                    <h1><a href="../index.php" style="color: white; text-decoration: none;">Skill2Startup</a></h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="browse_startups.php">Browse Startups</a></li>
                        <li><a href="browse_jobs.php" class="active">Browse Opportunities</a></li>
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
        <div class="container" style="margin-top: 20px;">
            <h2>🎯 Browse Opportunities</h2>
            <p style="color: #666; margin-bottom: 30px;">Discover jobs, internships, hackathons, workshops, and events</p>
            
            <div class="filter-bar">
                <div class="filter-tabs">
                    <a href="?type=all&search=<?php echo urlencode($search); ?>" 
                       class="filter-tab <?php echo $filter_type === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="?type=job&search=<?php echo urlencode($search); ?>" 
                       class="filter-tab <?php echo $filter_type === 'job' ? 'active' : ''; ?>">Jobs</a>
                    <a href="?type=internship&search=<?php echo urlencode($search); ?>" 
                       class="filter-tab <?php echo $filter_type === 'internship' ? 'active' : ''; ?>">Internships</a>
                    <a href="?type=hackathon&search=<?php echo urlencode($search); ?>" 
                       class="filter-tab <?php echo $filter_type === 'hackathon' ? 'active' : ''; ?>">Hackathons</a>
                    <a href="?type=workshop&search=<?php echo urlencode($search); ?>" 
                       class="filter-tab <?php echo $filter_type === 'workshop' ? 'active' : ''; ?>">Workshops</a>
                    <a href="?type=event&search=<?php echo urlencode($search); ?>" 
                       class="filter-tab <?php echo $filter_type === 'event' ? 'active' : ''; ?>">Events</a>
                </div>
                
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($filter_type); ?>">
                    <input type="text" name="search" placeholder="Search opportunities..." 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           style="padding: 10px; border: 1px solid #ddd; border-radius: 5px; width: 250px;">
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="opportunity-grid">
                <?php if (empty($job_posts)): ?>
                    <div class="card text-center">
                        <p>No opportunities found matching your criteria.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($job_posts as $job): ?>
                        <div class="opportunity-card <?php echo $job['type']; ?>">
                            <div class="opportunity-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                                    <p><strong><?php echo htmlspecialchars($job['startup_name']); ?></strong> • <?php echo htmlspecialchars($job['domain']); ?></p>
                                </div>
                                <span class="opportunity-type type-<?php echo $job['type']; ?>">
                                    <?php echo ucfirst($job['type']); ?>
                                </span>
                            </div>
                            
                            <p><?php echo htmlspecialchars(substr($job['description'], 0, 200)) . (strlen($job['description']) > 200 ? '...' : ''); ?></p>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 15px 0;">
                                <?php if ($job['location']): ?>
                                    <div><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></div>
                                <?php endif; ?>
                                <?php if ($job['salary']): ?>
                                    <div><strong>Salary:</strong> <?php echo htmlspecialchars($job['salary']); ?></div>
                                <?php endif; ?>
                                <?php if ($job['deadline']): ?>
                                    <div><strong>Deadline:</strong> <?php echo date('M d, Y', strtotime($job['deadline'])); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($job['requirements']): ?>
                                <div style="margin: 15px 0;">
                                    <strong>Requirements:</strong><br>
                                    <span style="color: #666;"><?php echo htmlspecialchars($job['requirements']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                                <small style="color: #666;">
                                    Posted <?php echo date('M d, Y', strtotime($job['created_at'])); ?>
                                </small>
                                <a href="apply_startup.php?startup_id=<?php echo $job['startup_id']; ?>&job_id=<?php echo $job['id']; ?>" 
                                   class="btn btn-primary">Apply Now</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
</body>
</html>
