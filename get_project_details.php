<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

function renderProjectTree($parts, $project_id) {
    $html = '<ul class="space-y-4">';
    foreach ($parts as $part) {
        $fontSizeClass = $part['level'] == 0 ? 'text-lg' : ($part['level'] == 1 ? 'text-base' : 'text-sm');
        
        // Add different background colors based on the level
        $bgColorClass = '';
        switch ($part['level']) {
            case 0:
                $bgColorClass = 'bg-blue-100';
                break;
            case 1:
                $bgColorClass = 'bg-green-100';
                break;
            case 2:
                $bgColorClass = 'bg-yellow-100';
                break;
            default:
                $bgColorClass = 'bg-gray-100';
        }
        
        $html .= '<li class="' . $bgColorClass . ' p-4 rounded-lg shadow">';
        $html .= '<div class="flex items-center justify-between">';
        if (!empty($part['children'])) {
            $html .= '<i class="fas fa-chevron-right toggle-children mr-2 text-gray-500"></i> ';
        } else {
            $html .= '<i class="fas fa-circle mr-2 text-gray-500 text-xs"></i> ';
        }
        $html .= '<span class="flex-grow font-medium ' . $fontSizeClass . '">' . htmlspecialchars($part['name']) . '</span>';
        $html .= '<div class="flex space-x-2">';
        $html .= '<button class="bg-blue-500 text-white px-2 py-1 rounded text-sm add-subpart" data-part-id="' . $part['id'] . '" data-project-id="' . $project_id . '" data-level="' . ($part['level'] + 1) . '"><i class="fas fa-plus mr-1"></i>Add Sub-part</button>';
        $html .= '<button class="bg-green-500 text-white px-2 py-1 rounded text-sm add-to-tasklist" data-part-id="' . $part['id'] . '" data-project-id="' . $project_id . '"><i class="fas fa-tasks mr-1"></i>Add to Tasklist</button>';
        $html .= '<button class="bg-purple-500 text-white px-2 py-1 rounded text-sm schedule-task" data-part-id="' . $part['id'] . '" data-project-id="' . $project_id . '"><i class="fas fa-calendar-alt mr-1"></i>Schedule</button>';
        $html .= '<button class="bg-red-500 text-white px-2 py-1 rounded text-sm delete-part" data-part-id="' . $part['id'] . '" data-project-id="' . $project_id . '"><i class="fas fa-trash-alt mr-1"></i>Delete</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<form class="add-part-form mt-4" style="display: none;" method="post">';
        $html .= '<input type="hidden" name="project_id" value="' . $project_id . '">';
        $html .= '<input type="hidden" name="parent_id" value="' . $part['id'] . '">';
        $html .= '<input type="hidden" name="level" value="' . ($part['level'] + 1) . '">';
        $html .= '<div class="flex">';
        $html .= '<input type="text" name="new_part" class="flex-grow mr-2 px-2 py-1 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="New sub-part name" required>';
        $html .= '<button type="submit" class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Add</button>';
        $html .= '</div></form>';
        if (!empty($part['children'])) {
            $html .= renderProjectTree($part['children'], $project_id);
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

if (isset($_GET['project_id'])) {
    $project_id = $_GET['project_id'];
    
    // Fetch project details
    $sql = "SELECT * FROM projects WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();
    $stmt->close();
    
    if ($project) {
        echo '<div class="flex justify-between items-center mb-6">';
        echo '<h2 class="text-2xl font-bold">' . htmlspecialchars($project['name']) . '</h2>';
        echo '</div>';
        echo '<div class="project-tree">';
        
        // Fetch and render project parts
        $parts = fetchProjectParts($conn, $project_id);
        echo renderProjectTree($parts, $project_id);
        
        // Add form for top-level parts
        echo '<form class="add-top-level-part-form mt-6" method="post">';
        echo '<input type="hidden" name="project_id" value="' . $project_id . '">';
        echo '<input type="hidden" name="parent_id" value="">';
        echo '<input type="hidden" name="level" value="0">';
        echo '<div class="flex">';
        echo '<input type="text" name="new_part" class="flex-grow mr-2 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="New top-level part" required>';
        echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Add Top-Level Part</button>';
        echo '</div></form>';
        
        echo '</div>';
    } else {
        echo '<p class="text-red-500">Project not found.</p>';
    }
} else {
    echo '<p class="text-gray-500">No project selected.</p>';
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'schedule_task') {
    $part_id = $_POST['part_id'];
    $project_id = $_POST['project_id'];
    $specific_date = $_POST['specific_date'];

    // Fetch the task description
    $sql = "SELECT name FROM project_parts WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $part_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $stmt->close();

    if ($task) {
        // Insert into periodic_tasks table
        $sql = "INSERT INTO periodic_tasks (description, frequency, specific_date, project_id, part_id) VALUES (?, 'specific_date', ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $task['name'], $specific_date, $project_id, $part_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Task not found']);
    }
    exit;
}

$conn->close();
?>

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

<script>
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