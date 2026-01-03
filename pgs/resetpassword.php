<?php
// reset_password.php
session_start();
$conn = new mysqli("sql113.infinityfree.com", "if0_40771057", "keTpieWit7k", "if0_40771057_taskflow");

$msg = "";
$msg_type = "";
$token = $_GET['token'] ?? "";

if (!$token) {
    die("Invalid request.");
}

$token_hash = hash("sha256", $token);

// CHECK IF TOKEN IS VALID
$stmt = $conn->prepare("SELECT * FROM users WHERE reset_token_hash = ? AND reset_token_expires_at > NOW()");
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>Link is invalid or has expired. <a href='forgot_password.php'>Try again</a></div>");
}

// HANDLE PASSWORD UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    if ($pass1 === $pass2) {
     
        $new_password = $pass1; 

        $update = $conn->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?");
        $update->bind_param("si", $new_password, $user['id']);
        
        if ($update->execute()) {
            $msg = "Password updated! Redirecting...";
            $msg_type = "success";
            header("refresh:2;url=../index.php"); // Redirect to login
        } else {
            $msg = "Database error.";
            $msg_type = "danger";
        }
    } else {
        $msg = "Passwords do not match.";
        $msg_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - TaskFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f3efdf; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-custom { background: #fbf9f1; padding: 3rem; border-radius: 2rem; width: 100%; max-width: 400px; text-align: center; }
        .btn-custom { background-color: #aebda0; color: white; border: none; padding: 10px; width: 100%; border-radius: 10px; font-weight: bold; transition: 0.3s; }
        .btn-custom:hover { background-color: #9da990; }
        .form-control { border-radius: 8px; padding: 10px; }
    </style>
</head>
<body>
    <div class="card-custom">
        <h2 class="mb-3 fw-bold">Reset Password</h2>
        
        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> small p-2"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3 text-start">
                <label class="fw-bold small text-muted">NEW PASSWORD</label>
                <input type="password" name="pass1" class="form-control" required minlength="4">
            </div>
            <div class="mb-4 text-start">
                <label class="fw-bold small text-muted">CONFIRM PASSWORD</label>
                <input type="password" name="pass2" class="form-control" required minlength="4">
            </div>
            <button type="submit" class="btn-custom">CHANGE PASSWORD</button>
        </form>
    </div>
</body>
</html>