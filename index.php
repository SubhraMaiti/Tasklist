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

// Fetch tasks with optional filters
$filter_tag = isset($_GET['filter_tag']) ? $_GET['filter_tag'] : '';
$show_completed = isset($_GET['show_completed']) ? $_GET['show_completed'] : 'false';
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_added';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

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

$sql .= " ORDER BY " . $sort_by . " " . $sort_order;

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
        .completed {
            background-color: #e0e0e0;
            text-decoration: line-through;
        }
        .sortable {
            cursor: pointer;
        }
        .sortable::after {
            content: "\25B2\25BC";
            font-size: 0.7em;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Task Manager V0.5</h1>
        
        <!-- Keep the existing form for adding tasks -->
        
        <h2 class="mb-3">Task List</h2>
        <form method="get" action="" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
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
                <div class="col-md-3">
                    <input type="date" name="filter_date" class="form-control" value="<?php echo $filter_date; ?>">
                </div>
                <div class="col-md-3">
                    <select name="show_completed" class="form-select">
                        <option value="false" <?php echo ($show_completed === 'false') ? 'selected' : ''; ?>>Hide Completed</option>
                        <option value="true" <?php echo ($show_completed === 'true') ? 'selected' : ''; ?>>Show Completed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="submit" value="Apply Filters" class="btn btn-secondary w-100">
                </div>
            </div>
        </form>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Serial Number</th>
                        <th class="sortable" data-sort="description">Task Description</th>
                        <th class="sortable" data-sort="date_added">Date Added</th>
                        <th class="sortable" data-sort="tag_name">Tag</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($result->num_rows > 0) {
                    $serial_number = 1;
                    while($row = $result->fetch_assoc()) {
                        echo "<tr" . ($row["completed"] ? ' class="completed"' : '') . ">";
                        echo "<td>" . $serial_number . "</td>";
                        echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
                        echo "<td>" . $row["date_added"] . "</td>";
                        echo "<td>" . htmlspecialchars($row["tag_name"]) . "</td>";
                        echo "<td>";
                        if (!$row["completed"]) {
                            echo "<form method='post' action='' class='d-inline'>";
                            echo "<input type='hidden' name='complete_task' value='" . $row["id"] . "'>";
                            echo "<button type='submit' class='btn btn-success btn-sm'>Complete</button>";
                            echo "</form>";
                        }
                        echo "<a href='?delete=" . $row["id"] . "&filter_tag=" . $filter_tag . "&show_completed=" . $show_completed . "&filter_date=" . $filter_date . "&sort_by=" . $sort_by . "&sort_order=" . $sort_order . "' class='btn btn-danger btn-sm ms-2'>Delete</a>";
                        echo "</td>";
                        echo "</tr>";
                        $serial_number++;
                    }
                } else {
                    echo "<tr><td colspan='5'>No tasks found</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sortableHeaders = document.querySelectorAll('.sortable');
            sortableHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const sort = this.dataset.sort;
                    let order = 'ASC';
                    
                    // Get current URL parameters
                    const urlParams = new URLSearchParams(window.location.search);
                    
                    // If already sorting by this column, toggle the order
                    if (urlParams.get('sort_by') === sort && urlParams.get('sort_order') === 'ASC') {
                        order = 'DESC';
                    }
                    
                    // Update URL parameters
                    urlParams.set('sort_by', sort);
                    urlParams.set('sort_order', order);
                    
                    // Redirect to the new URL
                    window.location.search = urlParams.toString();
                });
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>