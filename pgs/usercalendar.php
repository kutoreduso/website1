<?php
// --- 1. SESSION & ERROR REPORTING ---
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 2. DATABASE CONNECTION ---
$host = "sql113.infinityfree.com";
$username = "if0_40771057";
$password = "Your_vPanel_Password"; // <--- PUT YOUR REAL PASSWORD HERE
$dbname = "if0_40771057_taskflow";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 3. CALENDAR LOGIC (January 2025) ---
$year = 2025;
$month = 1;

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfWeek = date('w', strtotime("$year-$month-01")); 

// --- 4. FETCH TASKS ---
$tasks = [];
$sql = "SELECT * FROM tasks WHERE MONTH(deadline) = $month AND YEAR(deadline) = $year";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $dayVal = (int)date('d', strtotime($row['deadline']));
        $tasks[$dayVal] = $row['task_name']; 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .animate-fade-in { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-[#F3EFE0] h-screen overflow-hidden flex">

    <aside class="w-64 bg-white h-full flex flex-col justify-between p-6 shadow-sm z-10 hidden md:flex">
        
        <div>
            <div class="flex items-center gap-2 mb-10">
                <div class="relative w-7 h-7 flex-shrink-0">
                     <svg class="w-full h-full text-black" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                     <div class="absolute -bottom-1 -right-1 bg-white rounded-full p-0.5">
                        <svg class="w-3 h-3 text-black" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                     </div>
                </div>
                <span class="font-bold text-xl text-black tracking-tight">TaskFlow</span>
            </div>

            <nav class="space-y-4">
                <a href="dashboard.php" class="block w-full text-center py-3 rounded-xl bg-[#EFEAD8] text-gray-700 text-sm font-bold tracking-wide hover:opacity-90 transition shadow-sm">
                    DASHBOARD
                </a>
                <a href="#" class="block w-full text-center py-3 rounded-xl bg-[#EFEAD8] text-gray-700 text-sm font-bold tracking-wide hover:opacity-90 transition shadow-sm">
                    TASK
                </a>
                <a href="#" class="block w-full text-center py-3 rounded-xl bg-[#C4C4F5] text-[#5A5A8F] text-sm font-bold tracking-wide shadow-sm hover:opacity-90 transition">
                    CALENDAR
                </a>
            </nav>
        </div>

        <div>
            <a href="../logout.php" class="block w-full text-center py-3 rounded-xl bg-[#EFEAD8] text-black text-sm font-bold tracking-wide hover:bg-red-100 hover:text-red-600 transition shadow-sm uppercase">
                LOGOUT
            </a>
        </div>

    </aside>

    <main class="flex-1 flex flex-col p-4 md:p-10 relative overflow-hidden">
        
        <header class="flex justify-between md:justify-end items-center mb-6">
            <div class="md:hidden font-bold text-xl">TaskFlow</div>
            <div class="flex items-center gap-3">
                <div class="text-right hidden md:block">
                    <span class="block text-sm font-bold text-gray-800">
                        <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?>
                    </span>
                </div>
                <div class="w-10 h-10 rounded-full border-2 border-black flex items-center justify-center overflow-hidden bg-white">
                    <svg class="w-8 h-8 text-black mt-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                </div>
            </div>
        </header>

        <div class="bg-white rounded-[2rem] p-4 md:p-8 shadow-sm flex-1 overflow-hidden flex flex-col">
            
            <div class="flex justify-between items-end mb-6 px-2">
                <h2 class="text-2xl md:text-4xl font-light text-gray-300">01</h2>
                <h1 class="text-2xl md:text-4xl font-semibold tracking-[0.2em] text-gray-800 uppercase">
                    <?php echo date('F', mktime(0, 0, 0, $month, 10)); ?>
                </h1>
                <h2 class="text-2xl md:text-4xl font-light text-gray-500"><?php echo $year; ?></h2>
            </div>

            <div class="flex-1 border border-gray-200 rounded-lg overflow-auto">
                <div class="grid grid-cols-7 border-b border-gray-200 min-w-[600px]">
                    <?php foreach(['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'] as $day): ?>
                        <div class="py-3 text-center text-[10px] md:text-xs font-bold tracking-widest text-gray-800 uppercase bg-white">
                            <?php echo $day; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="grid grid-cols-7 bg-gray-200 gap-px border-b border-gray-200 h-full min-h-[500px] min-w-[600px]">
                    <?php
                    for ($i = 0; $i < $firstDayOfWeek; $i++) {
                        echo '<div class="bg-white h-full min-h-[80px]"></div>';
                    }

                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $hasTask = array_key_exists($day, $tasks);
                        $taskName = $hasTask ? $tasks[$day] : '';
                        
                        echo '<div class="bg-white h-full min-h-[80px] p-2 relative flex flex-col items-center group transition hover:bg-gray-50">';
                            echo '<span class="w-full text-right text-xs font-semibold text-gray-400 mb-1">' . $day . '</span>';
                            
                            if ($hasTask) {
                                echo '
                                <div class="w-full bg-[#FF9F9F] py-2 md:py-3 rounded text-center shadow-sm mt-1 animate-fade-in cursor-pointer hover:bg-[#ff8f8f]" title="'.htmlspecialchars($taskName).'">
                                    <span class="text-[9px] md:text-[10px] font-bold text-black uppercase tracking-wide block truncate px-1">TASK</span>
                                </div>
                                ';
                            }
                        echo '</div>';
                    }

                    $remainingDays = 7 - (($firstDayOfWeek + $daysInMonth) % 7);
                    if ($remainingDays < 7) {
                        for ($i = 0; $i < $remainingDays; $i++) {
                            echo '<div class="bg-white h-full min-h-[80px]"></div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>