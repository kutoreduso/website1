<?php
session_start();

// 1. SECURITY CHECK
$conn = new mysqli("sql113.infinityfree.com", "if0_40771057", "keTpieWit7k", "if0_40771057_taskflow");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// 3. GET LOGGED IN ADMIN INFO
$fullName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : "Admin";
$role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : "Administrator";

// 4. FETCH TASK COUNTS (WITH DEADLINE LOGIC)
$cntPending = 0;
$cntProgress = 0;
$cntCompleted = 0;
$cntDeadline = 0; // <--- New Counter

// Select all tasks to check their dates
$sql = "SELECT status, deadline FROM tasks";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $status = trim($row['status']);
        
        // Check if overdue: Date is past AND not completed
        $isOverdue = ($row['deadline'] < date('Y-m-d')) && ($status !== 'Completed');

        if ($status == 'Completed') {
            $cntCompleted++;
        } elseif ($isOverdue) {
            $cntDeadline++; // <--- Count as Deadline if overdue
        } elseif ($status == 'In Progress' || $status == 'Requesting Extension') {
            $cntProgress++;
        } else {
            $cntPending++;
        }
    }
}

$totalTasks = $cntPending + $cntProgress + $cntCompleted + $cntDeadline;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TaskFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        /* --- THEME COLORS --- */
        :root {
            --bg-color: #f3efdf;
            --sidebar-bg: #ffffff;
            --card-pending: #98e3ea;
            --card-prog: #dce2b8;
            --card-done: #aebda0;
            --btn-beige: #f0ebd8;
            --btn-purple: #cec2ff;
            --text-dark: #333;
        }

        body { background-color: var(--bg-color); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }

        /* --- SIDEBAR (Fixed Layout) --- */
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
        .nav-btn:hover { background-color: #e6e1cd; color: #000; }
        .nav-btn.active { background-color: var(--btn-purple); color: #000; }

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
        
        .welcome-section h1 { font-weight: 800; font-size: 2.5rem; margin-bottom: 0; }
        .welcome-section p { font-size: 1.5rem; color: #888; font-weight: 600; }

        /* --- STAT CARDS --- */
        .stat-card {
            border-radius: 20px; padding: 2rem; min-height: 150px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }
        .bg-pending { background-color: var(--card-pending); }
        .bg-prog { background-color: var(--card-prog); }
        .bg-done { background-color: var(--card-done); }

        .stat-label { font-weight: 700; font-size: 1.2rem; color: #000; }
        .stat-badge { background: rgba(0,0,0,0.1); padding: 5px 15px; border-radius: 12px; font-weight: bold; font-size: 1.2rem; }

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
        
        @media (max-width: 991px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1.5rem; } /* Reduced padding for mobile */
            .offcanvas-body { display: flex; flex-direction: column; }
        }

        @media (max-width: 576px) {
            /* Extra optimizations for small phones to fix Calendar */
            .main-content { padding: 1rem; }
            .widget-card { padding: 1rem; } /* Reduce internal card padding */
            .date-cell { padding: 8px 2px; font-size: 0.85rem; } /* Smaller cells */
            .day-name { font-size: 0.75rem; }
            .stat-card { padding: 1.5rem; }
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
            <a href="#" class="nav-btn active">DASHBOARD</a>
            <a href="admintask.php" class="nav-btn">TASK</a>
            <a href="usermanagement.php" class="nav-btn">USERS</a>

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
        <a href="#" class="nav-btn active">DASHBOARD</a>
        <a href="admintask.php" class="nav-btn">TASK</a>
        <a href="usermanagement.php" class="nav-btn">USERS</a>

    </div>
    <a href="logout.php" class="logout-btn">LOGOUT</a>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <i class="bi bi-list mobile-toggle d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"></i>
        <div class="ms-auto d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <span class="fw-bold d-block"><?php echo $fullName; ?></span>
            </div>
            <i class="bi bi-person-circle fs-2"></i>
        </div>
    </div>

    <div class="welcome-section mb-5">
        <h1>HELLO, <?php echo $fullName; ?></h1>
        <p>Position: <?php echo $role; ?></p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4"><div class="stat-card bg-pending"><span class="stat-label">Pending Task</span><span class="stat-badge"><?php echo $cntPending; ?></span></div></div>
        <div class="col-md-4"><div class="stat-card bg-prog"><span class="stat-label">In Progress</span><span class="stat-badge"><?php echo $cntProgress; ?></span></div></div>
        <div class="col-md-4"><div class="stat-card bg-done"><span class="stat-label">Completed</span><span class="stat-badge"><?php echo $cntCompleted; ?></span></div></div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="widget-card">
                <div class="widget-title"><span>Project Status</span><i class="bi bi-three-dots"></i></div>
                <div style="position: relative; height: 200px; display: flex; justify-content: center;"><canvas id="statusChart"></canvas></div>
                <div class="mt-4 small">
                    <div class="d-flex justify-content-between mb-2"><span><i class="bi bi-circle-fill" style="color: #aebda0;"></i> Completed</span><span class="fw-bold"><?php echo $cntCompleted; ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span><i class="bi bi-circle-fill" style="color: #dce2b8;"></i> In Progress</span><span class="fw-bold"><?php echo $cntProgress; ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span><i class="bi bi-circle-fill" style="color: #98e3ea;"></i> Pending</span><span class="fw-bold"><?php echo $cntPending; ?></span></div>
                    <div class="d-flex justify-content-between"><span><i class="bi bi-circle-fill" style="color: #dc3545;"></i> Deadline</span><span class="fw-bold text-danger"><?php echo $cntDeadline; ?></span></div>
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
    // --- UPDATED JAVASCRIPT FOR CHART ---
    const pendingData = <?php echo $cntPending; ?>;
    const progData = <?php echo $cntProgress; ?>;
    const doneData = <?php echo $cntCompleted; ?>;
    const deadlineData = <?php echo $cntDeadline; ?>; // New Data
    
    // Total used for center text
    const totalData = pendingData + progData + doneData + deadlineData;

    const ctx = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'In Progress', 'Pending', 'Deadline'],
            datasets: [{
                data: [doneData, progData, pendingData, deadlineData],
                backgroundColor: ['#aebda0', '#dce2b8', '#98e3ea', '#dc3545'], // Added Red for Deadline
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: true } } },
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
        for(let i=0; i<firstDayIndex; i++) grid.innerHTML += `<div class="date-cell empty"></div>`;
        const today = new Date();
        for(let i=1; i<=lastDay; i++) {
            const isToday = i === today.getDate() && currentDate.getMonth() === today.getMonth() && currentDate.getFullYear() === today.getFullYear();
            grid.innerHTML += `<div class="date-cell ${isToday ? 'today' : ''}">${i}</div>`;
        }
    }
    function changeMonth(direction) { currentDate.setMonth(currentDate.getMonth() + direction); renderCalendar(); }
    renderCalendar();
</script>
</body>
</html>