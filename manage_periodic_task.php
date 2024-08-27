<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ... (previous code remains unchanged)

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
    
    <!-- ... (form and other HTML elements remain unchanged) -->
    
    <h2>Periodic Tasks List</h2>
    <table>
        <tr>
            <th>No.</th>
            <th>Description</th>
            <th>Tag</th>
            <th>Frequency</th>
            <th>Specific Date</th>
            <th>Day of Week</th>
            <th>Day of Month</th>
            <th>Last Added</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            $row_number = 1; // Initialize the row number counter
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row_number . "</td>"; // Display the continuous row number
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
                $row_number++; // Increment the row number for the next iteration
            }
        } else {
            echo "<tr><td colspan='9'>No periodic tasks found</td></tr>";
        }
        ?>
    </table>

    <script src="script.js"></script>
</body>
</html>

<?php
$conn->close();
?>