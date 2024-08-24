<?php
// Database connection
$servername = "localhost";
$username = "taskmaster";
$password = "master@123!!";
$dbname = "Projects";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $task = $_POST["task"];
    $date = date("Y-m-d");
    
    $sql = "INSERT INTO tasks (description, date_added) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $task, $date);
    $stmt->execute();
    $stmt->close();
}

// Fetch tasks
$sql = "SELECT * FROM tasks ORDER BY date_added DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        form {
            margin-bottom: 20px;
        }
        input[type="text"] {
            width: 70%;
            padding: 10px;
        }
        input[type="submit"] {
            padding: 10px 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h1>Task Manager</h1>
    
    <form method="post" action="">
        <input type="text" name="task" placeholder="Enter your task" required>
        <input type="submit" value="Submit">
    </form>
    
    <h2>Task List</h2>
    <table>
        <tr>
            <th>Serial Number</th>
            <th>Task Description</th>
            <th>Date Added</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            $serial_number = 1;
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $serial_number . "</td>";
                echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
                echo "<td>" . $row["date_added"] . "</td>";
                echo "</tr>";
                $serial_number++;
            }
        } else {
            echo "<tr><td colspan='3'>No tasks found</td></tr>";
        }
        ?>
    </table>
</body>
</html>

<?php
$conn->close();
?>
