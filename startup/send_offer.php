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
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$student_id) {
    header('Location: browse_students.php');
    exit();
}

// Get student information
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: browse_students.php');
    exit();
}

// Get startup information
$stmt = $pdo->prepare("SELECT * FROM startups WHERE id = ?");
$stmt->execute([$startup_id]);
$startup = $stmt->fetch();

// Get startup's job posts for dropdown
$stmt = $pdo->prepare("SELECT * FROM job_posts WHERE startup_id = ? AND status = 'active' ORDER BY created_at DESC");
$stmt->execute([$startup_id]);
$job_posts = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_id = !empty($_POST['job_id']) ? (int)$_POST['job_id'] : null;
    $offer_type = $_POST['offer_type'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $salary_range = trim($_POST['salary_range']);
    $location = trim($_POST['location']);
    $requirements = trim($_POST['requirements']);
    $benefits = trim($_POST['benefits']);
    $deadline = $_POST['deadline'];
    
    if (empty($title) || empty($description)) {
        $error = "Title and description are required.";
    } else {
        try {
            // Check if offer already exists for this student and job/startup
            $check_stmt = $pdo->prepare("
                SELECT id FROM job_offers 
                WHERE student_id = ? AND startup_id = ? AND (job_id = ? OR (job_id IS NULL AND ? IS NULL))
                AND status IN ('pending', 'accepted')
            ");
            $check_stmt->execute([$student_id, $startup_id, $job_id, $job_id]);
            
            if ($check_stmt->fetch()) {
                $error = "You have already sent an offer to this student for this position.";
            } else {
                // Insert new job offer
                $stmt = $pdo->prepare("
                    INSERT INTO job_offers (
                        startup_id, student_id, job_id, offer_type, title, description, 
                        salary_range, location, requirements, benefits, deadline, 
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                $stmt->execute([
                    $startup_id, $student_id, $job_id, $offer_type, $title, 
                    $description, $salary_range, $location, $requirements, 
                    $benefits, $deadline
                ]);
                
                $success = "Job offer sent successfully to " . htmlspecialchars($student['name']) . "!";
                
                // Optional: Send email notification (implement if needed)
                // sendOfferNotificationEmail($student['email'], $startup['org_name'], $title);
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Job Offer - Startup Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .offer-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .student-info {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .offer-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .skills-display {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        .skill-tag {
            background: #007bff;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .job-selection {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
                        <li><a href="browse_students.php">Browse Students</a></li>
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
            <div class="offer-container">
                <h2>💼 Send Job Offer</h2>
                <p>Send a personalized job offer to this talented student</p>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        ✅ <?php echo $success; ?>
                        <div style="margin-top: 10px;">
                            <a href="browse_students.php" class="btn btn-primary">Browse More Students</a>
                            <a href="view_offers.php" class="btn btn-secondary">View All Offers</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        ❌ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Student Information -->
                <div class="student-info">
                    <h3>👤 Student Information</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                        <div>
                            <strong>Name:</strong> <?php echo htmlspecialchars($student['name']); ?><br>
                            <strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?><br>
                            <?php if (isset($student['contact']) && $student['contact']): ?>
                                <strong>Contact:</strong> <?php echo htmlspecialchars($student['contact']); ?><br>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if (isset($student['college']) && $student['college']): ?>
                                <strong>College:</strong> <?php echo htmlspecialchars($student['college']); ?><br>
                            <?php endif; ?>
                            <?php if (isset($student['skills']) && $student['skills']): ?>
                                <strong>Skills:</strong> <?php echo htmlspecialchars($student['skills']); ?><br>
                            <?php endif; ?>
                            <?php if (isset($student['interests']) && $student['interests']): ?>
                                <strong>Interests:</strong> <?php echo htmlspecialchars($student['interests']); ?><br>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($student['skills']): ?>
                        <div style="margin-top: 15px;">
                            <strong>Skills:</strong>
                            <div class="skills-display">
                                <?php 
                                $skills = explode(',', $student['skills']);
                                foreach ($skills as $skill): 
                                    $skill = trim($skill);
                                    if (!empty($skill)):
                                ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Offer Form -->
                <div class="offer-form">
                    <h3>💼 Create Job Offer</h3>
                    
                    <form method="POST" action="">
                        <!-- Job Selection -->
                        <div class="job-selection">
                            <h4>Select Job Post (Optional)</h4>
                            <p>You can select an existing job post or create a custom offer below.</p>
                            
                            <div class="form-group">
                                <label for="job_id">Existing Job Post:</label>
                                <select id="job_id" name="job_id" class="form-control" onchange="fillFromJobPost(this)">
                                    <option value="">Create Custom Offer</option>
                                    <?php foreach ($job_posts as $job): ?>
                                        <option value="<?php echo $job['id']; ?>" 
                                                data-title="<?php echo htmlspecialchars($job['title']); ?>"
                                                data-description="<?php echo htmlspecialchars($job['description']); ?>"
                                                data-requirements="<?php echo htmlspecialchars($job['requirements']); ?>"
                                                data-location="<?php echo htmlspecialchars($job['location']); ?>"
                                                data-type="<?php echo htmlspecialchars($job['type']); ?>">
                                            <?php echo htmlspecialchars($job['title']); ?> (<?php echo ucfirst($job['type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Offer Type -->
                        <div class="form-group">
                            <label for="offer_type">Offer Type: *</label>
                            <select id="offer_type" name="offer_type" required class="form-control">
                                <option value="job">Full-time Job</option>
                                <option value="internship">Internship</option>
                                <option value="part-time">Part-time Position</option>
                                <option value="freelance">Freelance Project</option>
                                <option value="contract">Contract Work</option>
                            </select>
                        </div>

                        <!-- Basic Information -->
                        <div class="form-group">
                            <label for="title">Position Title: *</label>
                            <input type="text" id="title" name="title" required class="form-control"
                                   placeholder="e.g., Junior Software Developer">
                        </div>

                        <div class="form-group">
                            <label for="description">Job Description: *</label>
                            <textarea id="description" name="description" required class="form-control" rows="4"
                                      placeholder="Describe the role, responsibilities, and what makes this opportunity exciting..."></textarea>
                        </div>

                        <!-- Salary and Location -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="salary_range">Salary Range:</label>
                                <input type="text" id="salary_range" name="salary_range" class="form-control"
                                       placeholder="e.g., $50,000 - $70,000 per year">
                            </div>
                            <div class="form-group">
                                <label for="location">Location:</label>
                                <input type="text" id="location" name="location" class="form-control"
                                       placeholder="e.g., New York, NY or Remote">
                            </div>
                        </div>

                        <!-- Requirements -->
                        <div class="form-group">
                            <label for="requirements">Requirements:</label>
                            <textarea id="requirements" name="requirements" class="form-control" rows="3"
                                      placeholder="List the required skills, experience, and qualifications..."></textarea>
                        </div>

                        <!-- Benefits -->
                        <div class="form-group">
                            <label for="benefits">Benefits & Perks:</label>
                            <textarea id="benefits" name="benefits" class="form-control" rows="3"
                                      placeholder="Describe benefits, perks, learning opportunities, company culture..."></textarea>
                        </div>

                        <!-- Response Deadline -->
                        <div class="form-group">
                            <label for="deadline">Response Deadline:</label>
                            <input type="date" id="deadline" name="deadline" class="form-control"
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>

                        <!-- Submit Buttons -->
                        <div class="form-group" style="margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">💼 Send Job Offer</button>
                            <a href="browse_students.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        function fillFromJobPost(select) {
            const option = select.options[select.selectedIndex];
            if (option.value) {
                document.getElementById('title').value = option.dataset.title || '';
                document.getElementById('description').value = option.dataset.description || '';
                document.getElementById('requirements').value = option.dataset.requirements || '';
                document.getElementById('location').value = option.dataset.location || '';
                document.getElementById('offer_type').value = option.dataset.type || 'job';
            } else {
                // Clear fields for custom offer
                document.getElementById('title').value = '';
                document.getElementById('description').value = '';
                document.getElementById('requirements').value = '';
                document.getElementById('location').value = '';
            }
        }
    </script>
</body>
</html>
