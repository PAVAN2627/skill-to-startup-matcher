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

// Check if job_id is provided
if (!isset($_GET['id']) && !isset($_GET['job_id'])) {
    header('Location: browse_startups.php');
    exit();
}

$job_id = (int)($_GET['id'] ?? $_GET['job_id']);

// Get comprehensive job details with startup information
$stmt = $pdo->prepare("
    SELECT jp.*, s.org_name, s.email as startup_email, s.logo, s.domain, s.website, 
           s.linkedin as startup_linkedin, s.address, s.description as startup_description
    FROM job_posts jp 
    JOIN startups s ON jp.startup_id = s.id 
    WHERE jp.id = ? AND jp.status = 'active'
");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    header('Location: browse_startups.php?error=job_not_found');
    exit();
}

// Get student data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log the submission attempt and POST data
    error_log("Job application submission - Student ID: {$student_id}, Job ID: {$job_id}");
    error_log("POST data keys: " . implode(', ', array_keys($_POST)));
    error_log("Job type: " . $job['type']);
    
    // Common fields for all application types
    $linkedin_profile = sanitizeInput($_POST['linkedin_profile'] ?? '');
    $github_profile = sanitizeInput($_POST['github_profile'] ?? '');
    $cover_letter = sanitizeInput($_POST['cover_letter'] ?? '');
    
    // Fields specific to job/internship applications
    $college_name = '';
    $degree = '';
    $year_of_passing = '';
    $years_experience = '';
    $why_hire_you = '';
    $resume_path = '';
    
    if ($job['type'] === 'job' || $job['type'] === 'internship') {
        $college_name = sanitizeInput($_POST['college_name'] ?? '');
        $degree = sanitizeInput($_POST['degree'] ?? '');
        $year_of_passing = sanitizeInput($_POST['year_of_passing'] ?? '');
        $years_experience = sanitizeInput($_POST['years_experience'] ?? '');
        $why_hire_you = sanitizeInput($_POST['why_hire_you'] ?? '');
        
        // Handle resume upload (required for jobs/internships)
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/resumes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'doc', 'docx'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'resume_' . $student_id . '_' . $job_id . '_' . time() . '.' . $file_extension;
                $resume_path = 'uploads/resumes/' . $new_filename;
                
                if (!move_uploaded_file($_FILES['resume']['tmp_name'], '../' . $resume_path)) {
                    $error = 'Failed to upload resume.';
                }
            } else {
                $error = 'Invalid file type. Please upload PDF, DOC or DOCX files only.';
            }
        } else {
            $error = 'Resume is required for job and internship applications.';
        }
    }
    
    // Validation
    if (empty($error)) {
        // Debug: Log what values we received
        error_log("Validation Debug - LinkedIn: '{$linkedin_profile}', GitHub: '{$github_profile}', Cover Letter length: " . strlen($cover_letter));
        
        if ($job['type'] === 'job' || $job['type'] === 'internship') {
            error_log("Job/Internship Debug - College: '{$college_name}', Degree: '{$degree}', Year: '{$year_of_passing}', Experience: '{$years_experience}', Why hire: length " . strlen($why_hire_you));
        }
        
        if (empty($linkedin_profile) || empty($github_profile) || empty($cover_letter)) {
            $error = 'Please fill in all required fields (LinkedIn, GitHub, Cover Letter).';
        } elseif (($job['type'] === 'job' || $job['type'] === 'internship') && 
                  (empty($college_name) || empty($degree) || empty($year_of_passing) || 
                   $years_experience === '' || $years_experience === null || empty($why_hire_you))) {
            $missing_fields = [];
            if (empty($college_name)) $missing_fields[] = 'College Name';
            if (empty($degree)) $missing_fields[] = 'Degree';
            if (empty($year_of_passing)) $missing_fields[] = 'Year of Passing';
            if ($years_experience === '' || $years_experience === null) $missing_fields[] = 'Years of Experience';
            if (empty($why_hire_you)) $missing_fields[] = 'Why should we hire you';
            
            $error = 'Please fill in all required fields for job/internship applications: ' . implode(', ', $missing_fields);
        }
    }
    
    if (empty($error)) {
        try {
            // Check if student has already applied to this specific job ID
            $check_stmt = $pdo->prepare("
                SELECT a.id, a.status, j.title, j.type 
                FROM applications a 
                JOIN job_posts j ON a.job_id = j.id 
                WHERE a.student_id = ? AND a.job_id = ?
            ");
            $check_stmt->execute([$student_id, $job_id]);
            $existing_app = $check_stmt->fetch();
            
            // Log the duplicate check for debugging
            error_log("Duplicate check: Student {$student_id}, Job {$job_id} (Type: {$job['type']}, Title: {$job['title']}), Found existing: " . ($existing_app ? 'YES' : 'NO'));
            
            if ($existing_app) {
                error_log("Student {$student_id} has already applied to Job {$job_id}. Status: {$existing_app['status']}");
                $error = "You have already applied to this position: '{$existing_app['title']}'. Status: " . ucfirst($existing_app['status']);
            }
            
            // Insert new application if no existing application for this specific job
            if (!$existing_app && empty($error) && empty($success)) {
                // Generate a unique application reference
                $application_ref = 'APP_' . $student_id . '_' . $job_id . '_' . time();
                
                // Check if application_id column exists
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO applications (
                            student_id, startup_id, job_id, application_id, cover_letter, 
                            linkedin_profile, github_profile, college_name, degree, 
                            year_of_passing, years_experience, why_hire_you, 
                            resume_path, status, applied_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    
                    $result = $stmt->execute([
                        $student_id, $job['startup_id'], $job_id, $application_ref, $cover_letter,
                        $linkedin_profile, $github_profile, $college_name, $degree,
                        $year_of_passing, $years_experience, $why_hire_you, $resume_path
                    ]);
                } catch (PDOException $e) {
                    // If application_id column doesn't exist, try without it
                    if ($e->getCode() == '42S22') {
                        error_log("application_id column missing, inserting without it");
                        $stmt = $pdo->prepare("
                            INSERT INTO applications (
                                student_id, startup_id, job_id, cover_letter, 
                                linkedin_profile, github_profile, college_name, degree, 
                                year_of_passing, years_experience, why_hire_you, 
                                resume_path, status, applied_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                        ");
                        
                        $result = $stmt->execute([
                            $student_id, $job['startup_id'], $job_id, $cover_letter,
                            $linkedin_profile, $github_profile, $college_name, $degree,
                            $year_of_passing, $years_experience, $why_hire_you, $resume_path
                        ]);
                    } else {
                        throw $e; // Re-throw if it's a different error
                    }
                }
                
                if ($result) {
                    $application_id = $pdo->lastInsertId();
                    error_log("NEW Application submitted successfully: App ID={$application_id}, Ref={$application_ref}, Student={$student_id}, Startup={$job['startup_id']}, Job={$job_id}");
                    $success = "Application submitted successfully! Your Application ID is: {$application_ref}. The startup will review your application.";
                } else {
                    $error = 'Failed to submit application. Please try again.';
                    error_log("Application insert failed for Student: {$student_id}, Job: {$job_id}");
                }
            }
        } catch (PDOException $e) {
            error_log('Application submission PDO error: ' . $e->getMessage() . " | Student: {$student_id}, Job: {$job_id}");
            error_log('PDO Error Code: ' . $e->getCode());
            error_log('PDO Error Info: ' . print_r($e->errorInfo, true));
            error_log('Full PDO Exception: ' . print_r($e, true));
            
            // Handle specific constraint violations
            if ($e->getCode() == 23000) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    // Log the exact constraint that's causing the issue
                    error_log('Duplicate entry detected. Full message: ' . $e->getMessage());
                    
                    // Check what kind of duplicate entry this is
                    if (strpos($e->getMessage(), 'unique_student_job') !== false || 
                        (strpos($e->getMessage(), 'student_id') !== false && strpos($e->getMessage(), 'job_id') !== false)) {
                        // This is a duplicate application to the same job
                        $error = 'You have already applied to this specific position.';
                    } elseif (strpos($e->getMessage(), 'application_id') !== false) {
                        // Duplicate application ID (shouldn't happen with timestamp)
                        $error = 'Application ID conflict. Please try again.';
                    } else {
                        // Log unknown duplicate for investigation but allow user to retry
                        error_log('Unknown duplicate entry type: ' . $e->getMessage());
                        $error = 'Unexpected error occurred. Please try again. If this continues, contact support.';
                    }
                } else {
                    $error = 'Database constraint error: ' . $e->getMessage();
                }
            } elseif ($e->getCode() == 42000) {
                $error = 'Database syntax error. Please contact support. Error: ' . $e->getMessage();
            } elseif ($e->getCode() == '42S22') {
                $error = 'Database column error. The system needs to be updated. Error: ' . $e->getMessage();
            } else {
                $error = 'Failed to submit application. Database error occurred. Error Code: ' . $e->getCode();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for <?php echo htmlspecialchars($job['title']); ?> - Student Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .job-details-container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .job-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.3);
            margin: 0 auto 20px;
            overflow: hidden;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .company-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .job-type-badge {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 25px;
            font-size: 0.9em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .job-details {
            padding: 40px;
        }
        
        .job-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin: 30px 0;
        }
        
        .job-info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #007bff;
        }
        
        .job-info-card h4 {
            margin: 0 0 10px 0;
            color: #007bff;
            font-size: 1.1em;
        }
        
        .event-image {
            width: 100%;
            max-width: 600px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .application-form {
            background: #f8f9fa;
            padding: 40px;
            border-top: 1px solid #eee;
        }
        
        .form-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: #007bff;
            outline: none;
        }
        
        .file-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            transition: border-color 0.3s;
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: #007bff;
        }
        
        .required {
            color: #dc3545;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 10px;
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
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                        <li><a href="browse_startups.php">Browse Opportunities</a></li>
                        <li><a href="my_applications.php">My Applications</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="job-details-container">
            <!-- Job Header with Company Info -->
            <div class="job-header">
                <div class="company-logo">
                    <?php if (!empty($job['logo'])): ?>
                        <img src="../<?php echo htmlspecialchars($job['logo']); ?>" alt="<?php echo htmlspecialchars($job['org_name']); ?>">
                    <?php else: ?>
                        <span style="font-size: 24px; color: white;"><?php echo strtoupper(substr($job['org_name'], 0, 2)); ?></span>
                    <?php endif; ?>
                </div>
                
                <h1><?php echo htmlspecialchars($job['title']); ?></h1>
                <h2><?php echo htmlspecialchars($job['org_name']); ?></h2>
                
                <div class="job-type-badge">
                    <?php echo ucfirst($job['type']); ?>
                </div>
            </div>

            <!-- Detailed Job Information -->
            <div class="job-details">
                <!-- Event Image for hackathons, workshops, events -->
                <?php if (in_array($job['type'], ['hackathon', 'workshop', 'event']) && !empty($job['event_images'])): ?>
                    <div style="text-align: center;">
                        <img src="../<?php echo htmlspecialchars($job['event_images']); ?>" alt="<?php echo htmlspecialchars($job['title']); ?>" class="event-image">
                    </div>
                <?php endif; ?>

                <!-- Job Information Grid -->
                <div class="job-info-grid">
                    <div class="job-info-card">
                        <h4>📍 Location</h4>
                        <p><?php echo htmlspecialchars($job['location'] ?? 'Remote/Flexible'); ?></p>
                    </div>
                    
                    <div class="job-info-card">
                        <h4>💰 <?php echo ($job['type'] === 'job' || $job['type'] === 'internship') ? 'Salary/Stipend' : 'Registration'; ?></h4>
                        <p><?php echo htmlspecialchars($job['salary'] ?? 'Not specified'); ?></p>
                    </div>
                    
                    <div class="job-info-card">
                        <h4>📅 Duration/Date</h4>
                        <p>
                            <?php if (!empty($job['application_deadline'])): ?>
                                Apply by: <?php echo date('F j, Y', strtotime($job['application_deadline'])); ?>
                            <?php else: ?>
                                Open Application
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="job-info-card">
                        <h4>🏢 Company Domain</h4>
                        <p><?php echo htmlspecialchars($job['domain'] ?? 'Technology'); ?></p>
                    </div>
                </div>

                <!-- Job Description -->
                <div class="job-info-card full-width">
                    <h4>📝 Description</h4>
                    <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                </div>

                <!-- Requirements -->
                <?php if (!empty($job['requirements'])): ?>
                <div class="job-info-card full-width">
                    <h4>✅ Requirements</h4>
                    <p><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></p>
                </div>
                <?php endif; ?>

                <!-- Company Information -->
                <div class="job-info-card full-width">
                    <h4>🏢 About <?php echo htmlspecialchars($job['org_name']); ?></h4>
                    <p><?php echo nl2br(htmlspecialchars($job['startup_description'] ?? 'Innovative startup looking for talented individuals.')); ?></p>
                    
                    <?php if (!empty($job['website'])): ?>
                        <p><strong>Website:</strong> <a href="<?php echo htmlspecialchars($job['website']); ?>" target="_blank"><?php echo htmlspecialchars($job['website']); ?></a></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($job['startup_linkedin'])): ?>
                        <p><strong>LinkedIn:</strong> <a href="<?php echo htmlspecialchars($job['startup_linkedin']); ?>" target="_blank">Company LinkedIn</a></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Application Form -->
            <div class="application-form">
                <h2>📝 Submit Your Application</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                        <br><br>
                        <a href="my_applications.php" class="btn btn-primary">📋 View My Applications</a>
                    </div>
                <?php else: ?>

                <form method="POST" enctype="multipart/form-data">
                    <!-- Common Fields for All Applications -->
                    <div class="form-section">
                        <h3>👤 Personal Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="linkedin_profile">LinkedIn Profile <span class="required">*</span></label>
                                <input type="url" id="linkedin_profile" name="linkedin_profile" class="form-control" 
                                       placeholder="https://linkedin.com/in/yourprofile" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="github_profile">GitHub Profile <span class="required">*</span></label>
                                <input type="url" id="github_profile" name="github_profile" class="form-control" 
                                       placeholder="https://github.com/yourusername" required>
                            </div>
                        </div>
                    </div>

                    <!-- Job/Internship Specific Fields -->
                    <?php if ($job['type'] === 'job' || $job['type'] === 'internship'): ?>
                    <div class="form-section">
                        <h3>🎓 Education & Experience</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="college_name">College/University <span class="required">*</span></label>
                                <input type="text" id="college_name" name="college_name" class="form-control" 
                                       placeholder="Your college/university name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="degree">Degree/Stream <span class="required">*</span></label>
                                <input type="text" id="degree" name="degree" class="form-control" 
                                       placeholder="e.g., B.Tech Computer Science" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="year_of_passing">Year of Passing <span class="required">*</span></label>
                                <input type="number" id="year_of_passing" name="year_of_passing" class="form-control" 
                                       min="2020" max="2030" placeholder="2025" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="years_experience">Years of Experience <span class="required">*</span></label>
                                <select id="years_experience" name="years_experience" class="form-control" required>
                                    <option value="">Select experience</option>
                                    <option value="0">Fresher (0 years)</option>
                                    <option value="1">1 year</option>
                                    <option value="2">2 years</option>
                                    <option value="3">3 years</option>
                                    <option value="4+">4+ years</option>
                                </select>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="resume">Resume <span class="required">*</span></label>
                                <div class="file-upload-area" onclick="document.getElementById('resume').click()">
                                    <p>📄 Click to upload your resume (PDF, DOC, DOCX)</p>
                                    <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" style="display: none;" required>
                                    <span id="file-name"></span>
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="why_hire_you">Why should we hire you? <span class="required">*</span></label>
                                <textarea id="why_hire_you" name="why_hire_you" class="form-control" rows="4" 
                                          placeholder="Tell us about your skills, achievements, and what makes you the right fit for this role..." required></textarea>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Cover Letter Section -->
                    <div class="form-section">
                        <h3>💌 
                            <?php if ($job['type'] === 'hackathon'): ?>
                                Why do you want to participate in this hackathon?
                            <?php elseif ($job['type'] === 'workshop' || $job['type'] === 'event'): ?>
                                Why do you want to attend this <?php echo $job['type']; ?>?
                            <?php else: ?>
                                Cover Letter
                            <?php endif; ?>
                        </h3>
                        
                        <div class="form-group">
                            <textarea id="cover_letter" name="cover_letter" class="form-control" rows="6" 
                                      placeholder="<?php 
                                      if ($job['type'] === 'hackathon') {
                                          echo 'Tell us about your coding experience, team collaboration skills, what you hope to build, and why this hackathon excites you...';
                                      } elseif ($job['type'] === 'workshop' || $job['type'] === 'event') {
                                          echo 'Explain your learning goals, relevant background, and how this ' . $job['type'] . ' fits your development...';
                                      } else {
                                          echo 'Write a compelling cover letter explaining your interest in this position and how you can contribute to the company...';
                                      } ?>" required></textarea>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div style="text-align: center; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary" style="padding: 15px 50px; font-size: 18px;">
                            🚀 Submit Application
                        </button>
                        <a href="browse_startups.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>

                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Skill2Startup. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // File upload handling
        document.getElementById('resume').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : '';
            document.getElementById('file-name').textContent = fileName ? 'Selected: ' + fileName : '';
        });
    </script>
</body>
</html>
