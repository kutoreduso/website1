<?php
session_start();

// 1. INCLUDE PHPMAILER
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/PHPMailer/Exception.php')) {
    require __DIR__ . '/PHPMailer/Exception.php';
    require __DIR__ . '/PHPMailer/PHPMailer.php';
    require __DIR__ . '/PHPMailer/SMTP.php';
}

// 2. DATABASE CONNECTION
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "taskflow_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// --- ACTION: ACCEPT USER & SEND EMAIL ---
if (isset($_GET['accept_id'])) {
    $id = $_GET['accept_id'];
    
    // Fetch user info
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $toEmail = $user['email'];
        $toName = $user['username'];

        // Activate User
        $update = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $update->bind_param("i", $id);
        
        if ($update->execute()) {
            $redirectMsg = "accepted"; 

            // Send Email
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    // REPLACE WITH YOUR DETAILS
                    $mail->Username   = 'kurtcantiga16@gmail.com'; 
                    $mail->Password   = 'fcrv xhmj aemj bidy'; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('no-reply@taskflow.com', 'TaskFlow Admin');
                    $mail->addAddress($toEmail, $toName);

                    $mail->isHTML(true);
                    $mail->Subject = 'Account Approved - TaskFlow';
                    $mail->Body    = "<h3>Welcome, $toName!</h3><p>Your registration for TaskFlow has been <b>APPROVED</b>.</p><p>Login here: <a href='http://localhost/website1/pgs/login.php'>Login</a></p>";

                    $mail->send();
                } catch (Exception $e) {
                    $redirectMsg = "accepted_no_email"; 
                }
            }
        }
    }
    header("Location: usermanagement.php?msg=" . $redirectMsg);
    exit();
}

// --- ACTION: DELETE/REJECT USER ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $conn->query("DELETE FROM users WHERE id = $id");
    header("Location: usermanagement.php?msg=deleted");
    exit();
}

// --- ACTION: UPDATE USER ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    $id = $_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=? WHERE id=?");
    $stmt->bind_param("sssi", $username, $email, $role, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: usermanagement.php?msg=updated");
    exit();
}

// FETCH DATA
$pendingUsers = [];
$res = $conn->query("SELECT * FROM users WHERE status = 'pending'");
while($row = $res->fetch_assoc()) $pendingUsers[] = $row;

$activeUsers = [];
$res2 = $conn->query("SELECT * FROM users WHERE status = 'active'");
while($row = $res2->fetch_assoc()) $activeUsers[] = $row;

