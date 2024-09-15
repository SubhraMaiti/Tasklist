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
        .task-list {
            min-height: 50px;
            border: 1px solid #ddd;
            padding: 10px;
        }
        .task-item {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 5px;
            margin-bottom: 5px;
            cursor: move;
        }
        .timeline {
            display: flex;
            flex-direction: column;
        }
        .hour-slot {
            height: 60px;
            border-bottom: 1px solid #ddd;
            position: relative;
        }
        .hour-label {
            position: absolute;
            left: -50px;
            top: -10px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Day Planner</h1>
        
        <div class="row">
            <div class="col-md-4">
                <h2>Pending Tasks</h2>
                <div id="pending-tasks" class="task-list">
                    <?php while($task = $pending_tasks->fetch_assoc()): ?>
                        <div class="task-item" draggable="true" data-task-id="<?php echo $task['id']; ?>">
                            <?php echo htmlspecialchars($task['description']); ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="col-md-8">
                <h2>Day Timeline</h2>
                <div id="day-timeline" class="timeline">
                    <?php
                    for ($hour = 0; $hour < 24; $hour++) {
                        echo "<div class='hour-slot' data-hour='" . sprintf("%02d:00", $hour) . "'>";
                        echo "<span class='hour-label'>" . sprintf("%02d:00", $hour) . "</span>";
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
                        hourSlot.appendChild(taskElement);
                        taskElement.style.position = 'absolute';
                        taskElement.style.top = '0';
                        taskElement.style.width = '100%';
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                    });
                }
            });

            // Initialize planned tasks
            <?php while($task = $planned_tasks->fetch_assoc()): ?>
            const plannedTask = document.querySelector(`.task-item[data-task-id="<?php echo $task['id']; ?>"]`);
            if (plannedTask) {
                const hourSlot = document.querySelector(`.hour-slot[data-hour="<?php echo substr($task['planned_time'], 0, 5); ?>"]`);
                if (hourSlot) {
                    hourSlot.appendChild(plannedTask);
                    plannedTask.style.position = 'absolute';
                    plannedTask.style.top = '0';
                    plannedTask.style.width = '100%';
                }
            }
            <?php endwhile; ?>
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>