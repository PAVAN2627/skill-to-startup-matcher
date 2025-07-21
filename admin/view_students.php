<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$students = []; // Initialize empty array to prevent errors
$error = '';

try {
    // Get all students with their details
    $stmt = $pdo->query("
        SELECT id, name, email, skills, interests, college, contact, availability, 
               is_verified, created_at
        FROM students 
        ORDER BY created_at DESC
    ");
    $students = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Failed to load students data.';
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Students - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .students-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .student-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .student-card.verified {
            border-left-color: #28a745;
        }
        .student-card.unverified {
            border-left-color: #ffc107;
        }
        .student-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .student-info {
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
        .status-verified { background: #d4edda; color: #155724; }
        .status-unverified { background: #fff3cd; color: #856404; }
        .skills-section, .interests-section {
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
                        <li><a href="view_students.php" class="active">View Students</a></li>
                        <li><a href="view_startups.php">View Startups</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <div class="students-container">
                <h2>👥 Registered Students</h2>
                <p>Manage and view all students on the platform</p>

                <?php if ($error): ?>
                    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc3545;">
                        ❌ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <div class="filter-tab active" onclick="filterStudents('all')">All Students (<?php echo count($students); ?>)</div>
                    <div class="filter-tab" onclick="filterStudents('verified')">Verified</div>
                    <div class="filter-tab" onclick="filterStudents('unverified')">Unverified</div>
                </div>

                <?php if (empty($students)): ?>
                    <div class="student-card">
                        <h4>No Students Yet</h4>
                        <p>No students have registered on the platform yet.</p>
                    </div>
                <?php else: ?>

                <?php foreach ($students as $student): ?>
                    <div class="student-card <?php echo $student['is_verified'] ? 'verified' : 'unverified'; ?>" 
                         data-status="<?php echo $student['is_verified'] ? 'verified' : 'unverified'; ?>">
                        <div class="student-header">
                            <div>
                                <h4>👤 <?php echo htmlspecialchars($student['name']); ?></h4>
                                <p style="color: #666; margin: 5px 0;">
                                    <?php echo htmlspecialchars($student['college']); ?>
                                </p>
                            </div>
                            <span class="status-badge status-<?php echo $student['is_verified'] ? 'verified' : 'unverified'; ?>">
                                <?php echo $student['is_verified'] ? '✅ Verified' : '⏳ Unverified'; ?>
                            </span>
                        </div>

                        <div class="student-info">
                            <div>
                                <p><strong>📧 Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                                <p><strong>📞 Contact:</strong> <?php echo htmlspecialchars($student['contact']); ?></p>
                                <p><strong>⏰ Availability:</strong> <?php echo htmlspecialchars($student['availability']); ?></p>
                            </div>
                            <div>
                                <p><strong>🆔 Student ID:</strong> #<?php echo $student['id']; ?></p>
                                <p><strong>📅 Registered:</strong> <?php echo date('M j, Y g:i A', strtotime($student['created_at'])); ?></p>
                            </div>
                        </div>

                        <!-- Skills Section -->
                        <?php if ($student['skills']): ?>
                            <div class="skills-section">
                                <h5>🛠️ Skills</h5>
                                <p><?php echo nl2br(htmlspecialchars($student['skills'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Interests Section -->
                        <?php if ($student['interests']): ?>
                            <div class="interests-section">
                                <h5>💡 Interests</h5>
                                <p><?php echo nl2br(htmlspecialchars($student['interests'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Actions -->
                        <div style="margin-top: 20px;">
                            <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" 
                               style="display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;">
                                📧 Email Student
                            </a>
                            <?php if ($student['contact']): ?>
                                <a href="tel:<?php echo htmlspecialchars($student['contact']); ?>" 
                                   style="display: inline-block; padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;">
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
        function filterStudents(status) {
            const cards = document.querySelectorAll('.student-card');
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
