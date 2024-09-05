<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch the ID of the "expense" tag
$sql = "SELECT id FROM tags WHERE name = 'Expense'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $expense_tag_id = $row['id'];
} else {
    // If "expense" tag doesn't exist, create it
    $sql = "INSERT INTO tags (name) VALUES ('Expense')";
    $conn->query($sql);
    $expense_tag_id = $conn->insert_id;
}

// Handle form submission for adding new expense task
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['task'])) {
    $task = $_POST["task"];
    $date = date("Y-m-d");
    $amount = isset($_POST["amount"]) ? floatval($_POST["amount"]) : 0;

    $sql = "INSERT INTO tasks (description, date_added, tag_id, completed, amount) VALUES (?, ?, ?, FALSE, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssid", $task, $date, $expense_tag_id, $amount);
    $stmt->execute();
    $stmt->close();
}

// Handle task deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM tasks WHERE id = ? AND tag_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $expense_tag_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch expense tasks
$sql = "SELECT id, description, date_added, completed FROM tasks WHERE tag_id = ? ORDER BY date_added DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $expense_tag_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tasks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .completed {
            text-decoration: line-through;
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Expense Tasks</h1>
        
        <form method="post" action="" class="mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="task" class="form-control" placeholder="Enter expense task" required>
                </div>
                <div class="col-md-3">
                    <input type="number" name="amount" class="form-control" placeholder="Amount" step="0.01" min="0">
                </div>
                <div class="col-md-3">
                    <input type="submit" value="Add Expense Task" class="btn btn-primary w-100">
                </div>
            </div>
        </form>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Task Description</th>
                        <th>Date Added</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr" . ($row["completed"] ? ' class="completed"' : '') . ">";
                        echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
                        echo "<td>" . $row["date_added"] . "</td>";
                        echo "<td>" . ($row["completed"] ? 'Completed' : 'Pending') . "</td>";
                        echo "<td>";
                        echo "<a href='?delete=" . $row["id"] . "' class='btn btn-danger btn-sm'>Delete</a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5'>No expense tasks found</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
        
        <a href="index.php" class="btn btn-secondary mt-3">Back to All Tasks</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>