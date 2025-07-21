<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireLogin('student');

$error = '';
$success = '';

// Get current student data
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Failed to load profile data.';
    error_log($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $skills = sanitizeInput($_POST['skills']);
    $interests = sanitizeInput($_POST['interests']);
    $availability = sanitizeInput($_POST['availability']);
    $college = sanitizeInput($_POST['college']);
    $contact = sanitizeInput($_POST['contact']);
    
    if (empty($name) || empty($skills) || empty($interests) || empty($availability) || empty($college) || empty($contact)) {
        $error = 'All fields are required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE students SET name = ?, skills = ?, interests = ?, availability = ?, college = ?, contact = ? WHERE id = ?");
            $stmt->execute([$name, $skills, $interests, $availability, $college, $contact, $_SESSION['user_id']]);
            
            $_SESSION['user_name'] = $name;
            $success = 'Profile updated successfully!';
            
            // Refresh student data
            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $student = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'Failed to update profile. Please try again.';
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Skill2Startup</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
                        <li><a href="browse_jobs.php">Browse Jobs</a></li>
                        <li><a href="my_applications.php">My Applications</a></li>
                        <li><a href="offers.php">Job Offers</a></li>
                        <li><a href="profile.php" class="active">👤 Profile</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="card" style="max-width: 600px; margin: 2rem auto;">
                <div class="card-header">
                    <h2 class="card-title">My Profile</h2>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($student): ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Full Name:</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address:</label>
                            <input type="email" id="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['email']); ?>" 
                                   disabled>
                            <small class="text-muted">Email cannot be changed</small>
                        </div>

                        <div class="form-group">
                            <label for="college">College/University:</label>
                            <input type="text" id="college" name="college" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['college']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="contact">Contact Number:</label>
                            <input type="tel" id="contact" name="contact" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['contact']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="skills">Skills:</label>
                            <textarea id="skills" name="skills" class="form-control" rows="4" 
                                      maxlength="500" required><?php echo htmlspecialchars($student['skills']); ?></textarea>
                            <small class="text-muted">Separate multiple skills with commas</small>
                        </div>

                        <div class="form-group">
                            <label for="interests">Interests:</label>
                            <textarea id="interests" name="interests" class="form-control" rows="4" 
                                      maxlength="500" required><?php echo htmlspecialchars($student['interests']); ?></textarea>
                            <small class="text-muted">Describe your areas of interest</small>
                        </div>

                        <div class="form-group">
                            <label for="availability">Availability:</label>
                            <select id="availability" name="availability" class="form-control" required>
                                <option value="Part-time" <?php echo ($student['availability'] === 'Part-time') ? 'selected' : ''; ?>>Part-time</option>
                                <option value="Full-time" <?php echo ($student['availability'] === 'Full-time') ? 'selected' : ''; ?>>Full-time</option>
                                <option value="Internship" <?php echo ($student['availability'] === 'Internship') ? 'selected' : ''; ?>>Internship</option>
                                <option value="Project-based" <?php echo ($student['availability'] === 'Project-based') ? 'selected' : ''; ?>>Project-based</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Account Information:</label>
                            <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                                <p><strong>Member since:</strong> <?php echo date('F d, Y', strtotime($student['created_at'])); ?></p>
                                <p><strong>Email verified:</strong> 
                                    <span class="badge badge-<?php echo $student['is_verified'] ? 'approved' : 'pending'; ?>">
                                        <?php echo $student['is_verified'] ? 'Verified' : 'Not Verified'; ?>
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger">Unable to load profile data.</div>
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
