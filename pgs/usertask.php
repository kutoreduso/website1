<?php
session_start();



// 2. DATABASE CONNECTION (Using your credentials)
$servername = "sql113.infinityfree.com";
$username = "if0_40771057";
$password = "keTpieWit7k";
$dbname = "if0_40771057_taskflow"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$my_id = $_SESSION['user_id'];
$loggedInName = $_SESSION['user_name'];

// --- HANDLE STATUS CHANGE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?");
    $stmt->bind_param("sii", $new_status, $task_id, $my_id);
    $stmt->execute();
    
    header("Location: usertask.php?msg=status_updated");
    exit();
}

// --- HANDLE EXTENSION REQUEST ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_extension'])) {
    $task_id = $_POST['task_id'];
    $days = $_POST['extension_days']; 
    
    $note_append = "\n[System]: Requested extension of +$days days.";
    
    $stmt = $conn->prepare("UPDATE tasks SET status = 'Requesting Extension', description = CONCAT(description, ?) WHERE id = ? AND assigned_to = ?");
    $stmt->bind_param("sii", $note_append, $task_id, $my_id);
    $stmt->execute();
    
    header("Location: usertask.php?msg=requested");
    exit();
}

// --- FETCH MY TASKS ---
$myTasks = $conn->prepare("SELECT * FROM tasks WHERE assigned_to = ? ORDER BY deadline ASC");
$myTasks->bind_param("i", $my_id);
$myTasks->execute();
$result = $myTasks->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - TaskFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        /* --- THEME COLORS --- */
        :root { --bg-color: #f3efdf; --sidebar-bg: #ffffff; --card-bg: #dce2b8; --item-bg: #f9f9f9; --btn-purple: #cec2ff; --btn-beige: #f0ebd8; }
        body { background-color: var(--bg-color); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }

        /* --- SIDEBAR (FIXED LAYOUT) --- */
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

        .logo { font-size: 1.5rem; font-weight: bold; margin-bottom: 3rem; display: flex; align-items: center; gap: 10px; color: #333; }

        .nav-menu {
            flex-grow: 1; /* Pushes logout down */
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .nav-btn {
            display: block; width: 100%; padding: 12px; border-radius: 12px;
            text-decoration: none; color: #666; font-weight: 600;
            background-color: var(--btn-beige); text-align: center;
            transition: 0.2s; border: none;
        }
        .nav-btn:hover { background-color: #e6e1cd; color: #333; }
        .nav-btn.active { background-color: var(--btn-purple); color: #333; }

        /* Pinned Logout Button */
        .logout-btn {
            background-color: var(--btn-beige); color: #000; font-weight: bold;
            text-align: center; padding: 12px; border-radius: 12px;
            text-decoration: none; display: block; margin-top: auto; 
        }
        .logout-btn:hover { background-color: #e6e1cd; }

        /* --- MAIN CONTENT --- */
        .main-container { 
            padding: 2rem; 
            margin-left: 250px; /* Space for Sidebar */
        }

        /* --- TASK LIST DESIGN --- */
        .task-container { background-color: var(--card-bg); border-radius: 20px; padding: 2rem; min-height: 500px; }
        
        /* Desktop Grid Layout */
        .task-grid-header { display: grid; grid-template-columns: 2fr 3fr 2fr 1fr 1.5fr 0.5fr; font-weight: bold; padding: 0 15px; margin-bottom: 15px; color: #1f1f1f; }
        .task-row { display: grid; grid-template-columns: 2fr 3fr 2fr 1fr 1.5fr 0.5fr; background-color: var(--item-bg); border-radius: 12px; padding: 15px; align-items: center; margin-bottom: 10px; font-size: 0.9rem; }
        
        .status-select { border: none; background-color: transparent; font-weight: bold; color: #d4a017; cursor: pointer; width: 100%; }
        .status-select:focus { outline: none; }
        .view-link { color: #9c27b0; text-decoration: none; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; cursor: pointer; }
        .dropdown-toggle::after { display: none; }
        .three-dots-btn { background: none; border: none; padding: 0; color: #333; font-size: 1.2rem; }
        
        .text-overdue { color: #dc3545 !important; font-weight: bold; } 

        .mobile-toggle { font-size: 1.5rem; cursor: pointer; color: #333; margin-right: auto; }
        
        /* --- RESPONSIVE CSS (MOBILE) --- */
        @media(max-width: 991px) { 
            .sidebar { display: none; } 
            .main-container { margin-left: 0; padding: 1rem; }
            .offcanvas-body { display: flex; flex-direction: column; }
            
            /* Responsive Task List: Stack items vertically on mobile */
            .task-grid-header { display: none; } /* Hide Header */
            
            .task-row {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                position: relative; /* For absolute menu positioning */
            }
            
            .task-row > span, .task-row > div { width: 100%; }
            
            /* User Name Style on Mobile */
            .task-row span:first-child { font-size: 1.1rem; margin-bottom: 5px; }
            
            /* Menu Button Top-Right */
            .task-row .dropdown.text-end {
                position: absolute;
                top: 10px;
                right: 10px;
                width: auto;
            }
            
            /* Add labels for clarity */
            .task-row > span:nth-child(2)::before { content: "Task: "; font-weight: bold; color: #777; }
            .task-row > span:nth-child(5)::before { content: "Deadline: "; font-weight: bold; color: #777; }
        }
        .sidebar-logo-img {
    width: 60px;       /* Adjust width as needed */
    height: auto;       /* Keeps the aspect ratio */
    display: block;     /* Removes extra space below image */
    margin: 0 auto;     /* Centers the image horizontally */
}

/* Ensure the container centers content */
.logo {
    display: flex;
    justify-content: center;
    margin-bottom: 2rem;
}
    </style>
</head>
<body>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header"><h5 class="offcanvas-title fw-bold">TaskFlow</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body">
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-btn">DASHBOARD</a>
            <a href="#" class="nav-btn active">TASK</a>

        </div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>
</div>

<div class="sidebar d-none d-lg-flex">
    <div class="logo">
        <img src="../imgs/logo.png" alt="TaskFlow Logo" class="sidebar-logo-img">
        TaskFlow
    </div>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-btn">DASHBOARD</a>
        <a href="#" class="nav-btn active">TASK</a>

    </div>
    <a href="logout.php" class="logout-btn">Log Out</a>
</div>

<div class="main-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <i class="bi bi-list mobile-toggle d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"></i>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="fw-bold d-none d-sm-block"><?php echo $loggedInName; ?></span>
            <i class="bi bi-person-circle fs-2"></i>
        </div>
    </div>

    <div class="task-container">
        <div class="task-grid-header">
            <span>Assigned name</span><span>Task</span><span>Status</span><span>Notes</span><span>Deadline</span><span></span>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): 
                $isOverdue = ($row['deadline'] < date('Y-m-d')) && ($row['status'] !== 'Completed');
                $deadlineClass = $isOverdue ? 'text-overdue' : '';
                $warningIcon = $isOverdue ? '<i class="bi bi-exclamation-circle-fill me-1"></i>' : '';
            ?>
                <div class="task-row">
                    <span class="fw-bold"><?php echo $loggedInName; ?></span>
                    <span><?php echo $row['project']; ?></span>

                    <div>
                        <?php if ($isOverdue): ?>
                            <span class="fw-bold text-danger text-uppercase">Deadline</span>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="update_status" value="1">
                                <input type="hidden" name="task_id" value="<?php echo $row['id']; ?>">
                                <select name="status" class="status-select" onchange="this.form.submit()">
                                    <option value="Pending" <?php if($row['status']=='Pending') echo 'selected'; ?>>Pending</option>
                                    <option value="In Progress" <?php if($row['status']=='In Progress') echo 'selected'; ?>>In Progress</option>
                                    <option value="Completed" <?php if($row['status']=='Completed') echo 'selected'; ?>>Completed</option>
                                    <option value="Requesting Extension" <?php if($row['status']=='Requesting Extension') echo 'selected'; ?> disabled>Requesting Ext...</option>
                                </select>
                            </form>
                        <?php endif; ?>
                    </div>

                    <span class="view-link" onclick="viewNotes('<?php echo addslashes($row['description'] ?? ''); ?>')">VIEW</span>
                    
                    <span class="<?php echo $deadlineClass; ?>">
                        <?php echo $warningIcon . date("M/d/Y", strtotime($row['deadline'])); ?>
                    </span>

                    <div class="dropdown text-end">
                        <button class="three-dots-btn" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="openExtensionModal(<?php echo $row['id']; ?>)"><i class="bi bi-calendar-plus me-2"></i>Request Deadline Extension</a></li>
                        </ul>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center text-muted mt-5">You have no assigned tasks.</p>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="extensionModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content" style="background-color: #fbf9f1;">
            <div class="modal-header border-0"><h6 class="modal-title fw-bold">Request Extension</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="request_extension" value="1">
                    <input type="hidden" name="task_id" id="ext_task_id">
                    <p class="small text-muted mb-2">How many days do you need?</p>
                    <select name="extension_days" class="form-select mb-3">
                        <option value="1">+1 Day</option><option value="2">+2 Days</option><option value="3">+3 Days</option><option value="4">+4 Days</option><option value="5">+5 Days</option>
                    </select>
                    <button type="submit" class="btn w-100 text-white fw-bold" style="background-color: #aebda0;">SUBMIT REQUEST</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function viewNotes(note) { Swal.fire({ title: 'Task Details', text: note || 'No details provided.', confirmButtonColor: '#aebda0' }); }
    function openExtensionModal(id) { document.getElementById('ext_task_id').value = id; new bootstrap.Modal(document.getElementById('extensionModal')).show(); }

    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    if (msg === 'status_updated') { const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 }); Toast.fire({ icon: 'success', title: 'Status Updated' }); }
    if (msg === 'requested') Swal.fire('Sent!', 'Extension request sent to Admin.', 'success');
    if(msg) window.history.replaceState({}, document.title, "usertask.php");
</script>
</body>
</html>