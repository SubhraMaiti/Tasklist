<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tags table if not exists
$sql = "CREATE TABLE IF NOT EXISTS tags (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE
)";
$conn->query($sql);

// Alter tasks table to add tag_id column if not exists
$sql = "SHOW COLUMNS FROM tasks LIKE 'tag_id'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE tasks ADD COLUMN tag_id INT(6) UNSIGNED, ADD FOREIGN KEY (tag_id) REFERENCES tags(id)";
    $conn->query($sql);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $task = $_POST["task"];
    $date = date("Y-m-d");
    $tag = $_POST["tag"];
    
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
    
    $sql = "INSERT INTO tasks (description, date_added, tag_id) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $task, $date, $tag_id);
    $stmt->execute();
    $stmt->close();
}

// Handle task deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM tasks WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

// Fetch tasks with optional tag filter
$filter_tag = isset($_GET['filter_tag']) ? $_GET['filter_tag'] : '';
$sql = "SELECT tasks.id, tasks.description, tasks.date_added, tags.name AS tag_name, tags.id AS tag_id 
        FROM tasks 
        LEFT JOIN tags ON tasks.tag_id = tags.id";
if (!empty($filter_tag)) {
    $sql .= " WHERE tasks.tag_id = ?";
}
$sql .= " ORDER BY tasks.date_added DESC";

$stmt = $conn->prepare($sql);
if (!empty($filter_tag)) {
    $stmt->bind_param("i", $filter_tag);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Fetch tags for dropdown
$sql = "SELECT * FROM tags ORDER BY name";
$tags_result = $conn->query($sql);
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
        input[type="text"], select {
            width: 30%;
            padding: 10px;
            margin-right: 10px;
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
        .delete {
            color: red;
            text-decoration: none;
        }
        .filter-form {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1>Task Manager V0.3</h1>
    
    <form method="post" action="">
        <input type="text" name="task" placeholder="Enter your task" required>
        <select name="tag" id="tag-select">
            <option value="">Select a tag</option>
            <?php 
            $tags_result->data_seek(0);
            while($tag = $tags_result->fetch_assoc()): 
            ?>
                <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
            <?php endwhile; ?>
            <option value="new">Add new tag</option>
        </select>
        <input type="text" name="new_tag" id="new-tag-input" placeholder="Enter new tag" style="display: none;">
        <input type="submit" value="Add Task">
    </form>
    
    <h2>Task List</h2>
    <form method="get" action="" class="filter-form">
        <select name="filter_tag">
            <option value="">All Tags</option>
            <?php 
            $tags_result->data_seek(0);
            while($tag = $tags_result->fetch_assoc()): 
            ?>
                <option value="<?php echo $tag['id']; ?>" <?php echo ($filter_tag == $tag['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($tag['name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <input type="submit" value="Filter">
    </form>
    
    <table>
        <tr>
            <th>Serial Number</th>
            <th>Task Description</th>
            <th>Date Added</th>
            <th>Tag</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            $serial_number = 1;
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $serial_number . "</td>";
                echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
                echo "<td>" . $row["date_added"] . "</td>";
                echo "<td>" . htmlspecialchars($row["tag_name"]) . "</td>";
                echo "<td><a href='?delete=" . $row["id"] . "&filter_tag=" . $filter_tag . "' class='delete'>Delete</a></td>";
                echo "</tr>";
                $serial_number++;
            }
        } else {
            echo "<tr><td colspan='5'>No tasks found</td></tr>";
        }
        ?>
    </table>

    <script>
        document.getElementById('tag-select').addEventListener('change', function() {
            var newTagInput = document.getElementById('new-tag-input');
            if (this.value === 'new') {
                newTagInput.style.display = 'inline-block';
                newTagInput.required = true;
            } else {
                newTagInput.style.display = 'none';
                newTagInput.required = false;
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>