$loggedInName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : "Admin";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        /* --- THEME COLORS & FONTS --- */
        :root { --bg-beige: #f3efdf; --sidebar-bg: #ffffff; --btn-beige: #f0ebd8; --btn-purple: #cec2ff; }
        body { background-color: var(--bg-beige); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }

        /* --- SIDEBAR (Fixed & Pinned Logout) --- */
        .sidebar {
            background-color: var(--sidebar-bg);
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0; left: 0;
            padding: 2rem;
            display: flex;
            flex-direction: column; 
            border-right: 1px solid #eaeaea;
            z-index: 1000;
        }
        .logo { font-size: 1.5rem; font-weight: 800; color: #333; margin-bottom: 3rem; display: flex; align-items: center; gap: 10px; }
        
        /* Navigation (Grows to push Logout down) */
        .nav-menu { flex-grow: 1; display: flex; flex-direction: column; gap: 15px; }

        .nav-btn {
            display: block; width: 100%; padding: 12px; border-radius: 12px;
            text-decoration: none; color: #555; font-weight: 600;
            background-color: var(--btn-beige); text-align: center; transition: 0.2s; border: none;
        }
        .nav-btn:hover { background-color: #e6e1cd; color: #000; }
        .nav-btn.active { background-color: var(--btn-purple); color: #000; }

        /* Logout Pinned to Bottom */
        .logout-btn {
            background-color: var(--btn-beige); color: #000; font-weight: bold;
            text-align: center; padding: 12px; border-radius: 12px;
            text-decoration: none; display: block; margin-top: auto;
        }
        .logout-btn:hover { background-color: #e6e1cd; }

        /* --- MAIN CONTENT --- */
        .main-content {
            margin-left: 250px; 
            padding: 3rem;
        }

        /* --- USER MANAGEMENT STYLES --- */
        .count-badge { background-color: #cbd5a1; color: #333; padding: 2px 8px; border-radius: 5px; font-size: 0.9rem; font-weight: bold; }
        .content-box { background: white; border-radius: 20px; padding: 2rem; min-height: 200px; margin-bottom: 2rem; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        
        /* User Card */
        .user-card { background-color: #dce2b8; border-radius: 15px; padding: 1.5rem; color: #222; position: relative; word-wrap: break-word; }
        .card-menu-btn { position: absolute; top: 15px; right: 15px; cursor: pointer; color: #444; background: none; border: none; padding: 0; }
        .dropdown-toggle::after { display: none; }

        /* Pending Requests Row */
        .pending-row { display: flex; justify-content: space-between; align-items: center; background-color: #f9f9f9; padding: 15px; border-radius: 10px; margin-bottom: 10px; border-left: 5px solid #cec2ff; }
        .btn-accept { background-color: #a8e6cf; color: #1b4d3e; border: none; padding: 5px 15px; border-radius: 8px; font-weight: bold; }
        .btn-reject { background-color: #ff8b94; color: #5c1e23; border: none; padding: 5px 15px; border-radius: 8px; font-weight: bold; }
        
        /* Mobile */
        @media (max-width: 991px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1.5rem; }
            .offcanvas-body { display: flex; flex-direction: column; }
        }
        
        @media (max-width: 768px) {
            .pending-row { flex-direction: column; align-items: flex-start; gap: 15px; }
            .pending-row > div:last-child { width: 100%; display: flex; gap: 10px; }
            .btn-accept, .btn-reject { flex: 1; padding: 10px; }
        }
    </style>
</head>
<body>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold"><i class="bi bi-kanban"></i> TaskFlow</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="nav-menu">
            <a href="admindashboard.php" class="nav-btn">DASHBOARD</a>
            <a href="admintask.php" class="nav-btn">TASK</a>
            <a href="#" class="nav-btn active">USERS</a>
            <a href="calendar.php" class="nav-btn">CALENDAR</a>
        </div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>
</div>

<div class="sidebar d-none d-lg-flex">
    <div class="logo"><i class="bi bi-kanban"></i> TaskFlow</div>
    <div class="nav-menu">
        <a href="admindashboard.php" class="nav-btn">DASHBOARD</a>
        <a href="admintask.php" class="nav-btn">TASK</a>
        <a href="#" class="nav-btn active">USERS</a>
        <a href="calendar.php" class="nav-btn">CALENDAR</a>
    </div>
    <a href="logout.php" class="logout-btn">Log Out</a>
</div>

<div class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <i class="bi bi-list fs-2 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" style="cursor: pointer;"></i>
        
        <div class="d-flex align-items-center ms-auto">
            <span class="fw-bold me-2 d-none d-sm-block"><?php echo $loggedInName; ?></span> 
            <i class="bi bi-person-circle fs-2"></i>
        </div>
    </div>

    <div class="fw-bold mb-2">REGISTRATION REQUESTS <span class="count-badge"><?php echo count($pendingUsers); ?></span></div>
    <div class="content-box">
        <?php if (count($pendingUsers) > 0): ?>
            <?php foreach ($pendingUsers as $user): ?>
                <div class="pending-row">
                    <div style="word-break: break-all;"> 
                        <strong><?php echo $user['username']; ?></strong><br>
                        <small class="text-muted"><?php echo $user['role']; ?> | <?php echo $user['email']; ?></small>
                    </div>
                    <div>
                        <button class="btn-accept" onclick="confirmAction('accept', <?php echo $user['id']; ?>)">Accept</button>
                        <button class="btn-reject" onclick="confirmAction('delete', <?php echo $user['id']; ?>)">Reject</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center text-muted">No pending request</p>
        <?php endif; ?>
    </div>

    <div class="fw-bold mb-2">ACTIVE USERS</div>
    <div class="content-box">
        <div class="row g-3">
            <?php foreach ($activeUsers as $user): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="user-card">
                        <div class="dropdown">
                            <button class="card-menu-btn" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>', '<?php echo $user['email']; ?>', '<?php echo $user['role']; ?>')"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="confirmAction('delete', <?php echo $user['id']; ?>)"><i class="bi bi-trash me-2"></i>Delete</a></li>
                            </ul>
                        </div>
                        
                        <h5><?php echo $user['username']; ?></h5>
                        <p class="mb-1 text-muted fw-bold"><?php echo $user['role']; ?></p>
                        <small style="word-break: break-all;"><?php echo $user['email']; ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"> 
        <div class="modal-content" style="background-color: #fbf9f1;">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="usermanagement.php">
                <div class="modal-body">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="user_id" id="edit_id">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Username</label>
                        <input type="text" class="form-control" name="username" id="edit_username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Role</label>
                        <select class="form-select" name="role" id="edit_role" required>
                            <option value="Senior Engineer">Senior Engineer</option>
                            <option value="Junior Engineer">Junior Engineer</option>
                            <option value="Technician">Technician</option>
                            <option value="Intern">Intern</option>
                            <option value="Head Department">Head Department</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success" style="background-color: #aebda0; border: none;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        
        if (msg === 'accepted') Swal.fire('Approved!', 'User approved & Email sent.', 'success');
        if (msg === 'accepted_no_email') Swal.fire('Approved!', 'User approved but Email FAILED to send.', 'warning');
        if (msg === 'deleted') Swal.fire('Deleted!', 'User removed.', 'success');
        if (msg === 'updated') Swal.fire('Updated!', 'User details saved.', 'success');

        if(msg) window.history.replaceState({}, document.title, "usermanagement.php");
    });

    function openEditModal(id, username, email, role) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_role').value = role;
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    }

    function confirmAction(action, id) {
        Swal.fire({
            title: action === 'accept' ? 'Accept User?' : 'Delete User?',
            text: action === 'accept' ? 'Send approval email?' : 'Cannot be undone!',
            icon: action === 'accept' ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            confirmButtonColor: action === 'accept' ? '#aebda0' : '#d33'
        }).then((res) => {
            if (res.isConfirmed) {
                let param = action === 'accept' ? 'accept_id' : 'delete_id';
                window.location.href = `usermanagement.php?${param}=${id}`;
            }
        });
    }
</script>
</body>
</html>