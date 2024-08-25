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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

// Fetch periodic tasks
$sql = "SELECT periodic_tasks.*, tags.name AS tag_name 
        FROM periodic_tasks 
        LEFT JOIN tags ON periodic_tasks.tag_id = tags.id
        ORDER BY periodic_tasks.id DESC";
$result = $conn->query($sql);

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
</head>
<body>
    <h1>Manage Periodic Tasks</h1>
    
    <form method="post" action="">
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
            <th>Frequency</th>
            <th>Specific Date</th>
            <th>Day of Week</th>
            <th>Day of Month</th>
            <th>Last Added</th>
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
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='8'>No periodic tasks found</td></tr>";
        }
        ?>
    </table>

    <script src="script.js"></script>
</body>
</html>

<?php
$conn->close();
?>