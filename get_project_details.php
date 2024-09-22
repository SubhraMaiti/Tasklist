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
        $html .= '<li class="bg-gray-50 p-4 rounded-lg shadow">';
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

$conn->close();
?>