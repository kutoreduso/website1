<?php
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. DATABASE CONNECTION
$conn = new mysqli("sql113.infinityfree.com", "if0_40771057", "keTpieWit7k", "if0_40771057_taskflow");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// --- HANDLE ADD TASK ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_task'])) {
    $project = $_POST['task_name'];
    $assigned_to = $_POST['assigned_to'];
    $deadline = $_POST['deadline'];
    $status = 'Pending';
    $description = $_POST['notes'];

    $stmt = $conn->prepare("INSERT INTO tasks (project, assigned_to, deadline, status, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisss", $project, $assigned_to, $deadline, $status, $description);
    $stmt->execute();
    header("Location: admintask.php?msg=added");
    exit();
}

// --- HANDLE UPDATE TASK ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_task'])) {
    $id = $_POST['task_id'];
    $project = $_POST['task_name'];
    $assigned_to = $_POST['assigned_to'];
    $deadline = $_POST['deadline'];
    $description = $_POST['notes'];
    
    $stmt = $conn->prepare("UPDATE tasks SET project=?, assigned_to=?, deadline=?, description=? WHERE id=?");
    $stmt->bind_param("sissi", $project, $assigned_to, $deadline, $description, $id);
    if ($stmt->execute()) { header("Location: admintask.php?msg=updated"); }
    $stmt->close();
    exit();
}

// --- HANDLE EXTENSION REQUESTS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['handle_extension'])) {
    $id = $_POST['request_id'];
    $action = $_POST['action']; 

    $query = $conn->query("SELECT deadline, description FROM tasks WHERE id = $id");
    $task = $query->fetch_assoc();
    $currentDesc = $task['description'];
    $currentDeadline = $task['deadline'];

    preg_match('/\+(\d+) days/', $currentDesc, $matches);
    $daysToAdd = isset($matches[1]) ? (int)$matches[1] : 0;
    $cleanDesc = trim(preg_replace('/\[System\]: Requested extension of \+\d+ days\./', '', $currentDesc));

    if ($action === 'accept') {
        $newDeadline = date('Y-m-d', strtotime($currentDeadline . " + $daysToAdd days"));
        $stmt = $conn->prepare("UPDATE tasks SET deadline = ?, description = ?, status = 'In Progress' WHERE id = ?");
        $stmt->bind_param("ssi", $newDeadline, $cleanDesc, $id);
        $stmt->execute();
        header("Location: admintask.php?msg=accepted");
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE tasks SET description = ?, status = 'In Progress' WHERE id = ?");
        $stmt->bind_param("si", $cleanDesc, $id);
        $stmt->execute();
        header("Location: admintask.php?msg=rejected");
    }
    exit();
}

// --- HANDLE DELETE TASK ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $conn->query("DELETE FROM tasks WHERE id = $id");
    header("Location: admintask.php?msg=deleted");
    exit();
}

// --- FETCH DATA ---
$users = $conn->query("SELECT id, username FROM users WHERE role != 'Admin' AND status='active'");

