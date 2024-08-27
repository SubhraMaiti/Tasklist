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

// Alter tasks table to add tag_id and completed columns if not exists
$sql = "SHOW COLUMNS FROM tasks LIKE 'tag_id'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE tasks ADD COLUMN tag_id INT(6) UNSIGNED, ADD FOREIGN KEY (tag_id) REFERENCES tags(id)";
    $conn->query($sql);
}

$sql = "SHOW COLUMNS FROM tasks LIKE 'completed'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE tasks ADD COLUMN completed BOOLEAN DEFAULT FALSE";
    $conn->query($sql);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['task'])) {
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
        
        $sql = "INSERT INTO tasks (description, date_added, tag_id, completed) VALUES (?, ?, ?, FALSE)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $task, $date, $tag_id);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['complete_task'])) {
        $task_id = $_POST['complete_task'];
        $sql = "UPDATE tasks SET completed = TRUE WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $stmt->close();
    }
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

// Fetch tasks with optional tag filter and completion status
$filter_tag = isset($_GET['filter_tag']) ? $_GET['filter_tag'] : '';
$show_completed = isset($_GET['show_completed']) ? $_GET['show_completed'] : 'false';
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';

$sql = "SELECT tasks.id, tasks.description, tasks.date_added, tags.name AS tag_name, tags.id AS tag_id, tasks.completed 
        FROM tasks 
        LEFT JOIN tags ON tasks.tag_id = tags.id
        WHERE 1=1";

if (!empty($filter_tag)) {
    $sql .= " AND tasks.tag_id = ?";
}

if ($show_completed === 'false') {
    $sql .= " AND tasks.completed = FALSE";
}

if (!empty($filter_date)) {
    $sql .= " AND tasks.date_added = ?";
}

$sql .= " ORDER BY tasks.date_added DESC";

$stmt = $conn->prepare($sql);

if (!empty($filter_tag) && !empty($filter_date)) {
    $stmt->bind_param("is", $filter_tag, $filter_date);
} elseif (!empty($filter_tag)) {
    $stmt->bind_param("i", $filter_tag);
} elseif (!empty($filter_date)) {
    $stmt->bind_param("s", $filter_date);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .completed {
            background-color: #e9ecef;
            text-decoration: line-through;
        }
        @media (max-width: 576px) {
            .table-responsive {
                border: none;
            }
            .table thead {
                display: none;
            }
            .table, .table tbody, .table tr, .table td {
                display: block;
                width: 100%;
            }
            .table tr {
                margin-bottom: 15px;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                overflow: hidden;
            }
            .table td {
                text-align: right;
                padding-left: 50%;
                position: relative;
            }
            .table td::before {
                content: attr(data-label);
                position: absolute;
                left: 6px;
                width: 45%;
                text-align: left;
                font-weight: bold;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4 text-center">Task Manager V0.4</h1>
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="post" action="">
                    <div class="mb-3">
                        <input type="text" name="task" class="form-control" placeholder="Enter your task" required>
                    </div>
                    <div class="mb-3">
                        <select name="tag" id="tag-select" class="form-select">
                            <option value="">Select a tag</option>
                            <?php 
                            while($tag = $tags_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                            <?php endwhile; ?>
                            <option value="new">Add new tag</option>
                        </select>
                    </div>
                    <div class="mb-3" id="new-tag-container" style="display: none;">
                        <input type="text" name="new_tag" id="new-tag-input" class="form-control" placeholder="Enter new tag">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Add Task</button>
                    </div>
                </form>
            </div>
        </div>
        
        <h2 class="mb-3">Task List</h2>
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="">
                    <div class="mb-3">
                        <select name="filter_tag" class="form-select">
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
                    </div>
                    <div class="mb-3">
                        <input type="date" name="filter_date" class="form-control" value="<?php echo $filter_date; ?>">
                    </div>
                    <div class="mb-3">
                        <select name="show_completed" class="form-select">
                            <option value="false" <?php echo ($show_completed === 'false') ? 'selected' : ''; ?>>Hide Completed</option>
                            <option value="true" <?php echo ($show_completed === 'true') ? 'selected' : ''; ?>>Show Completed</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-secondary">Filter</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Serial Number</th>
                        <th>Task Description</th>
                        <th>Date Added</th>
                        <th>Tag</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($result->num_rows > 0) {
                    $serial_number = 1;
                    while($row = $result->fetch_assoc()) {
                        echo "<tr" . ($row["completed"] ? ' class="completed"' : '') . ">";
                        echo "<td data-label='Serial Number'>" . $serial_number . "</td>";
                        echo "<td data-label='Task Description'>" . htmlspecialchars($row["description"]) . "</td>";
                        echo "<td data-label='Date Added'>" . $row["date_added"] . "</td>";
                        echo "<td data-label='Tag'>" . htmlspecialchars($row["tag_name"]) . "</td>";
                        echo "<td data-label='Action'>";
                        if (!$row["completed"]) {
                            echo "<form method='post' action='' class='d-inline me-2'>";
                            echo "<input type='hidden' name='complete_task' value='" . $row["id"] . "'>";
                            echo "<button type='submit' class='btn btn-success btn-sm'>Complete</button>";
                            echo "</form>";
                        }
                        echo "<a href='?delete=" . $row["id"] . "&filter_tag=" . $filter_tag . "&show_completed=" . $show_completed . "&filter_date=" . $filter_date . "' class='btn btn-danger btn-sm'>Delete</a>";
                        echo "</td>";
                        echo "</tr>";
                        $serial_number++;
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center'>No tasks found</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="index_script.js"></script>
</body>
</html>

<?php
$conn->close();
?>