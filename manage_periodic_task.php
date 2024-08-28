<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Alter periodic_tasks table to add day_of_week and day_of_month columns
$sql = "SHOW COLUMNS FROM periodic_tasks LIKE 'day_of_week'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE periodic_tasks ADD COLUMN day_of_week INT(1)";
    $conn->query($sql);
}

$sql = "SHOW COLUMNS FROM periodic_tasks LIKE 'day_of_month'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE periodic_tasks ADD COLUMN day_of_month INT(2)";
    $conn->query($sql);
}

// Handle form submission for adding new task
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    $description = $_POST["description"];
    $tag = $_POST["tag"];
    $frequency = $_POST["frequency"];
    $specific_date = ($frequency == 'specific_date') ? $_POST["specific_date"] : null;
    $day_of_week = ($frequency == 'weekly') ? $_POST["day_of_week"] : null;
    $day_of_month = ($frequency == 'monthly') ? $_POST["day_of_month"] : null;

    // Handle new tag
    if ($tag == "new" && !empty($_POST["new_tag"])) {
        $new_tag = $_POST["new_tag"];
        $sql = "INSERT IGNORE INTO tags (name) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $new_tag);
        $stmt->execute();
        $tag_id = $stmt->insert_id;
        $stmt->close();
    } elseif ($tag != "new") {
        $tag_id = $tag;
    } else {
        $tag_id = null;
    }

    $sql = "INSERT INTO periodic_tasks (description, tag_id, frequency, specific_date, day_of_week, day_of_month) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sissii", $description, $tag_id, $frequency, $specific_date, $day_of_week, $day_of_month);
    $stmt->execute();
    $stmt->close();
}

// Handle deletion of periodic task
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $task_id = $_POST['task_id'];
    $sql = "DELETE FROM periodic_tasks WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch periodic tasks with frequency filter
$frequency_filter = isset($_GET['frequency']) ? $_GET['frequency'] : '';
$sql = "SELECT periodic_tasks.*, tags.name AS tag_name 
        FROM periodic_tasks 
        LEFT JOIN tags ON periodic_tasks.tag_id = tags.id";
if ($frequency_filter) {
    $sql .= " WHERE periodic_tasks.frequency = ?";
}
$sql .= " ORDER BY periodic_tasks.id DESC";
$stmt = $conn->prepare($sql);
if ($frequency_filter) {
    $stmt->bind_param("s", $frequency_filter);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch tags for dropdown
$sql = "SELECT * FROM tags ORDER BY name";
$tags_result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Periodic Tasks</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .frequency-filter {
            cursor: pointer;
            position: relative;
        }
        .frequency-dropdown {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 120px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
        }
        .frequency-dropdown a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        .frequency-dropdown a:hover {
            background-color: #f1f1f1;
        }
    </style>
</head>
<body>
    <h1>Manage Periodic Tasks</h1>
    
    <form method="post" action="">
        <input type="hidden" name="action" value="add">
        <input type="text" name="description" placeholder="Enter task description" required>
        <select name="tag" id="tag-select">
            <option value="">Select a tag</option>
            <?php 
            while($tag = $tags_result->fetch_assoc()): 
            ?>
                <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
            <?php endwhile; ?>
            <option value="new">Add new tag</option>
        </select>
        <input type="text" name="new_tag" id="new-tag-input" placeholder="Enter new tag" style="display: none;">
        <select name="frequency" id="frequency-select">
            <option value="monthly">Monthly</option>
            <option value="weekly">Weekly</option>
            <option value="specific_date">Specific Date</option>
        </select>
        <select name="day_of_week" id="day-of-week-select" style="display: none;">
            <option value="1">Monday</option>
            <option value="2">Tuesday</option>
            <option value="3">Wednesday</option>
            <option value="4">Thursday</option>
            <option value="5">Friday</option>
            <option value="6">Saturday</option>
            <option value="7">Sunday</option>
        </select>
        <select name="day_of_month" id="day-of-month-select" style="display: none;">
            <?php for ($i = 1; $i <= 31; $i++): ?>
                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
            <?php endfor; ?>
        </select>
        <input type="date" name="specific_date" id="specific-date-input" style="display: none;">
        <input type="submit" value="Add Periodic Task">
    </form>
    
    <h2>Periodic Tasks List</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Description</th>
            <th>Tag</th>
            <th class="frequency-filter">
                Frequency
                <div class="frequency-dropdown">
                    <a href="?">All</a>
                    <a href="?frequency=monthly">Monthly</a>
                    <a href="?frequency=weekly">Weekly</a>
                    <a href="?frequency=specific_date">Specific Date</a>
                </div>
            </th>
            <th>Specific Date</th>
            <th>Day of Week</th>
            <th>Day of Month</th>
            <th>Last Added</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["id"] . "</td>";
                echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
                echo "<td>" . htmlspecialchars($row["tag_name"]) . "</td>";
                echo "<td>" . $row["frequency"] . "</td>";
                echo "<td>" . ($row["specific_date"] ? $row["specific_date"] : "N/A") . "</td>";
                echo "<td>" . ($row["day_of_week"] ? date('l', strtotime("Sunday +{$row['day_of_week']} days")) : "N/A") . "</td>";
                echo "<td>" . ($row["day_of_month"] ? $row["day_of_month"] : "N/A") . "</td>";
                echo "<td>" . ($row["last_added"] ? $row["last_added"] : "Not yet added") . "</td>";
                echo "<td>
                        <form method='post' action='' onsubmit='return confirm(\"Are you sure you want to delete this task?\");'>
                            <input type='hidden' name='action' value='delete'>
                            <input type='hidden' name='task_id' value='" . $row["id"] . "'>
                            <input type='submit' value='Delete' class='delete-btn'>
                        </form>
                      </td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='9'>No periodic tasks found</td></tr>";
        }
        ?>
    </table>

    <script>
        document.getElementById('tag-select').addEventListener('change', function() {
            var newTagInput = document.getElementById('new-tag-input');
            newTagInput.style.display = this.value === 'new' ? 'inline-block' : 'none';
        });

        document.getElementById('frequency-select').addEventListener('change', function() {
            var dayOfWeekSelect = document.getElementById('day-of-week-select');
            var dayOfMonthSelect = document.getElementById('day-of-month-select');
            var specificDateInput = document.getElementById('specific-date-input');

            dayOfWeekSelect.style.display = 'none';
            dayOfMonthSelect.style.display = 'none';
            specificDateInput.style.display = 'none';

            if (this.value === 'weekly') {
                dayOfWeekSelect.style.display = 'inline-block';
            } else if (this.value === 'monthly') {
                dayOfMonthSelect.style.display = 'inline-block';
            } else if (this.value === 'specific_date') {
                specificDateInput.style.display = 'inline-block';
            }
        });

        document.querySelector('.frequency-filter').addEventListener('click', function(e) {
            e.stopPropagation();
            var dropdown = this.querySelector('.frequency-dropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });

        document.addEventListener('click', function() {
            document.querySelector('.frequency-dropdown').style.display = 'none';
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>