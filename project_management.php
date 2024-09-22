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

//handle project deletion
if (isset($_GET['delete_project'])) {
    $project_id = $_GET['delete_project'];
    $sql = "DELETE FROM projects WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $stmt->close();
    // Redirect to refresh the page after deletion
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .project-tree ul {
            list-style-type: none;
        }
        .project-tree li {
            margin: 10px 0;
        }
        .add-part-form {
            display: none;
        }
        .toggle-children {
            cursor: pointer;
        }
        .project-link {
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Project Management</h1>
        
        <!-- Create new project form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Create New Project</h2>
            <form method="post" action="" class="flex" name="new-project-form">
                <input type="text" name="new_project" class="flex-grow mr-2 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter new project name" required>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Create Project</button>
            </form>
        </div>
        
        <!-- Project list and details -->
        <div class="flex flex-col md:flex-row space-y-8 md:space-y-0 md:space-x-8">
            <div class="w-full md:w-1/3">
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">Projects</h2>
                    <ul class="space-y-2">
                        <?php while ($project = $projects_result->fetch_assoc()): ?>
                            <li class="flex justify-between items-center">
                                <a href="#" class="project-link text-blue-500 hover:text-blue-700 font-bold" data-project-id="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></a>
                                <button class="delete-project text-red-500 hover:text-red-700" data-project-id="<?php echo $project['id']; ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
            
            <div class="w-full md:w-2/3">
                <div class="bg-white shadow-md rounded-lg p-6">
                    <div id="project-details"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="scheduleModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        Schedule Task
                    </h3>
                    <div class="mt-2">
                        <form id="scheduleForm">
                            <input type="hidden" id="schedulePart" name="part_id">
                            <input type="hidden" id="scheduleProject" name="project_id">
                            <input type="hidden" name="action" value="schedule_task">
                            <div class="mb-4">
                                <label for="scheduleDate" class="block text-sm font-medium text-gray-700">Select Date</label>
                                <input type="date" id="scheduleDate" name="specific_date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                            </div>
                            </form>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm" id="scheduleSubmit">
                        Schedule
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" id="scheduleCancel">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            initializeEventHandlers();

            $(document).on('click', '.project-link', function(e) {
                e.preventDefault();
                var projectId = $(this).data('project-id');
                loadProjectDetails(projectId);
            });

            $('form[name="new-project-form"]').submit(function(e) {
                e.preventDefault();
                var form = $(this);
                $.post('', form.serialize(), function(response) {
                    location.reload();
                });
            });

            $('.delete-project').click(function(e) {
                e.preventDefault();
                var projectId = $(this).data('project-id');
                if (confirm('Are you sure you want to delete this project and all its parts?')) {
                    window.location.href = '?delete_project=' + projectId;
                }
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
            $('.toggle-children').off('click').on('click', function() {
                $(this).toggleClass('fa-chevron-right fa-chevron-down');
                $(this).closest('li').children('ul').toggle();
            });

            $('.add-subpart').off('click').on('click', function() {
                $(this).closest('li').find('> .add-part-form').toggle();
            });

            $('.add-part-form, .add-top-level-part-form').off('submit').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                $.post('', form.serialize(), function(response) {
                    loadProjectDetails(form.find('[name="project_id"]').val());
                });
            });

            $('.delete-part').off('click').on('click', function(e) {
                e.preventDefault();
                var partId = $(this).data('part-id');
                var projectId = $(this).data('project-id');
                if (confirm('Are you sure you want to delete this part and all its sub-parts?')) {
                    $.get('?delete_part=' + partId, function() {
                        loadProjectDetails(projectId);
                    });
                }
            });

            $('.add-to-tasklist').off('click').on('click', function(e) {
                e.preventDefault();
                var partId = $(this).data('part-id');
                var projectId = $(this).data('project-id');
                $.post('', { add_to_tasklist: true, part_id: partId }, function() {
                    alert('Task added to the task list!');
                    loadProjectDetails(projectId);
                });
            });
        }

        document.addEventListener('DOMContentLoaded', (event) => {
        const modal = document.getElementById('scheduleModal');
        const scheduleButtons = document.querySelectorAll('.schedule-task');
        const cancelButton = document.getElementById('scheduleCancel');
        const submitButton = document.getElementById('scheduleSubmit');
        const scheduleForm = document.getElementById('scheduleForm');

        scheduleButtons.forEach(button => {
            button.addEventListener('click', () => {
                const partId = button.dataset.partId;
                const projectId = button.dataset.projectId;
                document.getElementById('schedulePart').value = partId;
                document.getElementById('scheduleProject').value = projectId;
                modal.classList.remove('hidden');
            });
        });

        cancelButton.addEventListener('click', () => {
            modal.classList.add('hidden');
        });

        submitButton.addEventListener('click', () => {
            if (scheduleForm.checkValidity()) {
                const formData = new FormData(scheduleForm);
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Task scheduled successfully!');
                        modal.classList.add('hidden');
                    } else {
                        alert('Error scheduling task: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while scheduling the task.');
                });
                } else {
                    alert('Please fill out all required fields.');
                }
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>