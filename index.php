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
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$show_completed = isset($_GET['show_completed']) ? $_GET['show_completed'] : 'false';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_added';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

$sql = "SELECT tasks.id, tasks.description, tasks.date_added, tags.name AS tag_name, tags.id AS tag_id, tasks.completed 
        FROM tasks 
        LEFT JOIN tags ON tasks.tag_id = tags.id
        WHERE 1=1";

if (!empty($filter_tag)) {
    $sql .= " AND tasks.tag_id = ?";
}

if (!empty($filter_date)) {
    $sql .= " AND DATE(tasks.date_added) = ?";
}

if ($show_completed === 'false') {
    $sql .= " AND tasks.completed = FALSE";
}

// Fetch the ID of the "expense" tag
$fetch_tag_id_sql = "SELECT id FROM tags WHERE name = 'Expense'";
$result = $conn->query($fetch_tag_id_sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $expense_tag_id = $row['id'];
} else {
    // If "expense" tag doesn't exist, create it
    $fetch_tag_id_sql = "INSERT INTO tags (name) VALUES ('Expense')";
    $conn->query($fetch_tag_id_sql);
    $expense_tag_id = $conn->insert_id;
}

//don't fecth tasks with tag name expense
$sql .= " AND tag_id != ". $expense_tag_id;


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
            position: relative;
        }
        .sortable::after {
            content: "\25B2\25BC";
            font-size: 0.7em;
            margin-left: 5px;
        }
        .filter-dropdown {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
        }
        .filter-dropdown a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        .filter-dropdown a:hover {
            background-color: #f1f1f1;
        }
        #date-filter {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            padding: 10px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Task Manager V0.9</h1>
        
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
        
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="show-completed" <?php echo $show_completed === 'true' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="show-completed">Show Completed Tasks</label>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Serial Number</th>
                        <th class="sortable" data-sort="description">Task Description</th>
                        <th class="sortable" data-sort="date_added">
                            Date Added
                            <div id="date-filter">
                                <input type="date" id="date-input" class="form-control">
                            </div>
                        </th>
                        <th class="sortable" data-sort="tag_name">
                            Tag
                            <div class="filter-dropdown" id="tag-filter-dropdown">
                                <a href="?filter_tag=">All Tags</a>
                                <?php 
                                $tags_result->data_seek(0);
                                while($tag = $tags_result->fetch_assoc()): 
                                ?>
                                    <a href="?filter_tag=<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></a>
                                <?php endwhile; ?>
                            </div>
                        </th>
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
                        echo "<a href='?delete=" . $row["id"] . "&sort_by=" . $sort_by . "&sort_order=" . $sort_order . "' class='btn btn-danger btn-sm ms-2'>Delete</a>";
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
    const dateAddedHeader = document.querySelector('.sortable[data-sort="date_added"]');
    const dateFilter = document.getElementById('date-filter');
    const dateInput = document.getElementById('date-input');
    const tagFilterDropdown = document.getElementById('tag-filter-dropdown');

    sortableHeaders.forEach(header => {
        header.addEventListener('click', function(e) {
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
            
            // Handle filtering
            if (sort === 'tag_name') {
                e.stopPropagation(); // Prevent immediate redirection
                tagFilterDropdown.style.display = tagFilterDropdown.style.display === 'block' ? 'none' : 'block';
            } else if (sort !== 'date_added') {
                // Redirect to the new URL if not handling tag or date filter
                window.location.search = urlParams.toString();
            }
        });
    });

    // Date Added header click event
    dateAddedHeader.addEventListener('click', function(e) {
        e.stopPropagation(); // Prevent event from bubbling up
        dateFilter.style.display = dateFilter.style.display === 'block' ? 'none' : 'block';
    });

    // Date input change event
    dateInput.addEventListener('change', function() {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('filter_date', this.value);
        window.location.search = urlParams.toString();
    });

    // Handle new tag input
    const tagSelect = document.getElementById('tag-select');
    const newTagContainer = document.getElementById('new-tag-container');
    const newTagInput = document.getElementById('new-tag-input');

    tagSelect.addEventListener('change', function() {
        if (this.value === 'new') {
            newTagContainer.style.display = 'block';
            newTagInput.required = true;
        } else {
            newTagContainer.style.display = 'none';
            newTagInput.required = false;
        }
    });

    // Close the dropdowns when clicking outside of them
    window.addEventListener('click', function(e) {
        if (!dateAddedHeader.contains(e.target) && !dateFilter.contains(e.target)) {
            dateFilter.style.display = 'none';
        }
        if (!e.target.matches('.sortable[data-sort="tag_name"]')) {
            tagFilterDropdown.style.display = 'none';
        }
    });

    // Handle show completed tasks checkbox
    const showCompletedCheckbox = document.getElementById('show-completed');
    showCompletedCheckbox.addEventListener('change', function() {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('show_completed', this.checked ? 'true' : 'false');
        window.location.search = urlParams.toString();
    });

    // Set the date input value if there's a filter_date in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const filterDate = urlParams.get('filter_date');
    if (filterDate) {
        dateInput.value = filterDate;
    }
});
    </script>
</body>
</html>

<?php
$conn->close();
?>