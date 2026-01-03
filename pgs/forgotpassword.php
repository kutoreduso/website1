<?php
// --- 1. ENABLE ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- 2. DATABASE CONNECTION ---
$conn = new mysqli("sql113.infinityfree.com", "if0_40771057", "keTpieWit7k", "if0_40771057_taskflow");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$msg = "";
$msg_type = "";

// --- 3. LOAD PHPMAILER (Adjusted for your screenshot) ---
// Your files are directly inside the PHPMailer folder, not inside 'src'
if (file_exists('PHPMailer/Exception.php')) {
    require 'PHPMailer/Exception.php';
    require 'PHPMailer/PHPMailer.php';
    require 'PHPMailer/SMTP.php';
} else {
    die("Error: Could not find PHPMailer files. I looked for 'pgs/PHPMailer/Exception.php' and could not find it.");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $token = bin2hex(random_bytes(16));
        $token_hash = hash("sha256", $token);
        $expiry = date("Y-m-d H:i:s", time() + 60 * 30); 

        $update = $conn->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE email = ?");
        $update->bind_param("sss", $token_hash, $expiry, $email);
        
        if ($update->execute()) {
            $mail = new PHPMailer(true);
            try {
                // --- SMTP SETTINGS (FILL THESE IN) ---
                $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    // REPLACE WITH YOUR DETAILS
                    $mail->Username   = 'kurtcantiga16@gmail.com'; 
                    $mail->Password   = 'fcrv xhmj aemj bidy'; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                $mail->setFrom('no-reply@taskflow.com', 'TaskFlow Security');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Reset Your Password';
                
                // FIX: Added 'pgs/' and added underscore to 'reset_password.php'
$resetLink = "http://taskflow.infinityfree.me/pgs/resetpassword.php?token=$token";
                
                $mail->Body    = "Click here to reset: <a href='$resetLink'>$resetLink</a>";

                $mail->send();
                $msg = "Reset link sent to your email.";
                $msg_type = "success";
            } catch (Exception $e) {
                $msg = "Mailer Error: {$mail->ErrorInfo}";
                $msg_type = "danger";
            }
        }
    } else {
        $msg = "Email not found.";
        $msg_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - TaskFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f3efdf; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-custom { background: #fbf9f1; padding: 3rem; border-radius: 2rem; width: 100%; max-width: 400px; text-align: center; }
        .btn-custom { background-color: #d9d9d9; border: none; padding: 10px; width: 100%; border-radius: 10px; font-weight: bold; transition: 0.3s; }
        .btn-custom:hover { background-color: #c0c0c0; }
        .form-control { border-radius: 8px; padding: 10px; }
    </style>
</head>
<body>
    <div class="card-custom">
        <h2 class="mb-3 fw-bold">Forgot Password</h2>
        <p class="text-muted small mb-4">Enter your email and we'll send you a reset link.</p>
        
        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> small p-2"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-4 text-start">
                <label class="fw-bold small text-muted">EMAIL ADDRESS</label>
                <input type="email" name="email" class="form-control" required placeholder="name@example.com">
            </div>
            <button type="submit" class="btn-custom">SEND LINK</button>
            <div class="mt-3">
                <a href="../index.php" class="text-decoration-none text-muted small fw-bold">Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>