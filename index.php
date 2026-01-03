<?php
session_start();
$conn = new mysqli("sql113.infinityfree.com", "if0_40771057", "keTpieWit7k", "if0_40771057_taskflow");

// 1. AUTO-REDIRECT IF ALREADY LOGGED IN
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'Administrator' || $_SESSION['user_role'] === 'Head Department') {
        header("Location: pgs/admindashboard.php");
    } else {
        header("Location: pgs/dashboard.php");
    }
    exit();
}

$error_msg = "";

// 2. HANDLE LOGIN FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if ($pass === $row['password']) { 
            if ($row['status'] === 'active') {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['username'];
                $_SESSION['user_role'] = $row['role'];

                if ($row['role'] === 'Administrator' || $row['role'] === 'Head Department') {
                    header("Location: pgs/admindashboard.php");
                } else {
                    header("Location: pgs/dashboard.php");
                }
                exit();
            } else {
                $error_msg = "Account not active yet.";
            }
        } else {
            $error_msg = "Wrong password.";
        }
    } else {
        $error_msg = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TaskFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bg-green: #abbea3; /* Sage Green */
            --bg-cream: #fbf9f1; /* Cream */
            --text-dark: #111111; 
        }

        /* 1. DESKTOP: Split Background Vertical (Left/Right) */
        body { 
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            /* Default Desktop Gradient: Left Green, Right Cream */
            background: linear-gradient(90deg, var(--bg-green) 50%, var(--bg-cream) 50%);
        }
        
        /* 2. Main Card Container */
        .login-card { 
            width: 100%;
            max-width: 950px; 
            min-height: 550px;
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            display: flex; 
            overflow: hidden; 
        }

        /* --- LEFT PANEL --- */
        .left-panel {
            background-color: var(--bg-green);
            width: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            padding: 2rem;
        }

        /* Logo Image Style */
        .login-logo-img {
            width: 150px; 
            height: auto;
            margin-bottom: 1rem;
            display: block;
        }

        .left-panel h1 {
            font-weight: 800;
            font-size: 2.5rem;
            margin-top: 0.5rem;
            letter-spacing: 1px;
        }

        /* --- RIGHT PANEL --- */
        .right-panel {
            background-color: var(--bg-cream);
            width: 50%;
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-title {
            font-weight: 800;
            font-size: 2.2rem;
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }

        /* Error Box Style */
        .alert-custom {
            background-color: #f8d7da;
            color: #58151c;
            border: 1px solid #f1aeb5;
            text-align: center;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        /* Form Labels */
        .form-label {
            font-weight: 800;
            font-size: 0.75rem;
            color: #111;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Input Fields */
        .input-group { margin-bottom: 15px; }
        .input-group-text {
            background: white;
            border: 1px solid #ddd;
            border-right: none;
            border-radius: 8px 0 0 8px;
            color: #555;
            padding-left: 15px;
        }
        .form-control {
            border: 1px solid #ddd;
            border-left: none;
            border-radius: 0 8px 8px 0;
            padding: 12px;
            font-size: 0.9rem;
            color: #333;
        }
        .form-control:focus { box-shadow: none; border-color: #aaa; }
        .input-group:focus-within .input-group-text,
        .input-group:focus-within .form-control { border-color: #888; }

        /* Button */
        .btn-login { 
            background-color: #d9d9d9;
            color: #000;
            border: none; 
            padding: 12px; 
            width: 100%; 
            border-radius: 8px; 
            font-weight: 800; 
            font-size: 1rem;
            margin-top: 10px;
            margin-bottom: 20px;
            text-transform: uppercase;
            transition: 0.2s;
        }
        .btn-login:hover { background-color: #c0c0c0; }

        /* Links */
        .forgot-link {
            font-size: 0.75rem;
            font-weight: 600;
            color: #111;
            text-decoration: none;
            display: block;
            text-align: right;
            margin-top: 2px;
            margin-bottom: 20px;
        }
        .signup-text { text-align: center; font-size: 0.85rem; color: #444; }
        .signup-link { color: #000; font-weight: 800; text-decoration: none; }

        /* --- MOBILE RESPONSIVE (Stacked View) --- */
        @media (max-width: 768px) {
            /* 1. Change background gradient to Top/Bottom split */
            body { 
                background: linear-gradient(180deg, var(--bg-green) 50%, var(--bg-cream) 50%);
                padding: 15px; /* Add some padding so card doesn't touch edges */
            }
            
            /* 2. Stack the card vertically */
            .login-card { 
                flex-direction: column; 
                max-width: 450px; 
                min-height: auto; /* Allow height to shrink */
            }

            /* 3. Show Left Panel (Green Top) */
            .left-panel { 
                width: 100%; 
                display: flex; /* Make sure it's visible */
                padding: 3rem 1rem;
            }

            /* 4. Adjust Right Panel (Cream Bottom) */
            .right-panel { 
                width: 100%; 
                padding: 2rem; 
                background: var(--bg-cream); 
            }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="left-panel">
            <img src="imgs/logo.png" alt="TaskFlow Logo" class="login-logo-img">
            
            <h1>Taskflow</h1>
        </div>

        <div class="right-panel">
            <h2 class="login-title">Login</h2>

            <?php if($error_msg): ?>
                <div class="alert-custom"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <form method="POST">
                <label class="form-label">USERNAME</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Type your username" required>
                </div>

                <label class="form-label">PASSWORD</label>
                <div class="input-group mb-1">
                    <span class="input-group-text"><i class="bi bi-eye"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Type your password" required>
                </div>

                <a href="pgs/forgotpassword.php" class="forgot-link">Forgot password?</a>

                <button type="submit" class="btn-login">LOGIN</button>

                <p class="signup-text">
                    don't have an account? <a href="pgs/signup.php" class="signup-link">Sign up</a>
                </p>
            </form>
        </div>
    </div>

</body>
</html>