$requests = $conn->query("
    SELECT t.id, t.project, t.deadline, t.description, u.username 
    FROM tasks t 
    JOIN users u ON t.assigned_to = u.id 
    WHERE t.status = 'Requesting Extension'
");

$allTasks = $conn->query("
    SELECT t.id, t.project, t.status, t.description, t.deadline, t.assigned_to, u.username 
    FROM tasks t 
    LEFT JOIN users u ON t.assigned_to = u.id 
    ORDER BY t.deadline ASC
");

$loggedInName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : "Admin";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Task - TaskFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        /* --- THEME --- */
        :root { --bg-beige: #f3efdf; --sidebar-bg: #ffffff; --card-sage: #c8d5b9; --btn-beige: #f0ebd8; --btn-purple: #cec2ff; }
        body { background-color: var(--bg-beige); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }

        /* --- SIDEBAR --- */
        .sidebar {
            background-color: var(--sidebar-bg);
            height: 100vh; width: 250px; position: fixed; top: 0; left: 0;
            padding: 2rem; display: flex; flex-direction: column; 
            border-right: 1px solid #eaeaea; z-index: 100;
        }
        .logo { font-size: 1.5rem; font-weight: 800; color: #333; margin-bottom: 3rem; display: flex; align-items: center; gap: 10px; }
        .nav-menu { flex-grow: 1; display: flex; flex-direction: column; gap: 15px; }
        .nav-btn { display: block; width: 100%; padding: 12px; border-radius: 12px; text-decoration: none; color: #666; font-weight: 600; background-color: var(--btn-beige); text-align: center; transition: 0.2s; border: none; }
        .nav-btn:hover { background-color: #e6e1cd; color: #333; }
        .nav-btn.active { background-color: var(--btn-purple); color: #333; }
        .logout-btn { background-color: var(--btn-beige); color: #000; font-weight: bold; text-align: center; padding: 12px; border-radius: 12px; text-decoration: none; display: block; margin-top: auto; }
        .logout-btn:hover { background-color: #e6e1cd; }

        /* --- MAIN CONTENT --- */
        .main-content { margin-left: 250px; padding: 2rem; }
        .top-header { display: flex; justify-content: flex-end; align-items: center; margin-bottom: 2rem; }
        .user-profile { display: flex; align-items: center; gap: 10px; font-weight: bold; }

        /* --- CARDS & TABLES --- */
        .content-card { background-color: var(--card-sage); border-radius: 20px; padding: 20px; margin-bottom: 2rem; min-height: 200px; }
        .section-header { font-weight: 800; font-size: 1.1rem; margin-bottom: 15px; color: #1f1f1f; display: flex; align-items: center; gap: 10px; }
        .count-badge { background-color: #cbd5a1; color: #333; padding: 2px 10px; border-radius: 6px; font-size: 0.9rem; font-weight: bold; }

        /* --- RESPONSIVE TASK ROWS --- */
        .white-row {
            background-color: #ffffff; border-radius: 10px; padding: 12px 20px; margin-bottom: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        /* Default Grid (Desktop) */
        .task-grid { display: grid; grid-template-columns: 2fr 3fr 1.5fr 1fr 1.5fr 0.5fr; align-items: center; width: 100%; gap: 10px; }
        .task-header-row { font-weight: bold; font-size: 0.9rem; padding: 0 20px; margin-bottom: 10px; color: #333; display: grid; grid-template-columns: 2fr 3fr 1.5fr 1fr 1.5fr 0.5fr; }

        .status-text { color: #eebb4d; font-weight: bold; }
        .view-link { color: #9c27b0; text-decoration: none; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; cursor: pointer; }
        .btn-add-task { background-color: #dce2b8; border: none; padding: 10px 25px; border-radius: 10px; font-weight: bold; color: #333; }
        .three-dots { border: none; background: none; font-size: 1.2rem; cursor: pointer; }

        /* Request Row Styling */
        .request-container { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .btn-accept { background-color: #a8e6cf; color: #1b4d3e; border: none; padding: 6px 20px; border-radius: 20px; font-weight: bold; font-size: 0.8rem; margin-left: 5px; }
        .btn-reject { background-color: #ff8b94; color: #5c1e23; border: none; padding: 6px 20px; border-radius: 20px; font-weight: bold; font-size: 0.8rem; margin-left: 5px; }

        /* --- MOBILE RESPONSIVENESS (< 991px) --- */
        @media (max-width: 991px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1.5rem; }
            .offcanvas-body { display: flex; flex-direction: column; height: 100%; }
            
            /* Hide the table header on mobile */
            .task-header-row { display: none; }

            /* Transform Grid to Block (Stacked) on Mobile */
            .task-grid { display: flex; flex-direction: column; align-items: flex-start; gap: 5px; position: relative; }
            
            /* Make each item take full width */
            .task-grid > span { width: 100%; display: block; margin-bottom: 2px; }
            
            /* Emphasize the User Name */
            .task-grid > span:first-child { font-size: 1.1rem; margin-bottom: 5px; color: #000; }
            
            /* Position the Menu Button Top-Right */
            .task-grid .text-end.dropdown { position: absolute; top: 0; right: 0; }
            
            /* Labeling for Context (Optional) */
            .task-grid > span:nth-child(2)::before { content: "Task: "; font-weight: bold; color: #777; }
            .task-grid > span:nth-child(5)::before { content: "Deadline: "; font-weight: bold; color: #777; }

            /* Request Row Mobile */
            .request-container { flex-direction: column; align-items: flex-start; gap: 10px; }
            .request-buttons { width: 100%; display: flex; gap: 10px; margin-top: 5px; }
            .btn-accept, .btn-reject { flex: 1; margin: 0; text-align: center; }
        }
        /* Sidebar Logo Image Style */
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
            <a href="admindashboard.php" class="nav-btn">DASHBOARD</a>
            <a href="#" class="nav-btn active">TASK</a>
            <a href="usermanagement.php" class="nav-btn">USERS</a>

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
        <a href="admindashboard.php" class="nav-btn">DASHBOARD</a>
        <a href="#" class="nav-btn active">TASK</a>
        <a href="usermanagement.php" class="nav-btn">USERS</a>

    </div>
    <a href="logout.php" class="logout-btn">Log Out</a>
</div>

<div class="main-content">
    
    <div class="top-header">
        <i class="bi bi-list fs-2 d-lg-none me-auto" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" style="cursor: pointer;"></i>
        <div class="user-profile">
            <span><?php echo $loggedInName; ?></span>
            <i class="bi bi-person-circle fs-1"></i>
        </div>
    </div>

    <div class="d-flex justify-content-end mb-3">
        <button class="btn-add-task" data-bs-toggle="modal" data-bs-target="#addTaskModal">ADD TASK</button>
    </div>

    <div class="section-header">
        EMPLOYEES REQUEST <span class="count-badge"><?php echo $requests->num_rows; ?></span>
    </div>
    
    <div class="content-card">
        <?php if ($requests->num_rows > 0): ?>
            <?php while($req = $requests->fetch_assoc()): 
                preg_match('/\+(\d+) days/', $req['description'], $matches);
                $daysRequested = isset($matches[1]) ? $matches[1] : '?';
            ?>
                <div class="white-row">
                    <div class="request-container">
                        <div style="flex-grow: 1;">
                            <span class="fw-bold d-block"><?php echo $req['username']; ?></span>
                            <small class="text-muted">Requesting +<?php echo $daysRequested; ?> days for: <?php echo $req['project']; ?></small>
                        </div>
                        
                        <form method="POST" id="form-<?php echo $req['id']; ?>" class="request-buttons m-0">
                            <input type="hidden" name="handle_extension" value="1">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <input type="hidden" name="action" id="action-<?php echo $req['id']; ?>" value="">
                            
                            <button type="button" class="btn-accept" onclick="confirmAction(<?php echo $req['id']; ?>, 'accept')">ACCEPT</button>
                            <button type="button" class="btn-reject" onclick="confirmAction(<?php echo $req['id']; ?>, 'reject')">REJECT</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="white-row justify-content-center text-muted">No pending extension requests</div>
        <?php endif; ?>
    </div>

    <div class="content-card">
        <div class="task-header-row">
            <span>Assigned name</span><span>Task</span><span>Status</span><span>Notes</span><span class="text-end">Deadline</span><span></span>
        </div>

        <?php if ($allTasks->num_rows > 0): ?>
            <?php while($task = $allTasks->fetch_assoc()): 
                // --- ADDED LOGIC: CHECK IF OVERDUE ---
                $isOverdue = ($task['deadline'] < date('Y-m-d')) && ($task['status'] !== 'Completed');
            ?>
                <div class="white-row">
                    <div class="task-grid">
                        <span class="fw-bold"><?php echo $task['username']; ?></span>
                        <span><?php echo $task['project']; ?></span>
                        
                        <?php if ($isOverdue): ?>
                            <span class="status-text text-danger fw-bold text-uppercase">DEADLINE</span>
                        <?php else: ?>
                            <span class="status-text"><?php echo $task['status']; ?></span>
                        <?php endif; ?>
                        
                        <span class="view-link" onclick="viewNotes('<?php echo addslashes(str_replace(array("\r", "\n"), '', $task['description'] ?? '')); ?>')">VIEW</span>
                        <span class="text-end"><?php echo date("M/d/Y", strtotime($task['deadline'])); ?></span>
                        
                        <div class="text-end dropdown">
                            <button class="three-dots" data-bs-toggle="dropdown"><i class="bi bi-plus-square"></i></button> 
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="openEditTask('<?php echo $task['id']; ?>', '<?php echo addslashes($task['project']); ?>', '<?php echo $task['assigned_to']; ?>', '<?php echo $task['deadline']; ?>', '<?php echo addslashes(str_replace(array("\r", "\n"), '', $task['description'] ?? '')); ?>')"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?php echo $task['id']; ?>)">Delete</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center text-muted mt-3">No tasks found.</p>
        <?php endif; ?>
    </div>

</div>

<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background-color: #fbf9f1;">
            <div class="modal-header border-0"><h5 class="modal-title fw-bold">Add New Task</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST"><div class="modal-body"><input type="hidden" name="add_task" value="1"><div class="mb-3"><label class="form-label fw-bold">Task Name</label><input type="text" name="task_name" class="form-control" required></div><div class="mb-3"><label class="form-label fw-bold">Assign To</label><select name="assigned_to" class="form-select" required><option value="">Select User...</option><?php $users->data_seek(0); while($u = $users->fetch_assoc()): ?><option value="<?php echo $u['id']; ?>"><?php echo $u['username']; ?></option><?php endwhile; ?></select></div><div class="mb-3"><label class="form-label fw-bold">Deadline</label><input type="date" name="deadline" class="form-control" required></div><div class="mb-3"><label class="form-label fw-bold">Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div></div><div class="modal-footer border-0"><button type="submit" class="btn btn-success" style="background-color: #aebda0; border: none;">Assign</button></div></form>
        </div>
    </div>
</div>

<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background-color: #fbf9f1;">
            <div class="modal-header border-0"><h5 class="modal-title fw-bold">Edit Task</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_task" value="1">
                    <input type="hidden" name="task_id" id="edit_task_id">
                    <div class="mb-3"><label class="form-label fw-bold">Task Name</label><input type="text" name="task_name" id="edit_task_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label fw-bold">Assign To</label><select name="assigned_to" id="edit_assigned_to" class="form-select" required><option value="">Select User...</option><?php $users->data_seek(0); while($u = $users->fetch_assoc()): ?><option value="<?php echo $u['id']; ?>"><?php echo $u['username']; ?></option><?php endwhile; ?></select></div>
                    <div class="mb-3"><label class="form-label fw-bold">Deadline</label><input type="date" name="deadline" id="edit_deadline" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label fw-bold">Notes</label><textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" style="background-color: #aebda0; border: none;">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function viewNotes(note) { Swal.fire({ title: 'Task Notes', text: note || 'No notes available.', confirmButtonColor: '#aebda0' }); }
    function confirmAction(id, actionType) {
        let title = actionType === 'accept' ? 'Accept Extension?' : 'Reject Request?';
        let btnColor = actionType === 'accept' ? '#a8e6cf' : '#ff8b94';
        Swal.fire({ title: title, text: "Are you sure?", icon: 'question', showCancelButton: true, confirmButtonColor: btnColor, confirmButtonText: 'Yes' }).then((result) => { if (result.isConfirmed) { document.getElementById('action-' + id).value = actionType; document.getElementById('form-' + id).submit(); } });
    }
    function confirmDelete(id) { Swal.fire({ title: 'Delete Task?', text: "Cannot be undone!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!' }).then((result) => { if (result.isConfirmed) { window.location.href = `admintask.php?delete_id=${id}`; } }) }
    function openEditTask(id, project, assignedTo, deadline, description) {
        document.getElementById('edit_task_id').value = id;
        document.getElementById('edit_task_name').value = project;
        document.getElementById('edit_assigned_to').value = assignedTo;
        document.getElementById('edit_deadline').value = deadline;
        document.getElementById('edit_notes').value = description;
        new bootstrap.Modal(document.getElementById('editTaskModal')).show();
    }
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    if (msg === 'added') Swal.fire('Success', 'Task added!', 'success');
    if (msg === 'updated') Swal.fire('Success', 'Task updated!', 'success');
    if (msg === 'deleted') Swal.fire('Deleted', 'Task removed.', 'success');
    if(msg && msg !== 'accepted' && msg !== 'rejected') window.history.replaceState({}, document.title, "admintask.php");
</script>
</body>
</html>