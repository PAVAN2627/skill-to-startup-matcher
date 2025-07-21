<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$startups = []; // Initialize empty array to prevent errors
$error = '';

try {
    // Get all startups with their details
    $stmt = $pdo->query("
        SELECT id, org_name, email, domain, description, address, 
               website, linkedin, instagram, twitter, founded_year, team_size, 
               is_verified, is_approved, created_at
        FROM startups 
        ORDER BY created_at DESC
    ");
    $startups = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Failed to load startups data.';
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Startups - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .startups-container {
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
        }
        .startup-card.approved {
            border-left-color: #28a745;
        }
        .startup-card.pending {
            border-left-color: #ffc107;
        }
        .startup-card.unverified {
            border-left-color: #dc3545;
        }
        .startup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .startup-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-unverified { background: #f8d7da; color: #721c24; }
        .description-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
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
                    <h1><a href="dashboard.php" style="color: white; text-decoration: none;">Skill2Startup Admin</a></h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="approve_startups.php">Review Startups</a></li>
                        <li><a href="view_students.php">View Students</a></li>
                        <li><a href="view_startups.php" class="active">View Startups</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <div class="startups-container">
                <h2>🏢 Registered Startups</h2>
                <p>Manage and view all startups on the platform</p>

                <?php if ($error): ?>
                    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc3545;">
                        ❌ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <div class="filter-tab active" onclick="filterStartups('all')">All Startups (<?php echo count($startups); ?>)</div>
                    <div class="filter-tab" onclick="filterStartups('approved')">Approved</div>
                    <div class="filter-tab" onclick="filterStartups('pending')">Pending</div>
                    <div class="filter-tab" onclick="filterStartups('unverified')">Unverified</div>
                </div>

                <?php if (empty($startups)): ?>
                    <div class="startup-card">
                        <h4>No Startups Yet</h4>
                        <p>No startups have registered on the platform yet.</p>
                    </div>
                <?php else: ?>

                <?php foreach ($startups as $startup): ?>
                    <?php 
                        $status_class = !$startup['is_verified'] ? 'unverified' : 
                                       ($startup['is_approved'] ? 'approved' : 'pending');
                        $status_text = !$startup['is_verified'] ? '❌ Unverified' : 
                                      ($startup['is_approved'] ? '✅ Approved' : '⏳ Pending');
                    ?>
                    <div class="startup-card <?php echo $status_class; ?>" data-status="<?php echo $status_class; ?>">
                        <div class="startup-header">
                            <div>
                                <h4>🏢 <?php echo htmlspecialchars($startup['org_name']); ?></h4>
                                <p style="color: #666; margin: 5px 0;">
                                    <?php echo htmlspecialchars($startup['domain']); ?> • <?php echo htmlspecialchars($startup['address'] ?: 'Address not provided'); ?>
                                </p>
                            </div>
                            <span class="status-badge status-<?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </div>

                        <div class="startup-info">
                            <div>
                                <p><strong>📧 Email:</strong> <?php echo htmlspecialchars($startup['email']); ?></p>
                                <p><strong>🌐 Website:</strong> 
                                    <?php if ($startup['website']): ?>
                                        <a href="<?php echo htmlspecialchars($startup['website']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($startup['website']); ?>
                                        </a>
                                    <?php else: ?>
                                        Not provided
                                    <?php endif; ?>
                                </p>
                                <p><strong>📍 Address:</strong> <?php echo htmlspecialchars($startup['address'] ?: 'Not provided'); ?></p>
                                <p><strong>🏷️ Domain:</strong> <?php echo htmlspecialchars($startup['domain'] ?: 'Not specified'); ?></p>
                            </div>
                            <div>
                                <p><strong>🆔 Startup ID:</strong> #<?php echo $startup['id']; ?></p>
                                <p><strong>📅 Founded:</strong> <?php echo $startup['founded_year'] ?: 'Not specified'; ?></p>
                                <p><strong>👥 Team Size:</strong> <?php echo $startup['team_size'] ?: 'Not specified'; ?></p>
                                <p><strong>📅 Registered:</strong> <?php echo date('M j, Y', strtotime($startup['created_at'])); ?></p>
                                
                                <!-- Social Media Links -->
                                <?php if ($startup['linkedin'] || $startup['instagram'] || $startup['twitter']): ?>
                                    <div style="margin-top: 10px;">
                                        <strong>🔗 Social:</strong>
                                        <?php if ($startup['linkedin']): ?>
                                            <a href="<?php echo htmlspecialchars($startup['linkedin']); ?>" target="_blank" style="margin: 0 5px; color: #0077b5;">LinkedIn</a>
                                        <?php endif; ?>
                                        <?php if ($startup['instagram']): ?>
                                            <a href="<?php echo htmlspecialchars($startup['instagram']); ?>" target="_blank" style="margin: 0 5px; color: #e4405f;">Instagram</a>
                                        <?php endif; ?>
                                        <?php if ($startup['twitter']): ?>
                                            <a href="<?php echo htmlspecialchars($startup['twitter']); ?>" target="_blank" style="margin: 0 5px; color: #1da1f2;">Twitter</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Description Section -->
                        <?php if ($startup['description']): ?>
                            <div class="description-section">
                                <h5>📝 Company Description</h5>
                                <p><?php echo nl2br(htmlspecialchars($startup['description'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Actions -->
                        <div style="margin-top: 20px;">
                            <a href="mailto:<?php echo htmlspecialchars($startup['email']); ?>" 
                               style="display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;">
                                📧 Email Startup
                            </a>
                            <?php if ($startup['website']): ?>
                                <a href="<?php echo htmlspecialchars($startup['website']); ?>" target="_blank"
                                   style="display: inline-block; padding: 8px 16px; background: #17a2b8; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;">
                                    🌐 Visit Website
                                </a>
                            <?php endif; ?>
                            <?php if (!$startup['is_approved'] && $startup['is_verified']): ?>
                                <a href="approve_startups.php" 
                                   style="display: inline-block; padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;">
                                    ✅ Review for Approval
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
        function filterStartups(status) {
            const cards = document.querySelectorAll('.startup-card');
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
    </script>
</body>
</html>
