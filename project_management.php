<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Create projects table if not exists
if (!tableExists($conn, 'projects')) {
    $sql = "CREATE TABLE projects (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql)) {
        die("Error creating projects table: " . $conn->error);
    }
}

// Create project_parts table if not exists
if (!tableExists($conn, 'project_parts')) {
    $sql = "CREATE TABLE project_parts (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id INT(6) UNSIGNED,
        parent_id INT(6) UNSIGNED NULL,
        name VARCHAR(255) NOT NULL,
        level INT(6) NOT NULL
    )";
    if (!$conn->query($sql)) {
        die("Error creating project_parts table: " . $conn->error);
    }

    // Add foreign key constraints
    $sql = "ALTER TABLE project_parts
        ADD CONSTRAINT fk_project
        FOREIGN KEY (project_id) 
        REFERENCES projects(id)
        ON DELETE CASCADE";
    if (!$conn->query($sql)) {
        die("Error adding fk_project constraint: " . $conn->error);
    }

    $sql = "ALTER TABLE project_parts
        ADD CONSTRAINT fk_parent
        FOREIGN KEY (parent_id) 
        REFERENCES project_parts(id)
        ON DELETE CASCADE";
    if (!$conn->query($sql)) {
        die("Error adding fk_parent constraint: " . $conn->error);
    }
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['new_project'])) {
        $project_name = $_POST['new_project'];
        $sql = "INSERT INTO projects (name) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $project_name);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['new_part'])) {
        $project_id = $_POST['project_id'];
        $parent_id = $_POST['parent_id'] !== '' ? $_POST['parent_id'] : null;
        $part_name = $_POST['new_part'];
        $level = $_POST['level'];
        $sql = "INSERT INTO project_parts (project_id, parent_id, name, level) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisi", $project_id, $parent_id, $part_name, $level);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['add_to_tasklist'])) {
        $part_id = $_POST['part_id'];
        $sql = "SELECT p.name AS part_name, pr.name AS project_name 
                FROM project_parts p 
                JOIN projects pr ON p.project_id = pr.id 
                WHERE p.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $part_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $task_description = $row['part_name'] . " (Project: " . $row['project_name'] . ")";
        $date = date("Y-m-d");
        
        // Get the tag_id for "Personal Project"
        $sql = "SELECT id FROM tags WHERE name = 'Personal Project'";
        $result = $conn->query($sql);
        if ($result->num_rows == 0) {
            // Create the "Personal Project" tag if it doesn't exist
            $sql = "INSERT INTO tags (name) VALUES ('Personal Project')";
            $conn->query($sql);
            $tag_id = $conn->insert_id;
        } else {
            $tag_row = $result->fetch_assoc();
            $tag_id = $tag_row['id'];
        }

        $sql = "INSERT INTO tasks (description, date_added, tag_id, completed) VALUES (?, ?, ?, FALSE)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $task_description, $date, $tag_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle part deletion
if (isset($_GET['delete_part'])) {
    $part_id = $_GET['delete_part'];
    $sql = "DELETE FROM project_parts WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $part_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch projects
$sql = "SELECT * FROM projects ORDER BY created_at DESC";
$projects_result = $conn->query($sql);

// Function to fetch project parts
function fetchProjectParts($conn, $project_id, $parent_id = null, $level = 0) {
    $sql = "SELECT * FROM project_parts WHERE project_id = ? AND parent_id " . ($parent_id === null ? "IS NULL" : "= ?") . " ORDER BY name";
    $stmt = $conn->prepare($sql);
    if ($parent_id === null) {
        $stmt->bind_param("i", $project_id);
    } else {
        $stmt->bind_param("ii", $project_id, $parent_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $parts = [];
    while ($row = $result->fetch_assoc()) {
        $row['children'] = fetchProjectParts($conn, $project_id, $row['id'], $level + 1);
        $parts[] = $row;
    }
    return $parts;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .project-tree ul {
            list-style-type: none;
            padding-left: 20px;
        }
        .project-tree li {
            margin: 10px 0;
        }
        .add-part-form {
            display: none;
            margin-left: 20px;
        }
        .toggle-children {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Project Management</h1>
        
        <!-- Create new project form -->
        <form method="post" action="" class="mb-4" name="new-project-form">
            <div class="input-group">
                <input type="text" name="new_project" class="form-control" placeholder="Enter new project name" required>
                <button type="submit" class="btn btn-primary">Create Project</button>
            </div>
        </form>
        
        <!-- Project list -->
        <div class="row">
            <div class="col-md-4">
                <h2>Projects</h2>
                <ul class="list-group">
                    <?php while ($project = $projects_result->fetch_assoc()): ?>
                        <li class="list-group-item">
                            <a href="#" class="project-link" data-project-id="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
            
            <div class="col-md-8">
                <div id="project-details"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initial load of event handlers
            initializeEventHandlers();

            // Load project details
            $(document).on('click', '.project-link', function(e) {
                e.preventDefault();
                var projectId = $(this).data('project-id');
                loadProjectDetails(projectId);
            });

            // Handle new project form submission
            $('form[name="new-project-form"]').submit(function(e) {
                e.preventDefault();
                var form = $(this);
                $.post('', form.serialize(), function(response) {
                    location.reload(); // Reload the page to show the new project
                });
            });
        });

        function loadProjectDetails(projectId) {
            $.get('get_project_details.php', { project_id: projectId }, function(response) {
                $('#project-details').html(response);
                initializeEventHandlers();
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error("Error loading project details:", textStatus, errorThrown);
            });
        }

        function initializeEventHandlers() {
            // Toggle children visibility
            $('.toggle-children').off('click').on('click', function() {
                $(this).toggleClass('fa-chevron-right fa-chevron-down');
                $(this).closest('li').children('ul').toggle();
            });

            // Show add sub-part form
            $('.add-subpart').off('click').on('click', function() {
                $(this).closest('li').find('> .add-part-form').toggle();
            });

            // Handle add part form submission
            $('.add-part-form').off('submit').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                $.post('', form.serialize(), function(response) {
                    loadProjectDetails(form.find('[name="project_id"]').val());
                });
            });

            // Handle delete part
            $('.delete-part').off('click').on('click', function(e) {
                e.preventDefault();
                var partId = $(this).data('part-id');
                var projectId = $(this).data('project-id');
                if (confirm('Are you sure you want to delete this part?')) {
                    $.get('?delete_part=' + partId, function() {
                        loadProjectDetails(projectId);
                    });
                }
            });

            // Handle add to tasklist
            $('.add-to-tasklist').off('click').on('click', function(e) {
                e.preventDefault();
                var partId = $(this).data('part-id');
                var projectId = $(this).data('project-id');
                $.post('', { add_to_tasklist: true, part_id: partId }, function() {
                    alert('Task added to the task list!');
                    loadProjectDetails(projectId);
                });
            });

            // Add this new handler for the top-level "Add Part" form
            $('.add-top-level-part-form').off('submit').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                $.post('', form.serialize(), function(response) {
                    loadProjectDetails(form.find('[name="project_id"]').val());
                });
            });
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>