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
    $html = '<ul>';
    foreach ($parts as $part) {
        $html .= '<li>';
        $html .= '<i class="fas fa-chevron-right toggle-children"></i> ';
        $html .= htmlspecialchars($part['name']);
        $html .= ' <a href="#" class="delete-part" data-part-id="' . $part['id'] . '" data-project-id="' . $project_id . '"><i class="fas fa-trash text-danger"></i></a>';
        $html .= ' <a href="#" class="add-to-tasklist" data-part-id="' . $part['id'] . '" data-project-id="' . $project_id . '"><i class="fas fa-plus text-success"></i></a>';
        $html .= ' <a href="#" class="show-add-form"><i class="fas fa-plus-circle text-primary"></i></a>';
        $html .= '<form class="add-part-form" method="post">';
        $html .= '<input type="hidden" name="project_id" value="' . $project_id . '">';
        $html .= '<input type="hidden" name="parent_id" value="' . $part['id'] . '">';
        $html .= '<input type="hidden" name="level" value="' . ($part['level'] + 1) . '">';
        $html .= '<input type="text" name="new_part" placeholder="New part name" required>';
        $html .= '<button type="submit">Add</button>';
        $html .= '</form>';
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
        echo '<h2>' . htmlspecialchars($project['name']) . '</h2>';
        echo '<div class="project-tree">';
        
        // Fetch and render project parts
        $parts = fetchProjectParts($conn, $project_id);
        echo renderProjectTree($parts, $project_id);
        
        // Add form for top-level parts
        echo '<form class="add-part-form" method="post">';
        echo '<input type="hidden" name="project_id" value="' . $project_id . '">';
        echo '<input type="hidden" name="parent_id" value="">';
        echo '<input type="hidden" name="level" value="0">';
        echo '<input type="text" name="new_part" placeholder="New top-level part" required>';
        echo '<button type="submit">Add</button>';
        echo '</form>';
        
        echo '</div>';
    } else {
        echo '<p>Project not found.</p>';
    }
} else {
    echo '<p>No project selected.</p>';
}

$conn->close();
?>