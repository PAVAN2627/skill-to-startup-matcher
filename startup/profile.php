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

try {
    // Get startup data
    $stmt = $pdo->prepare("SELECT * FROM startups WHERE id = ?");
    $stmt->execute([$startup_id]);
    $startup = $stmt->fetch();
    
    if (!$startup) {
        header('Location: ../login.php');
        exit();
    }
    
} catch (PDOException $e) {
    $error = 'Failed to load profile data.';
    error_log($e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $org_name = sanitizeInput($_POST['org_name']);
    $domain = sanitizeInput($_POST['domain']);
    $description = sanitizeInput($_POST['description']);
    $address = sanitizeInput($_POST['address']);
    $website = sanitizeInput($_POST['website']);
    $linkedin = sanitizeInput($_POST['linkedin']);
    $instagram = sanitizeInput($_POST['instagram']);
    $twitter = sanitizeInput($_POST['twitter']);
    $founded_year = sanitizeInput($_POST['founded_year']);
    $team_size = sanitizeInput($_POST['team_size']);
    
    // Handle logo upload
    $logo_path = $startup['logo']; // Keep existing logo by default
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/logos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = 'logo_' . $startup_id . '_' . time() . '.' . $file_extension;
            $logo_path = 'uploads/logos/' . $new_filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], '../' . $logo_path)) {
                // Delete old logo if exists
                if ($startup['logo'] && file_exists('../' . $startup['logo'])) {
                    unlink('../' . $startup['logo']);
                }
            } else {
                $error = 'Failed to upload logo.';
            }
        } else {
            $error = 'Invalid file type. Please upload JPG, PNG or GIF files only.';
        }
    }
    
    if (empty($error)) {
        try {
            // First, let's check if all columns exist
            $test_query = $pdo->query("DESCRIBE startups");
            $columns = $test_query->fetchAll(PDO::FETCH_COLUMN);
            
            // Log available columns for debugging
            error_log("Available columns in startups table: " . implode(', ', $columns));
            
            $stmt = $pdo->prepare("
                UPDATE startups 
                SET org_name = ?, domain = ?, description = ?, logo = ?, address = ?, 
                    website = ?, linkedin = ?, instagram = ?, twitter = ?, 
                    founded_year = ?, team_size = ?
                WHERE id = ?
            ");
            
            $params = [
                $org_name, $domain, $description, $logo_path, $address,
                $website, $linkedin, $instagram, $twitter, 
                $founded_year, $team_size, $startup_id
            ];
            
            // Log the parameters for debugging
            error_log("Update parameters: " . print_r($params, true));
            
            $result = $stmt->execute($params);
            
            if ($result) {
                $success = 'Profile updated successfully!';
                
                // Refresh startup data
                $stmt = $pdo->prepare("SELECT * FROM startups WHERE id = ?");
                $stmt->execute([$startup_id]);
                $startup = $stmt->fetch();
            } else {
                $error = 'Failed to update the startup profile. Please check database correct all fields.';
                error_log("Update failed - no result from execute()");
            }
            
        } catch (PDOException $e) {
            $error = 'Failed to update the startup profile. Please check database correct all fields. Error: ' . $e->getMessage();
            error_log("Profile update error: " . $e->getMessage());
            error_log("Error code: " . $e->getCode());
            error_log("Error info: " . print_r($e->errorInfo, true));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Profile - Startup Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
        }
        .profile-form {
            padding: 40px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-full {
            grid-column: 1 / -1;
        }
        .logo-upload {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            overflow: hidden;
        }
        .logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .logo-placeholder {
            color: #666;
            font-size: 3em;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            outline: none;
        }
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        .social-input {
            position: relative;
        }
        .social-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        .social-input .form-control {
            padding-left: 40px;
        }
        .btn-upload {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-upload:hover {
            background: rgba(255,255,255,0.3);
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
                        <li><a href="post_job.php">Post Opportunities</a></li>
                        <li><a href="manage_jobs.php">Manage Posts</a></li>
                        <li><a href="view_applications.php">View Applications</a></li>
                        <li><a href="view_offers.php">Manage Offers</a></li>
                        <li><a href="profile.php" class="active">🏢 Company Profile</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container" style="margin-top: 20px;">
            <div class="profile-container">
                <div class="profile-header">
                    <div class="logo-upload">
                        <div class="logo-preview">
                            <?php if ($startup['logo']): ?>
                                <img src="../<?php echo htmlspecialchars($startup['logo']); ?>" alt="Company Logo" id="logoImg">
                            <?php else: ?>
                                <div class="logo-placeholder">🏢</div>
                            <?php endif; ?>
                        </div>
                        <h2><?php echo htmlspecialchars($startup['org_name']); ?></h2>
                        <p style="opacity: 0.9;"><?php echo htmlspecialchars($startup['domain']); ?></p>
                    </div>
                </div>

                <div class="profile-form">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group form-full">
                            <label for="logo">Company Logo:</label>
                            <input type="file" id="logo" name="logo" class="form-control" accept="image/*" onchange="previewLogo(this)">
                            <small style="color: #666;">Upload JPG, PNG or GIF files only. Max size: 2MB</small>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="org_name">Company Name: <span style="color: red;">*</span></label>
                                <input type="text" id="org_name" name="org_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($startup['org_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="domain">Industry/Domain: <span style="color: red;">*</span></label>
                                <input type="text" id="domain" name="domain" class="form-control" 
                                       value="<?php echo htmlspecialchars($startup['domain']); ?>" 
                                       placeholder="e.g., FinTech, EdTech, HealthTech" required>
                            </div>
                        </div>

                        <div class="form-group form-full">
                            <label for="description">Company Description: <span style="color: red;">*</span></label>
                            <textarea id="description" name="description" class="form-control" rows="5" 
                                      placeholder="Describe your company, mission, and what makes you unique..." required><?php echo htmlspecialchars($startup['description']); ?></textarea>
                        </div>

                        <div class="form-group form-full">
                            <label for="address">Company Address:</label>
                            <textarea id="address" name="address" class="form-control" rows="3" 
                                      placeholder="Complete address including city, state, country..."><?php echo htmlspecialchars($startup['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="founded_year">Founded Year:</label>
                                <input type="number" id="founded_year" name="founded_year" class="form-control" 
                                       value="<?php echo htmlspecialchars($startup['founded_year'] ?? ''); ?>" 
                                       min="1900" max="<?php echo date('Y'); ?>" placeholder="e.g., 2020">
                            </div>

                            <div class="form-group">
                                <label for="team_size">Team Size:</label>
                                <select id="team_size" name="team_size" class="form-control">
                                    <option value="">Select team size</option>
                                    <option value="1-10" <?php echo ($startup['team_size'] ?? '') === '1-10' ? 'selected' : ''; ?>>1-10 employees</option>
                                    <option value="11-50" <?php echo ($startup['team_size'] ?? '') === '11-50' ? 'selected' : ''; ?>>11-50 employees</option>
                                    <option value="51-200" <?php echo ($startup['team_size'] ?? '') === '51-200' ? 'selected' : ''; ?>>51-200 employees</option>
                                    <option value="201-500" <?php echo ($startup['team_size'] ?? '') === '201-500' ? 'selected' : ''; ?>>201-500 employees</option>
                                    <option value="500+" <?php echo ($startup['team_size'] ?? '') === '500+' ? 'selected' : ''; ?>>500+ employees</option>
                                </select>
                            </div>
                        </div>

                        <h3 style="margin: 30px 0 20px 0; color: #333;">🌐 Online Presence</h3>

                        <div class="form-group form-full">
                            <label for="website">Website URL:</label>
                            <div class="social-input">
                                <span class="social-icon">🌐</span>
                                <input type="url" id="website" name="website" class="form-control" 
                                       value="<?php echo htmlspecialchars($startup['website'] ?? ''); ?>" 
                                       placeholder="https://www.yourcompany.com">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="linkedin">LinkedIn Profile:</label>
                                <div class="social-input">
                                    <span class="social-icon">💼</span>
                                    <input type="url" id="linkedin" name="linkedin" class="form-control" 
                                           value="<?php echo htmlspecialchars($startup['linkedin'] ?? ''); ?>" 
                                           placeholder="https://linkedin.com/company/yourcompany">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="instagram">Instagram:</label>
                                <div class="social-input">
                                    <span class="social-icon">📸</span>
                                    <input type="url" id="instagram" name="instagram" class="form-control" 
                                           value="<?php echo htmlspecialchars($startup['instagram'] ?? ''); ?>" 
                                           placeholder="https://instagram.com/yourcompany">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="twitter">Twitter/X:</label>
                            <div class="social-input">
                                <span class="social-icon">🐦</span>
                                <input type="url" id="twitter" name="twitter" class="form-control" 
                                       value="<?php echo htmlspecialchars($startup['twitter'] ?? ''); ?>" 
                                       placeholder="https://twitter.com/yourcompany">
                            </div>
                        </div>

                        <div class="form-group form-full" style="margin-top: 30px;">
                            <button type="submit" class="btn btn-primary btn-lg">💾 Update Profile</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Skill2Startup. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function previewLogo(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const logoImg = document.getElementById('logoImg');
                    if (logoImg) {
                        logoImg.src = e.target.result;
                    } else {
                        const logoPreview = document.querySelector('.logo-preview');
                        logoPreview.innerHTML = '<img src="' + e.target.result + '" alt="Logo Preview" id="logoImg">';
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
