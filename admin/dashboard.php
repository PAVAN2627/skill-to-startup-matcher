<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

try {
    // Get statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total_students FROM students WHERE is_verified = 1");
    $total_students = $stmt->fetch()['total_students'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_startups FROM startups WHERE is_verified = 1");
    $total_startups = $stmt->fetch()['total_startups'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as approved_startups FROM startups WHERE is_verified = 1 AND is_approved = 1");
    $approved_startups = $stmt->fetch()['approved_startups'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as pending_startups FROM startups WHERE is_verified = 1 AND is_approved = 0");
    $pending_startups = $stmt->fetch()['pending_startups'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_matches FROM matches");
    $total_matches = $stmt->fetch()['total_matches'];
    
    // Get recent registrations
    $stmt = $pdo->query("
        SELECT 'student' as type, name as display_name, email, created_at 
        FROM students 
        WHERE is_verified = 1 
        UNION ALL 
        SELECT 'startup' as type, org_name as display_name, email, created_at 
        FROM startups 
        WHERE is_verified = 1 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recent_registrations = $stmt->fetchAll();
    
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
    <title>Admin Dashboard - Skill2Startup</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            background: #f8f9fa;
        }
        .dashboard-grid {
            gap: 1.5rem;
        }
        .stat-card {
            border: 1px solid #e9ecef;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1><a href="dashboard.php" style="color: white; text-decoration: none;">Skill2Startup Admin</a></h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard.php" class="active">Dashboard</a></li>
                        <li><a href="approve_startups.php">Review Startups</a></li>
                        <li><a href="view_students.php">View Students</a></li>
                        <li><a href="view_startups.php">View Startups</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</h2>
                </div>
                
                <div class="dashboard-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_students; ?></div>
                        <div class="stat-label">Verified Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_startups; ?></div>
                        <div class="stat-label">Total Startups</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $approved_startups; ?></div>
                        <div class="stat-label">Approved Startups</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #ffc107;"><?php echo $pending_startups; ?></div>
                        <div class="stat-label">Pending Approval</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_matches; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                </div>
            </div>

            <?php if ($pending_startups > 0): ?>
                <div class="alert alert-warning">
                    <h4>Action Required</h4>
                    <p>There are <strong><?php echo $pending_startups; ?></strong> startup(s) waiting for approval.</p>
                    <a href="approve_startups.php" class="btn btn-primary">Review Pending Startups</a>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="dashboard-grid" style="grid-template-columns: repeat(3, 1fr);">
                    <a href="approve_startups.php" class="stat-card" style="text-decoration: none; color: inherit; transition: all 0.3s ease;">
                        <div class="stat-number">📋</div>
                        <div class="stat-label">Review Startups</div>
                        <small style="color: #666; margin-top: 10px; display: block;">Approve pending startups</small>
                    </a>
                    <a href="view_students.php" class="stat-card" style="text-decoration: none; color: inherit; transition: all 0.3s ease;">
                        <div class="stat-number">👥</div>
                        <div class="stat-label">View Students</div>
                        <small style="color: #666; margin-top: 10px; display: block;">Manage registered students</small>
                    </a>
                    <a href="view_startups.php" class="stat-card" style="text-decoration: none; color: inherit; transition: all 0.3s ease;">
                        <div class="stat-number">🏢</div>
                        <div class="stat-label">View Startups</div>
                        <small style="color: #666; margin-top: 10px; display: block;">Browse all startups</small>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Registrations</h3>
                </div>
                
                <?php if (empty($recent_registrations)): ?>
                    <p class="text-center text-muted">No recent registrations.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Registration Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_registrations as $registration): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-<?php echo $registration['type'] === 'student' ? 'approved' : 'pending'; ?>">
                                                <?php echo ucfirst($registration['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($registration['display_name']); ?></td>
                                        <td><?php echo htmlspecialchars($registration['email']); ?></td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($registration['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Platform Statistics -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Platform Health</h3>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div style="text-align: center; padding: 1rem;">
                        <h4 style="color: #28a745;">Active Users</h4>
                        <p style="font-size: 2rem; margin: 0;"><?php echo $total_students + $approved_startups; ?></p>
                    </div>
                    <div style="text-align: center; padding: 1rem;">
                        <h4 style="color: #17a2b8;">Success Rate</h4>
                        <p style="font-size: 2rem; margin: 0;">
                            <?php echo $total_startups > 0 ? round(($approved_startups / $total_startups) * 100) : 0; ?>%
                        </p>
                    </div>
                    <div style="text-align: center; padding: 1rem;">
                        <h4 style="color: #ffc107;">Pending Review</h4>
                        <p style="font-size: 2rem; margin: 0;"><?php echo $pending_startups; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Skill2Startup Admin Panel. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
</body>
</html>
