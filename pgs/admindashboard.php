<?php
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. DATABASE CONNECTION
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "taskflow_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// 3. GET LOGGED IN ADMIN INFO
$fullName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : "Admin";
$role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : "Administrator";

// 4. FETCH TASK COUNTS (LIVE DATA)
$pending = 0;
$progress = 0;
$completed = 0;

// Query to count tasks grouped by status
$sql = "SELECT status, COUNT(*) as count FROM tasks GROUP BY status";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Normalize status string (remove extra spaces, lower case check)
        $status = trim($row['status']);
        
        if ($status == 'Pending') {
            $pending = $row['count'];
        } elseif ($status == 'In Progress') {
            $progress = $row['count'];
        } elseif ($status == 'Completed') {
            $completed = $row['count'];
        }
    }
}

// 5. FETCH RECENT TASKS FOR CHART (Optional - fetches data for the donut chart)
$totalTasks = $pending + $progress + $completed;

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
            --card-pending: #98e3ea;   /* Light Cyan/Blue */
            --card-prog: #dce2b8;      /* Sage Green */
            --card-done: #aebda0;      /* Darker Green */
            --btn-beige: #f0ebd8;      /* Nav Buttons */
            --btn-purple: #cec2ff;     /* Active State */
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
            flex-direction: column; /* Stack items vertically */
            border-right: 1px solid #eaeaea;
            z-index: 1000;
        }

        .logo { font-size: 1.5rem; font-weight: bold; margin-bottom: 3rem; display: flex; align-items: center; gap: 10px; color: #333; }

        /* Navigation Menu (Grows to push Logout down) */
        .nav-menu {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .nav-btn {
            display: block;
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            text-decoration: none;
            color: #666;
            font-weight: 600;
            background-color: var(--btn-beige);
            text-align: center;
            transition: 0.2s;
            border: none;
        }
        .nav-btn:hover { background-color: #e6e1cd; color: #000; }
        .nav-btn.active { background-color: var(--btn-purple); color: #000; }

        /* Logout Button (Pinned to Bottom) */
        .logout-btn {
            background-color: var(--btn-beige);
            color: #000;
            font-weight: bold;
            text-align: center;
            padding: 12px;
            border-radius: 12px;
            text-decoration: none;
            display: block;
            margin-top: auto; /* Pushes to bottom */
        }
        .logout-btn:hover { background-color: #e6e1cd; }

        /* --- MAIN CONTENT (Adjusted for fixed sidebar) --- */
        .main-content {
            margin-left: 250px; /* Same as sidebar width */
            padding: 3rem;
        }
        
        .welcome-section h1 { font-weight: 800; font-size: 2.5rem; margin-bottom: 0; }
        .welcome-section p { font-size: 1.5rem; color: #888; font-weight: 600; }

        /* --- STAT CARDS --- */
        .stat-card {
            border-radius: 20px;
            padding: 2rem;
            min-height: 150px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }
        
        .bg-pending { background-color: var(--card-pending); }
        .bg-prog { background-color: var(--card-prog); }
        .bg-done { background-color: var(--card-done); }

        .stat-label { font-weight: 700; font-size: 1.2rem; color: #000; }
        .stat-badge { 
            background: rgba(0,0,0,0.1); 
            padding: 5px 15px; 
            border-radius: 12px; 
            font-weight: bold; 
            font-size: 1.2rem;
        }

        /* --- WIDGETS --- */
        .widget-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            height: 100%;
            min-height: 350px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        .widget-title { font-weight: bold; margin-bottom: 1.5rem; display: flex; justify-content: space-between; }

        /* --- CALENDAR STYLES --- */
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; gap: 5px; }
        .day-name { font-weight: bold; color: #888; font-size: 0.8rem; margin-bottom: 10px; }
        .date-cell { padding: 10px; border-radius: 5px; cursor: pointer; font-size: 0.9rem; }
        .date-cell:hover { background-color: #f0f0f0; }
        .date-cell.today { background-color: #3b5bdb; color: white; font-weight: bold; }
        .date-cell.empty { background: transparent; cursor: default; }
        .btn-cal { border: none; background: transparent; cursor: pointer; font-size: 1.2rem; }

        /* Mobile Responsive */
        .mobile-toggle { font-size: 1.5rem; cursor: pointer; color: #333; margin-right: auto; }
        @media(max-width: 991px) { 
            .sidebar { display: none; } 
            .main-content { margin-left: 0; }
            .offcanvas-body { display: flex; flex-direction: column; } /* Flex for mobile menu too */
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
            <a href="calendar.php" class="nav-btn">CALENDAR</a>
        </div>
        <a href="logout.php" class="logout-btn">LOGOUT</a>
    </div>
</div>

<div class="sidebar d-none d-lg-flex">
    <div class="logo"><i class="bi bi-kanban"></i> TaskFlow</div>
    
    <div class="nav-menu">
        <a href="#" class="nav-btn active">DASHBOARD</a>
        <a href="admintask.php" class="nav-btn">TASK</a>
        <a href="usermanagement.php" class="nav-btn">USERS</a>
        <a href="calendar.php" class="nav-btn">CALENDAR</a>
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
        
        <div class="col-md-4">
            <div class="stat-card bg-pending">
                <span class="stat-label">Pending Task</span>
                <span class="stat-badge"><?php echo $pending; ?></span>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card bg-prog">
                <span class="stat-label">In Progress</span>
                <span class="stat-badge"><?php echo $progress; ?></span>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card bg-done">
                <span class="stat-label">Completed</span>
                <span class="stat-badge"><?php echo $completed; ?></span>
            </div>
        </div>

    </div>

    <div class="row g-4">
        
        <div class="col-md-4">
            <div class="widget-card">
                <div class="widget-title">
                    <span>Project Status</span>
                    <i class="bi bi-three-dots"></i>
                </div>
                <div style="position: relative; height: 200px; display: flex; justify-content: center;">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="mt-4 small">
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="bi bi-circle-fill" style="color: #98e3ea"></i> Completed</span>
                        <span class="fw-bold"><?php echo $completed; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="bi bi-circle-fill" style="color: #d4a017"></i> In Progress</span>
                        <span class="fw-bold"><?php echo $progress; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="bi bi-circle-fill" style="color: #4b6cb7 "></i> Pending</span>
                        <span class="fw-bold"><?php echo $pending; ?></span>
                    </div>
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
    // Inject PHP data into JS
    const pendingData = <?php echo $pending; ?>;
    const progData = <?php echo $progress; ?>;
    const doneData = <?php echo $completed; ?>;
    const totalData = pendingData + progData + doneData;

    // Chart.js Configuration
    const ctx = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'In Progress', 'Pending'],
            datasets: [{
                data: [doneData, progData, pendingData],
                backgroundColor: ['#4b6cb7', '#d4a017', '#98e3ea'], 
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true }
            }
        },
        plugins: [{
            id: 'textCenter',
            beforeDraw: function(chart) {
                var width = chart.width,
                    height = chart.height,
                    ctx = chart.ctx;

                ctx.restore();
                var fontSize = (height / 140).toFixed(2);
                ctx.font = "bold " + fontSize + "em sans-serif";
                ctx.textBaseline = "middle";
                ctx.fillStyle = "#333";

                var text = totalData,
                    textX = Math.round((width - ctx.measureText(text).width) / 2),
                    textY = height / 2;

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

        // Add Day Headers
        const days = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        days.forEach(d => grid.innerHTML += `<div class="day-name">${d}</div>`);

        // Calculate Days
        const firstDayIndex = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1).getDay();
        const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).getDate();

        // Empty Cells before 1st of month
        for(let i=0; i<firstDayIndex; i++) {
            grid.innerHTML += `<div class="date-cell empty"></div>`;
        }

        // Date Cells
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

    // Initialize Calendar
    renderCalendar();
</script>
</body>
</html>