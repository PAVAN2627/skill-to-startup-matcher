<?php
session_start();
require_once 'includes/db.php';

$error = '';
$success = '';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    $user_type = $_SESSION['user_type'];
    if ($user_type === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($user_type === 'student') {
        header('Location: student/dashboard.php');
    } elseif ($user_type === 'startup') {
        header('Location: startup/dashboard.php');
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $user_type = isset($_POST['user_type']) ? $_POST['user_type'] : '';
    
    if (empty($email) || empty($password) || empty($user_type)) {
        $error = 'All fields are required.';
    } else {
        try {
            if ($user_type === 'student') {
                $stmt = $pdo->prepare("SELECT id, name, password, is_verified FROM students WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if ($user['is_verified']) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_type'] = 'student';
                        $_SESSION['user_name'] = $user['name'];
                        header('Location: student/dashboard.php');
                        exit();
                    } else {
                        $error = 'Please verify your email before logging in.';
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } elseif ($user_type === 'startup') {
                $stmt = $pdo->prepare("SELECT id, org_name, password, is_verified, is_approved FROM startups WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if ($user['is_verified'] && $user['is_approved']) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_type'] = 'startup';
                        $_SESSION['user_name'] = $user['org_name'];
                        header('Location: startup/dashboard.php');
                        exit();
                    } else if (!$user['is_verified']) {
                        $error = 'Please verify your email before logging in.';
                    } else if (!$user['is_approved']) {
                        $error = 'Your startup account is pending admin approval.';
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Skill2Startup</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            font-size: 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
        }

        .register-links {
            text-align: center;
            margin-top: 20px;
        }

        .register-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
        }

        .register-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Skill2Startup</h1>
            <p>Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="user_type">I am a:</label>
                <select name="user_type" id="user_type" required>
                    <option value="">Select your account type</option>
                    <option value="student" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'student') ? 'selected' : ''; ?>>🎓 Student</option>
                    <option value="startup" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'startup') ? 'selected' : ''; ?>>🚀 Startup</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" name="password" id="password" required>
            </div>

            <button type="submit" class="btn">Sign In</button>
        </form>

        <div class="register-links">
            <p>Don't have an account?</p>
            <a href="student/register.php">Register as Student</a> |
            <a href="startup/register.php">Register as Startup</a>
        </div>
    </div>
</body>
</html>
