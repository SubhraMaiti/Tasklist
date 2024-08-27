<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ... (keep all the existing PHP code as is) ...

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
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Task Manager V0.4</h1>
        
        <form method="post" action="" class="mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="task" class="form-control" placeholder="Enter your task" required>
                </div>
                <div class="col-md-4">
                    <select name="tag" id="tag-select" class="form-select">
                        <option value="">Select a tag</option>
                        <?php 
                        $tags_result->data_seek(0);
                        while($tag = $tags_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                        <?php endwhile; ?>
                        <option value="new">Add new tag</option>
                    </select>
                </div>
                <div class="col-md-4 mt-2" id="new-tag-container" style="display: none;">
                    <input type="text" name="new_tag" id="new-tag-input" class="form-control" placeholder="Enter new tag">
                </div>
                <div class="col-md-2">
                    <input type="submit" value="Add Task" class="btn btn-primary w-100">
                </div>
            </div>
        </form>
        
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
                    <input type="submit" value="Filter" class="btn btn-secondary w-100">
                </div>
            </div>
        </form>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover">
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
                        echo "<a href='?delete=" . $row["id"] . "&filter_tag=" . $filter_tag . "&show_completed=" . $show_completed . "&filter_date=" . $filter_date . "' class='btn btn-danger btn-sm ms-2'>Delete</a>";
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
    <script src="index_script.js"></script>
</body>
</html>

<?php
$conn->close();
?>