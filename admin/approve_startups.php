<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/otp_mailer.php';

requireAdmin();

$error = '';
$success = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startup_id = (int)$_POST['startup_id'];
    $action = $_POST['action'];
    
    if (in_array($action, ['approve', 'reject'])) {
        try {
            $is_approved = ($action === 'approve') ? 1 : -1;
            
            // Get startup details first
            $stmt = $pdo->prepare("SELECT org_name, email FROM startups WHERE id = ?");
            $stmt->execute([$startup_id]);
            $startup = $stmt->fetch();
            
            if ($startup) {
                // Update startup status
                $stmt = $pdo->prepare("UPDATE startups SET is_approved = ? WHERE id = ?");
                $stmt->execute([$is_approved, $startup_id]);
                
                // Send email notification
                $approved = ($action === 'approve');
                sendApprovalEmail($startup['email'], $startup['org_name'], $approved);
                
                $success = "Startup " . $action . "d successfully and notification email sent!";
            } else {
                $error = "Startup not found.";
            }
        } catch (PDOException $e) {
            $error = 'Failed to update startup status.';
            error_log($e->getMessage());
        }
    }
}

try {
    // Get pending startups
    $stmt = $pdo->query("
        SELECT * FROM startups 
        WHERE is_verified = 1 AND is_approved = 0 
        ORDER BY created_at ASC
    ");
    $pending_startups = $stmt->fetchAll();
    
    // Get all startups for reference
    $stmt = $pdo->query("
        SELECT *, 
        CASE 
            WHEN is_approved = 1 THEN 'Approved'
            WHEN is_approved = 0 THEN 'Pending'
            ELSE 'Rejected'
        END as status_text
        FROM startups 
        WHERE is_verified = 1 
        ORDER BY created_at DESC
    ");
    $all_startups = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Failed to load startup data.';
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Startups - Skill2Startup Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
                        <li><a href="approve_startups.php">Approve Startups</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Pending Approvals -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Pending Startup Approvals (<?php echo count($pending_startups); ?>)</h2>
                </div>
                
                <?php if (empty($pending_startups)): ?>
                    <div class="text-center p-3">
                        <p class="text-muted">No startups pending approval.</p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 1rem;">
                        <?php foreach ($pending_startups as $startup): ?>
                            <div class="card" style="margin: 0; border-left: 4px solid #ffc107;">
                                <div style="display: flex; justify-content: between; align-items: start; gap: 2rem;">
                                    <div style="flex: 1;">
                                        <h4><?php echo htmlspecialchars($startup['org_name']); ?></h4>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($startup['email']); ?></p>
                                        <p><strong>Domain:</strong> <?php echo htmlspecialchars($startup['domain']); ?></p>
                                        <p><strong>Registration Date:</strong> <?php echo date('M d, Y g:i A', strtotime($startup['created_at'])); ?></p>
                                        
                                        <div style="margin-top: 1rem;">
                                            <strong>Description:</strong><br>
                                            <span class="text-muted"><?php echo htmlspecialchars($startup['description']); ?></span>
                                        </div>
                                        
                                        <?php if ($startup['id_proof']): ?>
                                            <div style="margin-top: 1rem;">
                                                <strong>ID Proof:</strong><br>
                                                <a href="../uploads/id_proofs/<?php echo htmlspecialchars($startup['id_proof']); ?>" 
                                                   target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                                                    View Document
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="text-align: right; min-width: 150px;">
                                        <form method="POST" style="display: inline-block; margin-bottom: 0.5rem;">
                                            <input type="hidden" name="startup_id" value="<?php echo $startup['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-block"
                                                    onclick="return confirm('Approve this startup?')">
                                                ✓ Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="startup_id" value="<?php echo $startup['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger btn-block"
                                                    onclick="return confirm('Reject this startup? This action cannot be undone.')">
                                                ✗ Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- All Startups -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">All Startups</h3>
                </div>
                
                <div class="mb-3">
                    <input type="text" id="searchTable" class="form-control" placeholder="Search startups..." style="max-width: 300px;">
                </div>
                
                <?php if (empty($all_startups)): ?>
                    <p class="text-center text-muted">No verified startups found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table" id="startupsTable">
                            <thead>
                                <tr>
                                    <th>Organization</th>
                                    <th>Email</th>
                                    <th>Domain</th>
                                    <th>Status</th>
                                    <th>Registration Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_startups as $startup): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($startup['org_name']); ?></td>
                                        <td><?php echo htmlspecialchars($startup['email']); ?></td>
                                        <td><?php echo htmlspecialchars($startup['domain']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $startup['is_approved'] == 1 ? 'approved' : ($startup['is_approved'] == 0 ? 'pending' : 'rejected'); ?>">
                                                <?php echo $startup['status_text']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($startup['created_at'])); ?></td>
                                        <td>
                                            <?php if ($startup['id_proof']): ?>
                                                <a href="../uploads/id_proofs/<?php echo htmlspecialchars($startup['id_proof']); ?>" 
                                                   target="_blank" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                    View Docs
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($startup['is_approved'] == 0): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="startup_id" value="<?php echo $startup['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                            onclick="return confirm('Approve this startup?')">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="startup_id" value="<?php echo $startup['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                            onclick="return confirm('Reject this startup?')">
                                                        Reject
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Skill2Startup Admin Panel. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
    <script>
        // Initialize search functionality
        searchTable('searchTable', 'startupsTable');
    </script>
</body>
</html>
