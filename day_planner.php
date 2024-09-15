<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Alter tasks table to add planned_time column if not exists
$sql = "SHOW COLUMNS FROM tasks LIKE 'planned_time'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE tasks ADD COLUMN planned_time TIME";
    $conn->query($sql);
}

// Handle task planning
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['task_id']) && isset($_POST['planned_time'])) {
    $task_id = $_POST['task_id'];
    $planned_time = $_POST['planned_time'];
    
    $sql = "UPDATE tasks SET planned_time = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $planned_time, $task_id);
    $stmt->execute();
    $stmt->close();
    
    exit; // End the script here as this is an AJAX request
}

// Fetch pending tasks
$sql = "SELECT id, description, tag_id FROM tasks WHERE completed = FALSE AND planned_time IS NULL";
$pending_tasks = $conn->query($sql);

// Fetch planned tasks
$sql = "SELECT id, description, planned_time FROM tasks WHERE completed = FALSE AND planned_time IS NOT NULL ORDER BY planned_time";
$planned_tasks = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Day Planner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .task-list {
            min-height: 50px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .task-item {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 3px;
            padding: 5px 10px;
            margin-bottom: 5px;
            cursor: move;
            font-size: 0.9rem;
        }
        .timeline {
            display: flex;
            flex-direction: column;
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .hour-slot {
            height: 60px;
            border-bottom: 1px solid #e9ecef;
            position: relative;
            padding-left: 60px;
        }
        .hour-label {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.8rem;
            color: #6c757d;
        }
        .planned-task {
            background-color: #007bff;
            color: #ffffff;
            border-radius: 3px;
            padding: 2px 5px;
            font-size: 0.8rem;
            position: absolute;
            left: 60px;
            right: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Day Planner</h1>
        
        <div class="row">
            <div class="col-md-3">
                <h2 class="h4 mb-3">Pending Tasks</h2>
                <div id="pending-tasks" class="task-list">
                    <?php while($task = $pending_tasks->fetch_assoc()): ?>
                        <div class="task-item" draggable="true" data-task-id="<?php echo $task['id']; ?>">
                            <?php echo htmlspecialchars($task['description']); ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="col-md-9">
                <h2 class="h4 mb-3">Day Timeline</h2>
                <div id="day-timeline" class="timeline">
                    <?php
                    for ($hour = 5; $hour <= 22; $hour++) {
                        $hour_formatted = sprintf("%02d:00", $hour);
                        echo "<div class='hour-slot' data-hour='" . $hour_formatted . "'>";
                        echo "<span class='hour-label'>" . $hour_formatted . "</span>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pendingTasks = document.getElementById('pending-tasks');
            const dayTimeline = document.getElementById('day-timeline');

            // Set up drag and drop for pending tasks
            pendingTasks.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('task-item')) {
                    e.dataTransfer.setData('text/plain', e.target.dataset.taskId);
                }
            });

            // Set up drop zones in the timeline
            dayTimeline.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            dayTimeline.addEventListener('drop', function(e) {
                e.preventDefault();
                const taskId = e.dataTransfer.getData('text');
                const hourSlot = e.target.closest('.hour-slot');
                
                if (hourSlot) {
                    const plannedTime = hourSlot.dataset.hour;
                    const taskElement = document.querySelector(`.task-item[data-task-id="${taskId}"]`);
                    
                    // Send AJAX request to update the task's planned time
                    fetch('day_planner.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `task_id=${taskId}&planned_time=${plannedTime}`
                    })
                    .then(response => response.text())
                    .then(data => {
                        // Move the task to the timeline
                        const plannedTask = document.createElement('div');
                        plannedTask.className = 'planned-task';
                        plannedTask.textContent = taskElement.textContent;
                        plannedTask.dataset.taskId = taskId;
                        hourSlot.appendChild(plannedTask);
                        taskElement.remove();
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                    });
                }
            });

            // Initialize planned tasks
            <?php while($task = $planned_tasks->fetch_assoc()): ?>
            const hourSlot = document.querySelector(`.hour-slot[data-hour="<?php echo substr($task['planned_time'], 0, 5); ?>"]`);
            if (hourSlot) {
                const plannedTask = document.createElement('div');
                plannedTask.className = 'planned-task';
                plannedTask.textContent = "<?php echo addslashes($task['description']); ?>";
                plannedTask.dataset.taskId = "<?php echo $task['id']; ?>";
                hourSlot.appendChild(plannedTask);
            }
            <?php endwhile; ?>
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>