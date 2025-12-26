<?php
session_start();
$conn = new mysqli("localhost", "root", "", "taskflow_db");

// If already logged in, skip login page
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'Admin' || $_SESSION['user_role'] === 'Head Department') {
        header("Location: admindashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // Check Database
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        // VERIFY PASSWORD
        if ($pass === $row['password']) {
            if ($row['status'] === 'active') {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['username'];
                $_SESSION['user_role'] = $row['role'];

               // Change 'Admin' to 'Administrator' to match your database
if ($row['role'] === 'Administrator' || $row['role'] === 'Head Department') {
    header("Location: admindashboard.php");
} else {
    header("Location: dashboard.php");
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
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f3efdf; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: #fbf9f1; padding: 3rem; border-radius: 2rem; width: 100%; max-width: 400px; text-align: center; }
        .btn-login { background-color: #d9d9d9; border: none; padding: 10px; width: 100%; border-radius: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2 class="mb-4">Login</h2>
        <?php if($error_msg): ?><div class="alert alert-danger"><?php echo $error_msg; ?></div><?php endif; ?>
        
        <form method="POST">
            <div class="mb-3 text-start">
                <label class="fw-bold small">USERNAME</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-4 text-start">
                <label class="fw-bold small">PASSWORD</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn-login">LOGIN</button>
            <p class="mt-3 small">Don't have an account? <a href="signup.php" class="text-dark fw-bold">Sign up</a></p>
        </form>
    </div>
</body>
</html>