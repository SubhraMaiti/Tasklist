<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
        // Include the necessary HTML structure and styling
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($project['name']) . ' - Project Details</title>
            <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        </head>
        <body class="bg-gray-100">
            <div class="container mx-auto px-4 py-8">
                <h1 class="text-3xl font-bold mb-8">' . htmlspecialchars($project['name']) . '</h1>
                <div id="project-details"></div>
            </div>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
                $(document).ready(function() {
                    loadProjectDetails(' . $project_id . ');
                });

                function loadProjectDetails(projectId) {
                    $.get("get_project_details.php", { project_id: projectId }, function(response) {
                        $("#project-details").html(response);
                        initializeEventHandlers();
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        console.error("Error loading project details:", textStatus, errorThrown);
                    });
                }

                // Include other necessary JavaScript functions here
            </script>
        </body>
        </html>';
    } else {
        echo '<p class="text-red-500">Project not found.</p>';
    }
} else {
    echo '<p class="text-gray-500">No project selected.</p>';
}

$conn->close();
?>