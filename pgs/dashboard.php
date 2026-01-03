<?php
session_start();

$conn = new mysqli("sql113.infinityfree.com", "if0_40771057", "keTpieWit7k", "if0_40771057_taskflow");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// 3. GET LOGGED IN USER ID
$my_id = $_SESSION['user_id'];
$fullName = $_SESSION['user_name'];
$role = $_SESSION['user_role'];

// 4. FETCH TASK COUNTS (WITH DEADLINE LOGIC)
$todo = 0;
$progress = 0;
$done = 0;
$deadline_count = 0; // <--- New Counter

// Fetch status AND deadline for this user
$stmt = $conn->prepare("SELECT status, deadline FROM tasks WHERE assigned_to = ?");
$stmt->bind_param("i", $my_id);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    $status = strtolower(trim($row['status']));
    $taskDeadline = $row['deadline'];
    
    // Check if overdue: Date is past AND not completed
    $isOverdue = ($taskDeadline < date('Y-m-d')) && ($status !== 'completed') && ($status !== 'done');

    if ($status == 'completed' || $status == 'done') {
        $done++;
    } elseif ($isOverdue) {
        $deadline_count++; // <--- Count as Deadline
    } elseif ($status == 'in progress' || $status == 'requesting extension') {
        $progress++;
    } else {
        // Pending / To Do
        $todo++;
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TaskFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        /* --- THEME COLORS --- */
        :root {
            --bg-color: #f3efdf;
            --sidebar-bg: #ffffff;
            --card-todo: #98e3ea;
            --card-prog: #dce2b8;
            --card-done: #aebda0;
            --btn-beige: #f0ebd8;
            --btn-purple: #cec2ff;
        }

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

        .nav-menu { flex-grow: 1; display: flex; flex-direction: column; gap: 15px; }

        .nav-btn {
            display: block; width: 100%; padding: 12px; border-radius: 12px;
            text-decoration: none; color: #666; font-weight: 600;
            background-color: var(--btn-beige); text-align: center;
            transition: 0.2s; border: none;
        }
        .nav-btn:hover { background-color: #e6e1cd; color: #333; }
        .nav-btn.active { background-color: var(--btn-purple); color: #333; }

        .logout-btn {
            background-color: var(--btn-beige); color: #000; font-weight: bold;
            text-align: center; padding: 12px; border-radius: 12px;
            text-decoration: none; display: block; margin-top: auto;
        }
        .logout-btn:hover { background-color: #e6e1cd; }

        /* --- MAIN CONTENT --- */
        .main-container { 
            padding: 3rem; 
            margin-left: 250px; /* Matches Sidebar Width */
        }
        
        .welcome-section h1 { font-weight: 800; font-size: 2.5rem; margin-bottom: 0; }
        .welcome-section p { font-size: 1.2rem; color: #777; font-weight: 600; }

        /* --- STAT CARDS --- */
        .stat-card {
            border-radius: 20px; padding: 2rem; min-height: 140px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            display: flex; justify-content: space-between; align-items: center;
        }
        .bg-todo { background-color: var(--card-todo); }
        .bg-prog { background-color: var(--card-prog); }
        .bg-done { background-color: var(--card-done); }
        
        .stat-label { font-weight: 700; font-size: 1.1rem; color: #000; }
        .stat-badge { background: rgba(0,0,0,0.1); padding: 2px 10px; border-radius: 10px; font-weight: bold; margin-left: 10px; }

        /* --- WIDGETS --- */
        .widget-card {
            background: white; border-radius: 20px; padding: 2rem;
            height: 100%; min-height: 350px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        .widget-title { font-weight: bold; margin-bottom: 1.5rem; display: flex; justify-content: space-between; }

        /* --- CALENDAR STYLES (Responsive) --- */
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; gap: 5px; }
        .day-name { font-weight: bold; color: #888; font-size: 0.8rem; margin-bottom: 10px; }
        
        .date-cell { padding: 10px; border-radius: 5px; cursor: pointer; font-size: 0.9rem; }
        .date-cell:hover { background-color: #f0f0f0; }
        .date-cell.today { background-color: #3b5bdb; color: white; font-weight: bold; }
        .date-cell.empty { background: transparent; cursor: default; }
        .btn-cal { border: none; background: transparent; cursor: pointer; font-size: 1.2rem; }

        /* --- MOBILE RESPONSIVENESS --- */
        .mobile-toggle { font-size: 1.5rem; cursor: pointer; color: #333; margin-right: auto; }
        
        @media(max-width: 991px) { 
            .sidebar { display: none; } 
            .main-container { margin-left: 0; padding: 1.5rem; }
            .offcanvas-body { display: flex; flex-direction: column; } 
        }

        @media (max-width: 576px) {
            .main-container { padding: 1rem; }
            .widget-card { padding: 1rem; }
            .date-cell { padding: 8px 2px; font-size: 0.85rem; }
            .day-name { font-size: 0.75rem; }
            .stat-card { padding: 1.5rem; }
        }
        /* Sidebar Logo Container */
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
            <a href="#" class="nav-btn active">DASHBOARD</a>
            <a href="usertask.php" class="nav-btn">TASK</a> 
        </div>
        <a href="logout.php" class="logout-btn">LOGOUT</a>
    </div>
</div>
<div class="sidebar d-none d-lg-flex">
    <div class="logo">
        <img src="../imgs/logo.png" alt="TaskFlow Logo" class="sidebar-logo-img">
        TaskFlow
    </div>

    <div class="nav-menu">
        <a href="dashboard.php" class="nav-btn">DASHBOARD</a>
        <a href="usertask.php" class="nav-btn active">TASK</a>

       
    </div>
    <a href="logout.php" class="logout-btn">Log Out</a>
</div>

<div class="main-container">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <i class="bi bi-list mobile-toggle d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"></i>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="fw-bold d-none d-sm-block"><?php echo $fullName; ?></span>
            <i class="bi bi-person-circle fs-2"></i>
        </div>
    </div>

    <div class="welcome-section mb-5">
        <h1>HELLO, <?php echo $fullName; ?></h1>
        <p>Position: <?php echo $role; ?></p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4"><div class="stat-card bg-todo"><span class="stat-label">To do Task</span> <span class="stat-badge"><?php echo $todo; ?></span></div></div>
        <div class="col-md-4"><div class="stat-card bg-prog"><span class="stat-label">In Progress</span> <span class="stat-badge"><?php echo $progress; ?></span></div></div>
        <div class="col-md-4"><div class="stat-card bg-done"><span class="stat-label">Completed</span> <span class="stat-badge"><?php echo $done; ?></span></div></div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="widget-card">
                <div class="widget-title"><span>Project Status</span><i class="bi bi-three-dots"></i></div>
                <div style="position: relative; height: 200px; display: flex; justify-content: center;"><canvas id="statusChart"></canvas></div>
                <div class="mt-4 small">
                    <div class="d-flex justify-content-between mb-2"><span><i class="bi bi-circle-fill" style="color: #aebda0;"></i> Completed</span><span class="fw-bold"><?php echo $done; ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span><i class="bi bi-circle-fill" style="color: #dce2b8;"></i> In Progress</span><span class="fw-bold"><?php echo $progress; ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span><i class="bi bi-circle-fill" style="color: #98e3ea;"></i> To Do</span><span class="fw-bold"><?php echo $todo; ?></span></div>
                    <div class="d-flex justify-content-between"><span><i class="bi bi-circle-fill" style="color: #dc3545;"></i> Deadline</span><span class="fw-bold text-danger"><?php echo $deadline_count; ?></span></div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="widget-card">
                <div class="calendar-header">
                    <span class="fw-bold fs-5" id="monthYear">Month Year</span>
                    <div>
                        <button class="btn-cal" onclick="changeMonth(-1)"><i class="bi bi-chevron-left"></i></button>
                        <button class="btn-cal" onclick="changeMonth(1)"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
                
                <div class="calendar-grid" id="calendarGrid"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // --- CHART LOGIC ---
    const todoData = <?php echo $todo; ?>;
    const progData = <?php echo $progress; ?>;
    const doneData = <?php echo $done; ?>;
    const deadlineData = <?php echo $deadline_count; ?>; // New Data
    
    // Total for center text
    const totalData = todoData + progData + doneData + deadlineData;

    const ctx = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Done', 'In Progress', 'Todo', 'Deadline'],
            datasets: [{
                data: [doneData, progData, todoData, deadlineData],
                backgroundColor: ['#aebda0', '#dce2b8', '#98e3ea', '#dc3545'], // Red for Deadline
                borderWidth: 0,
                cutout: '70%'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
        plugins: [{
            id: 'textCenter',
            beforeDraw: function(chart) {
                var width = chart.width, height = chart.height, ctx = chart.ctx;
                ctx.restore();
                var fontSize = (height / 140).toFixed(2);
                ctx.font = "bold " + fontSize + "em sans-serif";
                ctx.textBaseline = "middle";
                ctx.fillStyle = "#333";
                var text = totalData, textX = Math.round((width - ctx.measureText(text).width) / 2), textY = height / 2;
                ctx.fillText(text, textX, textY);
                ctx.save();
            }
        }]
    });

    // --- CALENDAR LOGIC ---
    let currentDate = new Date();

    function renderCalendar() {
        const monthYear = document.getElementById('monthYear');
        const grid = document.getElementById('calendarGrid');
        grid.innerHTML = '';

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        monthYear.innerText = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;

        const days = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        days.forEach(d => grid.innerHTML += `<div class="day-name">${d}</div>`);

        const firstDayIndex = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1).getDay();
        const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).getDate();

        for(let i=0; i<firstDayIndex; i++) {
            grid.innerHTML += `<div class="date-cell empty"></div>`;
        }

        const today = new Date();
        for(let i=1; i<=lastDay; i++) {
            const isToday = i === today.getDate() && 
                          currentDate.getMonth() === today.getMonth() && 
                          currentDate.getFullYear() === today.getFullYear();
            
            grid.innerHTML += `<div class="date-cell ${isToday ? 'today' : ''}">${i}</div>`;
        }
    }

    function changeMonth(direction) {
        currentDate.setMonth(currentDate.getMonth() + direction);
        renderCalendar();
    }

    renderCalendar();
</script>
</body>
</html>