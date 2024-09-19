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

function renderProjectTree($parts, $project_id, $level = 0) {
    $html = '<ul' . ($level == 0 ? ' class="project-tree"' : '') . '>';
    foreach ($parts as $part) {
        $html .= '<li>';
        $html .= '<i class="fas fa-chevron-' . (empty($part['children']) ? 'right' : 'down') . ' toggle-children"></i> ';
        $html .= htmlspecialchars($part['name']);
        $html .= ' <a href="#" class="delete-part" data-part-id="' . $part['id'] . '" data-project-id="' . $project_id . '"><i class="fas fa-trash"></i></a>';
        $html .= ' <a href="#" class="add-to-tasklist" data-part-id="' . $part['id'] . '" data-project-id="' . $project_id . '"><i class="fas fa-plus"></i> Add to Tasklist</a>';
        $html .= ' <a href="#" class="show-add-form"><i class="fas fa-plus-circle"></i> Add Sub-part</a>';
        $html .= '<form class="add-part-form" method="post">';
        $html .= '<input type="hidden" name="project_id" value="' . $project_id . '">';
        $html .= '<input type="hidden" name="parent_id" value="' . $part['id'] . '">';
        $html .= '<input type="hidden" name="level" value="' . ($level + 1) . '">';
        $html .= '<div class="input-group input-group-sm">';
        $html .= '<input type="text" name="new_part" class="form-control" placeholder="Enter sub-part name" required>';
        $html .= '<button type="submit" class="btn btn-outline-secondary">Add</button>';
        $html .= '</div>';
        $html .= '</form>';
        if (!empty($part['children'])) {
            $html .= renderProjectTree($part['children'], $project_id, $level + 1);
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
        echo '<h2>' . htmlspecialchars($project['name']) . '</h2>';
        
        // Add new part form
        echo '<form class="add-part-form mb-3" method="post">';
        echo '<input type="hidden" name="project_id" value="' . $project_id . '">';
        echo '<input type="hidden" name="parent_id" value="">';
        echo '<input type="hidden" name="level" value="0">';
        echo '<div class="input-group">';
        echo '<input type="text" name="new_part" class="form-control" placeholder="Enter new part name" required>';
        echo '<button type="submit" class="btn btn-outline-secondary">Add Part</button>';
        echo '</div>';
        echo '</form>';

        // Render project tree
        $parts = fetchProjectParts($conn, $project_id);
        echo renderProjectTree($parts, $project_id);
    } else {
        echo '<p class="text-danger">Project not found.</p>';
    }
} else {
    echo '<p class="text-danger">No project ID provided.</p>';
}

$conn->close();
?>