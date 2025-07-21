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
$error = '';
$success = '';

// Get opportunity type from URL parameter
$opportunity_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'job';
$valid_types = ['job', 'internship', 'hackathon', 'workshop', 'event'];
if (!in_array($opportunity_type, $valid_types)) {
    $opportunity_type = 'job';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $type = sanitizeInput($_POST['type']);
    $requirements = sanitizeInput($_POST['requirements']);
    $required_skills = sanitizeInput($_POST['required_skills']);
    $location = sanitizeInput($_POST['location']);
    $salary = sanitizeInput($_POST['salary']);
    $duration = sanitizeInput($_POST['duration']);
    $deadline = $_POST['deadline'];
    
    // Handle event images upload
    $event_images = '';
    if (isset($_FILES['event_images']) && !empty($_FILES['event_images']['name'][0])) {
        $upload_dir = '../uploads/events/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $uploaded_images = [];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        for ($i = 0; $i < count($_FILES['event_images']['name']); $i++) {
            if ($_FILES['event_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_extension = strtolower(pathinfo($_FILES['event_images']['name'][$i], PATHINFO_EXTENSION));
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'event_' . $startup_id . '_' . time() . '_' . $i . '.' . $file_extension;
                    $image_path = 'uploads/events/' . $new_filename;
                    
                    if (move_uploaded_file($_FILES['event_images']['tmp_name'][$i], '../' . $image_path)) {
                        $uploaded_images[] = $image_path;
                    }
                }
            }
        }
        
        if (!empty($uploaded_images)) {
            $event_images = implode(',', $uploaded_images);
        }
    }
    
    if (empty($title) || empty($description) || empty($type)) {
        $error = 'Please fill in all required fields.';
    } elseif (($type === 'job' || $type === 'internship') && empty($required_skills)) {
        $error = 'Please specify the required technical skills for this ' . $type . '.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO job_posts (startup_id, title, description, type, requirements, required_skills, event_images, location, salary, duration, deadline, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$startup_id, $title, $description, $type, $requirements, $required_skills, $event_images, $location, $salary, $duration, $deadline]);
            
            $type_label = ucfirst($type);
            $success = "$type_label posting created successfully! Students can now apply.";
        } catch (PDOException $e) {
            $error = 'Failed to create posting. Please try again.';
            error_log("Job posting error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post <?php echo ucfirst($opportunity_type); ?> - Startup Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .post-form {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-full {
            grid-column: 1 / -1;
        }
        textarea {
            min-height: 120px;
        }
        .job-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .job-type-option {
            border: 2px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .job-type-option:hover {
            border-color: #007bff;
            background: #f0f8ff;
            text-decoration: none;
            color: inherit;
        }
        .job-type-option.selected {
            border-color: #007bff;
            background: #007bff;
            color: white;
        }
        .job-type-option.selected:hover {
            color: white;
        }
        .job-type-option input[type="radio"] {
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
                        <li><a href="browse_students.php">Browse Students</a></li>
                        <li><a href="post_job.php" class="active">Post Opportunity</a></li>
                        <li><a href="manage_jobs.php">Manage Posts</a></li>
                        <li><a href="view_applications.php">View Applications</a></li>
                        <li><a href="view_offers.php">Manage Offers</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <div class="post-form">
                <h2>📝 Post New Opportunity</h2>
                <p>Create opportunities for students to grow and learn</p>

                <!-- Opportunity Type Selector -->
                <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                    <h4 style="margin-bottom: 15px;">Select Opportunity Type:</h4>
                    <div class="job-type-grid">
                        <a href="post_job.php?type=job" class="job-type-option <?php echo $opportunity_type === 'job' ? 'selected' : ''; ?>">
                            <div style="font-size: 2em; margin-bottom: 10px;">💼</div>
                            <strong>Job</strong>
                            <div style="font-size: 0.9em; color: #666;">Full-time/Part-time positions</div>
                        </a>
                        <a href="post_job.php?type=internship" class="job-type-option <?php echo $opportunity_type === 'internship' ? 'selected' : ''; ?>">
                            <div style="font-size: 2em; margin-bottom: 10px;">🎓</div>
                            <strong>Internship</strong>
                            <div style="font-size: 0.9em; color: #666;">Learning opportunities</div>
                        </a>
                        <a href="post_job.php?type=hackathon" class="job-type-option <?php echo $opportunity_type === 'hackathon' ? 'selected' : ''; ?>">
                            <div style="font-size: 2em; margin-bottom: 10px;">🚀</div>
                            <strong>Hackathon</strong>
                            <div style="font-size: 0.9em; color: #666;">Coding competitions</div>
                        </a>
                        <a href="post_job.php?type=workshop" class="job-type-option <?php echo $opportunity_type === 'workshop' ? 'selected' : ''; ?>">
                            <div style="font-size: 2em; margin-bottom: 10px;">🎯</div>
                            <strong>Workshop</strong>
                            <div style="font-size: 0.9em; color: #666;">Skill development sessions</div>
                        </a>
                        <a href="post_job.php?type=event" class="job-type-option <?php echo $opportunity_type === 'event' ? 'selected' : ''; ?>">
                            <div style="font-size: 2em; margin-bottom: 10px;">📅</div>
                            <strong>Event</strong>
                            <div style="font-size: 0.9em; color: #666;">Networking & seminars</div>
                        </a>
                    </div>
                </div>

                <h3>📝 Post New <?php echo ucfirst($opportunity_type); ?></h3>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                        <br><br>
                        <a href="manage_jobs.php" class="btn btn-primary">View All Posts</a>
                        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                <?php else: ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group form-full">
                            <label for="title"><?php echo ucfirst($opportunity_type); ?> Title: <span style="color: red;">*</span></label>
                            <input type="text" id="title" name="title" class="form-control" 
                                   placeholder="e.g., Frontend Developer Internship, ML Hackathon, React Workshop" 
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                        </div>

                        <!-- Hidden field for opportunity type -->
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($opportunity_type); ?>">

                        <div class="form-group form-full">
                            <label for="description">Description: <span style="color: red;">*</span></label>
                            <textarea id="description" name="description" class="form-control" rows="5"
                                      placeholder="Describe the <?php echo $opportunity_type; ?>, responsibilities, requirements, what students will learn..." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>

                        <div class="form-group form-full">
                            <label for="requirements"><?php echo $opportunity_type === 'hackathon' || $opportunity_type === 'workshop' || $opportunity_type === 'event' ? 'Participation Requirements:' : 'Job Requirements:'; ?></label>
                            <textarea id="requirements" name="requirements" class="form-control" rows="3"
                                      placeholder="<?php echo $opportunity_type === 'hackathon' ? 'e.g., Basic programming knowledge, team registration required...' : 'e.g., Bachelor\'s degree, 2+ years experience, strong communication skills...'; ?>"><?php echo isset($_POST['requirements']) ? htmlspecialchars($_POST['requirements']) : ''; ?></textarea>
                        </div>

                        <?php if ($opportunity_type === 'job' || $opportunity_type === 'internship'): ?>
                        <div class="form-group form-full">
                            <label for="required_skills">Required Technical Skills: <span style="color: red;">*</span></label>
                            <textarea id="required_skills" name="required_skills" class="form-control" rows="2"
                                      placeholder="e.g., React, Node.js, Python, Machine Learning, UI/UX Design, JavaScript, SQL..." required><?php echo isset($_POST['required_skills']) ? htmlspecialchars($_POST['required_skills']) : ''; ?></textarea>
                            <small style="color: #666;">Separate skills with commas. This helps us match with student profiles!</small>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="required_skills" value="">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="location">Location:</label>
                            <input type="text" id="location" name="location" class="form-control" 
                                   placeholder="<?php echo $opportunity_type === 'hackathon' || $opportunity_type === 'workshop' ? 'e.g., Online, Mumbai, Delhi' : 'e.g., Remote, Mumbai, Bangalore'; ?>" 
                                   value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="duration"><?php echo $opportunity_type === 'hackathon' ? 'Event Duration:' : 'Duration:'; ?></label>
                            <input type="text" id="duration" name="duration" class="form-control" 
                                   placeholder="<?php echo $opportunity_type === 'hackathon' ? 'e.g., 48 hours, 3 days' : 'e.g., 3 months, 6 months, Full-time'; ?>" 
                                   value="<?php echo isset($_POST['duration']) ? htmlspecialchars($_POST['duration']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="salary"><?php echo $opportunity_type === 'hackathon' ? 'Prize/Rewards:' : 'Compensation:'; ?></label>
                            <input type="text" id="salary" name="salary" class="form-control" 
                                   placeholder="<?php echo $opportunity_type === 'hackathon' ? 'e.g., Prize pool ₹1L, Certificates, Swag' : 'e.g., ₹15,000/month, Unpaid, ₹500/day'; ?>" 
                                   value="<?php echo isset($_POST['salary']) ? htmlspecialchars($_POST['salary']) : ''; ?>">
                        </div>

                        <?php if ($opportunity_type === 'hackathon' || $opportunity_type === 'workshop' || $opportunity_type === 'event'): ?>
                        <div class="form-group form-full">
                            <label for="event_images"><?php echo ucfirst($opportunity_type); ?> Images:</label>
                            <input type="file" id="event_images" name="event_images[]" class="form-control" 
                                   accept="image/*" multiple>
                            <small style="color: #666;">Upload multiple images to showcase your <?php echo $opportunity_type; ?>. Supported formats: JPG, PNG, GIF</small>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="deadline"><?php echo $opportunity_type === 'hackathon' || $opportunity_type === 'workshop' ? 'Registration Deadline:' : 'Application Deadline:'; ?></label>
                            <input type="date" id="deadline" name="deadline" class="form-control" 
                                   value="<?php echo isset($_POST['deadline']) ? $_POST['deadline'] : ''; ?>" 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group form-full">
                            <button type="submit" class="btn btn-primary btn-lg">🚀 Post <?php echo ucfirst($opportunity_type); ?></button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </form>

                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function selectJobType(type) {
            // Remove selected class from all options
            document.querySelectorAll('.job-type-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.getElementById(type).checked = true;
        }

        // Set minimum date to today
        document.getElementById('deadline').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
