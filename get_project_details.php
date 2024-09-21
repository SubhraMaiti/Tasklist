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
    $html = '<ul class="list-group">';
    foreach ($parts as $part) {
        $html .= '<li class="list-group-item">';
        if (!empty($part['children'])) {
            $html .= '<i class="fas fa-chevron-right toggle-children mr-2"></i> ';
        } else {
            $html .= '<i class="fas fa-circle mr-2"></i> ';
        }
        $html .= htmlspecialchars($part['name']);
        $html .= ' <div class="float-right">';
        $html .= '<button class="btn btn-sm btn-primary mr-1 add-subpart" data-part-id="' . $part['id'] . '" data-project-id="' . $project_id . '" data-level="' . ($part['level'] + 1) . '">Add Sub-part</button>';
        $html .= '<button class="btn btn-sm btn-success mr-1 add-to-tasklist" data-part-id="' . $part['id'] . '" data-project-id="' . $project_id . '">Add to Tasklist</button>';
        $html .= '<button class="btn btn-sm btn-danger delete-part" data-part-id="' . $part['id'] . '" data-project-id="' . $project_id . '">Delete</button>';
        $html .= '</div>';
        $html .= '<form class="add-part-form mt-2" style="display: none;" method="post">';
        $html .= '<input type="hidden" name="project_id" value="' . $project_id . '">';
        $html .= '<input type="hidden" name="parent_id" value="' . $part['id'] . '">';
        $html .= '<input type="hidden" name="level" value="' . ($part['level'] + 1) . '">';
        $html .= '<div class="input-group">';
        $html .= '<input type="text" name="new_part" class="form-control" placeholder="New sub-part name" required>';
        $html .= '<div class="input-group-append">';
        $html .= '<button type="submit" class="btn btn-outline-secondary">Add</button>';
        $html .= '</div></div></form>';
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
        echo '<form class="add-top-level-part-form mt-3" method="post">';
        echo '<input type="hidden" name="project_id" value="' . $project_id . '">';
        echo '<input type="hidden" name="parent_id" value="">';
        echo '<input type="hidden" name="level" value="0">';
        echo '<div class="input-group">';
        echo '<input type="text" name="new_part" class="form-control" placeholder="New top-level part" required>';
        echo '<div class="input-group-append">';
        echo '<button type="submit" class="btn btn-outline-primary">Add Top-Level Part</button>';
        echo '</div></div></form>';
        
        echo '</div>';
    } else {
        echo '<p>Project not found.</p>';
    }
} else {
    echo '<p>No project selected.</p>';
}

$conn->close();
?>