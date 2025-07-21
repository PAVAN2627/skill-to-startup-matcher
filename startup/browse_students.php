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

// Handle search filters
$search_skills = isset($_GET['skills']) ? trim($_GET['skills']) : '';

// Build search query for skills matching
$where_conditions = [];
$params = [];

if (!empty($search_skills)) {
    // Split skills by comma and search for each skill
    $skills_array = array_map('trim', explode(',', $search_skills));
    $skill_conditions = [];
    foreach ($skills_array as $skill) {
        if (!empty($skill)) {
            $skill_conditions[] = "s.skills LIKE ?";
            $params[] = "%$skill%";
        }
    }
    if (!empty($skill_conditions)) {
        $where_conditions[] = '(' . implode(' OR ', $skill_conditions) . ')';
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get all students with their skills
$stmt = $pdo->prepare("
    SELECT s.*, 
           COUNT(a.id) as total_applications,
           COUNT(CASE WHEN a.status = 'accepted' THEN 1 END) as accepted_applications
    FROM students s 
    LEFT JOIN applications a ON s.id = a.student_id 
    $where_clause
    GROUP BY s.id 
    ORDER BY s.created_at DESC
");
$stmt->execute($params);
$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Students - Startup Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .students-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .student-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .student-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .student-info h4 {
            margin: 0 0 5px 0;
            color: #007bff;
        }
        .student-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 15px 0;
        }
        .skill-tag {
            background: #007bff;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
            text-decoration: none;
        }
        .clickable-skill {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .clickable-skill:hover {
            background: #0056b3;
            transform: scale(1.05);
            box-shadow: 0 2px 5px rgba(0,123,255,0.3);
            color: white;
            text-decoration: none;
        }
        .stats-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            background: #e9ecef;
            color: #495057;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-offer {
            background: #28a745;
            color: white;
            border: none;
        }
        .btn-offer:hover {
            background: #218838;
        }
        .students-grid {
            display: grid;
            gap: 20px;
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
                        <li><a href="browse_students.php" class="active">Browse Students</a></li>
                        <li><a href="post_opportunity.php">Post Job/Opportunity</a></li>
                        <li><a href="manage_jobs.php">Manage Posts</a></li>
                        <li><a href="view_applications.php">View Applications</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <div class="students-container">
                <h2>👥 Browse Students</h2>
                <p>Discover talented students and send them job offers</p>

                <!-- Statistics -->
                <div class="stats-bar">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($students); ?></div>
                        <div>Total Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($students, fn($s) => !empty($s['skills']))); ?></div>
                        <div>With Skills Listed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($students, fn($s) => $s['total_applications'] > 0)); ?></div>
                        <div>Active Job Seekers</div>
                    </div>
                </div>

                <!-- Skills Search Filter -->
                <div class="filters">
                    <h4>🔍 Find Students by Skills</h4>
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div style="flex: 2;">
                                <label for="skills">Skills (comma-separated for better matching)</label>
                                <input type="text" id="skills" name="skills" 
                                       placeholder="e.g., Python, React, Design, JavaScript, Node.js..." 
                                       value="<?php echo htmlspecialchars($search_skills); ?>" 
                                       class="form-control"
                                       style="width: 100%;">
                            </div>
                            <div style="display: flex; gap: 10px; align-items: end;">
                                <button type="submit" class="btn btn-primary">🔍 Search Skills</button>
                                <a href="browse_students.php" class="btn btn-secondary">Show All</a>
                            </div>
                        </div>
                        <?php if (!empty($search_skills)): ?>
                        <div style="margin-top: 10px; padding: 10px; background: #e8f4f8; border-radius: 5px; color: #2c5aa0;">
                            <strong>🎯 Searching for skills:</strong> <?php echo htmlspecialchars($search_skills); ?>
                            <br><small>Tip: Use commas to separate multiple skills for better matching</small>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Students List -->
                <?php if (empty($students)): ?>
                    <div class="student-card">
                        <h4>No Students Found</h4>
                        <p>No students match your search criteria. Try adjusting your filters.</p>
                        <a href="browse_students.php" class="btn btn-primary">View All Students</a>
                    </div>
                <?php else: ?>

                <div class="students-grid">
                    <?php foreach ($students as $student): ?>
                        <div class="student-card">
                            <div class="student-header">
                                <div class="student-info">
                                    <h4>👤 <?php echo htmlspecialchars($student['name']); ?></h4>
                                    <p style="color: #666; margin: 5px 0;">
                                        📧 <?php echo htmlspecialchars($student['email']); ?>
                                    </p>
                                    <?php if (isset($student['contact']) && $student['contact']): ?>
                                        <p style="color: #666; margin: 5px 0;">
                                            📱 <?php echo htmlspecialchars($student['contact']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="stats-badge">
                                    <?php echo $student['total_applications']; ?> Applications
                                </div>
                            </div>

                            <div class="student-meta">
                                <?php if (isset($student['college']) && $student['college']): ?>
                                    <div>
                                        <strong>🏫 College:</strong> <?php echo htmlspecialchars($student['college']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($student['availability']) && $student['availability']): ?>
                                    <div>
                                        <strong>⏰ Availability:</strong> <?php echo htmlspecialchars($student['availability']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($student['interests']) && $student['interests']): ?>
                                    <div>
                                        <strong>💡 Interests:</strong> <?php echo htmlspecialchars($student['interests']); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong>📅 Joined:</strong> <?php echo date('M Y', strtotime($student['created_at'])); ?>
                                </div>
                            </div>

                            <!-- Skills -->
                            <?php if ($student['skills']): ?>
                                <div style="margin: 15px 0;">
                                    <strong>💡 Skills:</strong>
                                    <div class="skills-list">
                                        <?php 
                                        $skills = explode(',', $student['skills']);
                                        foreach ($skills as $skill): 
                                            $skill = trim($skill);
                                            if (!empty($skill)):
                                        ?>
                                            <a href="browse_students.php?skills=<?php echo urlencode($skill); ?>" 
                                               class="skill-tag clickable-skill" 
                                               title="Click to search for students with '<?php echo htmlspecialchars($skill); ?>' skill">
                                                <?php echo htmlspecialchars($skill); ?>
                                            </a>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <a href="send_offer.php?student_id=<?php echo $student['id']; ?>" class="btn btn-offer">
                                    💼 Send Job Offer
                                </a>
                                <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" class="btn btn-secondary">
                                    📧 Contact Student
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php endif; ?>

                <!-- Quick Actions -->
                <div style="text-align: center; margin-top: 30px;">
                    <a href="post_opportunity.php" class="btn btn-primary">💼 Post New Job</a>
                    <a href="dashboard.php" class="btn btn-secondary">📊 Back to Dashboard</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
