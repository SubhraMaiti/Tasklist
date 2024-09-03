<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Get current habit ID
$habit_id = isset($_GET['habit_id']) ? intval($_GET['habit_id']) : null;

// Fetch habits from periodic_tasks table with tag 'habit'
$sql = "SELECT periodic_tasks.id, periodic_tasks.description 
        FROM periodic_tasks 
        JOIN tags ON periodic_tasks.tag_id = tags.id 
        WHERE tags.name = 'habit'";
$habits_result = $conn->query($sql);

// If no habit_id is set, use the first habit
//check
if ($habit_id === null && $habits_result->num_rows > 0) {
    $habit = $habits_result->fetch_assoc();
    $habit_id = $habit['id'];
}

// Fetch the description for the current habit
$sql = "SELECT description FROM periodic_tasks WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $habit_id);
$stmt->execute();
$result = $stmt->get_result();
$habit_description = $result->fetch_assoc()['description'];

// Fetch completions for the current habit and month
$sql = "SELECT DATE(date_added) as date, completed 
        FROM tasks
        WHERE description = ? AND MONTH(date_added) = ? AND YEAR(date_added) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $habit_description, $month, $year);
$stmt->execute();
$completions_result = $stmt->get_result();

$completions = array();
while ($row = $completions_result->fetch_assoc()) {
    $completions[$row['date']] = $row['completed'];
}

// Helper functions
function getMonthName($month) {
    return date('F', mktime(0, 0, 0, $month, 1, 2000));
}

function getDaysInMonth($month, $year) {
    return date('t', mktime(0, 0, 0, $month, 1, $year));
}

function getFirstDayOfWeek($month, $year) {
    return date('w', mktime(0, 0, 0, $month, 1, $year));
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habit Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            max-width: 400px;
            margin: 0 auto;
        }
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid #ddd;
            font-size: 0.8em;
        }
        .completed {
            background-color: #28a745;
            color: white;
        }
        .not-completed {
            background-color: #dc3545;
            color: white;
        }
        .future {
            background-color: white;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Habit Tracker</h1>

        <div class="row mb-3">
            <div class="col">
                <select id="habit-select" class="form-select">
                    <?php
                    $habits_result->data_seek(0);
                    while ($habit = $habits_result->fetch_assoc()) {
                        echo "<option value='{$habit['id']}'" . ($habit['id'] == $habit_id ? " selected" : "") . ">{$habit['description']}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col">
                <h2><?php echo getMonthName($month) . " " . $year; ?></h2>
            </div>
            <div class="col-auto">
                <a href="?month=<?php echo $month-1; ?>&year=<?php echo $year; ?>&habit_id=<?php echo $habit_id; ?>" class="btn btn-primary btn-sm">&lt;</a>
                <a href="?month=<?php echo $month+1; ?>&year=<?php echo $year; ?>&habit_id=<?php echo $habit_id; ?>" class="btn btn-primary btn-sm">&gt;</a>
            </div>
        </div>

        <div class="calendar">
            <?php
            $days = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
            foreach ($days as $day) {
                echo "<div class='calendar-day'><strong>$day</strong></div>";
            }

            $firstDay = getFirstDayOfWeek($month, $year);
            $daysInMonth = getDaysInMonth($month, $year);
            $currentDay = 1;
            $today = date('Y-m-d');

            for ($i = 0; $i < 42; $i++) {
                if ($i < $firstDay || $currentDay > $daysInMonth) {
                    echo "<div class='calendar-day'></div>";
                } else {
                    $date = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                    $class = 'calendar-day';
                    if ($date > $today) {
                        $class .= ' future';
                    } elseif (isset($completions[$date])) {
                        $class .= $completions[$date] ? ' completed' : ' not-completed';
                    } else {
                        $class .= ' not-completed';
                    }
                    echo "<div class='$class'>$currentDay</div>";
                    $currentDay++;
                }
            }
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('habit-select').addEventListener('change', function() {
            window.location.href = '?habit_id=' + this.value + '&month=<?php echo $month; ?>&year=<?php echo $year; ?>';
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>