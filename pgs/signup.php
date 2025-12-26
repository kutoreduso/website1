<?php
session_start();

// DATABASE CONNECTION
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "taskflow_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $email = $_POST['email'];
    $position = $_POST['position']; // Maps to 'role' in DB
    $pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    // 1. Check Passwords Match
    if ($pass !== $confirm_pass) {
        $msg = "<div class='alert alert-danger'>Passwords do not match.</div>";
    } else {
        // 2. Check if User Exists
        $check = $conn->query("SELECT id FROM users WHERE username='$user' OR email='$email'");
        if ($check->num_rows > 0) {
            $msg = "<div class='alert alert-warning'>Username or Email already taken.</div>";
        } else {
            // 3. Insert User (Status = pending)
            // Note: In a real app, use password_hash($pass, PASSWORD_DEFAULT) here!
            $stmt = $conn->prepare("INSERT INTO users (username, email, role, password, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->bind_param("ssss", $user, $email, $position, $pass);
            
            if ($stmt->execute()) {
                $msg = "<div class='alert alert-success'>Registration successful! Wait for admin approval.</div>";
            } else {
                $msg = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f3efdf; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { 
            background: #fbf9f1; 
            border-radius: 2rem; 
            padding: 3rem; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            max-width: 450px;
            width: 100%;
        }
        .form-label { font-size: 0.75rem; font-weight: bold; color: #333; margin-bottom: 2px; text-transform: uppercase; }
        .input-group { border-bottom: 2px solid #333; margin-bottom: 1rem; }
        .form-control, .form-select { border: none; background: transparent; padding-left: 0; box-shadow: none; }
        .form-control:focus, .form-select:focus { background: transparent; box-shadow: none; }
        
        /* Dropdown custom style */
        .form-select { cursor: pointer; color: #555; }

        input[type="password"]::-ms-reveal, input[type="password"]::-ms-clear { display: none; }

        .btn-login { 
            background-color: #d9d9d9; 
            border: none; color: #333; font-weight: bold; padding: 0.8rem; border-radius: 10px; letter-spacing: 1px; margin-top: 10px;
        }
        .btn-login:hover { background-color: #c0c0c0; }
    </style>
</head>
<body>

<div class="login-card text-center">
    <h2 class="mb-4 fw-bold">Sign Up</h2>
    
    <?php echo $msg; ?>

    <form action="signup.php" method="POST">
        <div class="text-start mb-2">
            <label class="form-label">Username</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-0 ps-0"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control" placeholder="Create a username" required>
            </div>
        </div>

        <div class="text-start mb-2">
            <label class="form-label">Email</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-0 ps-0"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>
        </div>

        <div class="text-start mb-2">
            <label class="form-label">Position</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-0 ps-0"><i class="bi bi-briefcase"></i></span>
                <select name="position" class="form-select" required>
                    <option value="" selected disabled>Select your position</option>
                    <option value="Senior Engineer">Senior Engineer</option>
                    <option value="Junior Engineer">Junior Engineer</option>
                    <option value="Technician">Technician</option>
                    <option value="Intern">Intern</option>
                </select>
            </div>
        </div>

        <div class="text-start mb-2">
    <label class="form-label">Password</label>
    <div class="input-group">
        <span class="input-group-text bg-transparent border-0 ps-0"><i class="bi bi-lock"></i></span>
        
        <input type="password" name="password" id="pass1" class="form-control" placeholder="Create password" required>
        
        <span class="input-group-text bg-transparent border-0 pe-0" style="cursor: pointer;" onclick="togglePass('pass1', this)">
            <i class="bi bi-eye"></i>
        </span>
    </div>
</div>

<div class="text-start mb-4">
    <label class="form-label">Confirm Password</label>
    <div class="input-group">
        <span class="input-group-text bg-transparent border-0 ps-0"><i class="bi bi-lock-fill"></i></span>
        
        <input type="password" name="confirm_password" id="pass2" class="form-control" placeholder="Confirm password" required>
        
        <span class="input-group-text bg-transparent border-0 pe-0" style="cursor: pointer;" onclick="togglePass('pass2', this)">
            <i class="bi bi-eye"></i>
        </span>
    </div>
</div>

        <button type="submit" class="btn btn-login w-100 mb-4">SIGN UP</button>

        <p class="small text-muted">Already have an account? <a href="login.php" class="text-dark fw-bold text-decoration-none">Login</a></p>
    </form>
</div>

<script>
function togglePass(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}

function togglePass(inputId, toggleElement) {
    const input = document.getElementById(inputId);
    const icon = toggleElement.querySelector('i'); // Find the icon inside the clicked span

    // 1. Toggle Input Type (Password <-> Text)
    if (input.type === "password") {
        input.type = "text";
        
        // 2. Change Icon to "Eye Slash" (Hidden)
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = "password";
        
        // 3. Change Icon back to "Eye" (Visible)
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

</script>

</body>
</html>