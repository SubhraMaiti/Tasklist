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
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo htmlspecialchars($project['name']); ?> - Project Details</title>
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
            </style>
        </head>
        <body class="bg-gray-100">
            <div class="container mx-auto px-4 py-8">
                <h1 class="text-3xl font-bold mb-8"><?php echo htmlspecialchars($project['name']); ?></h1>
                <div id="project-details"></div>
            </div>

            <!-- Schedule Modal -->
            <div id="scheduleModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <!-- Modal content (same as in project_management.php) -->
            </div>

            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
                $(document).ready(function() {
                    loadProjectDetails(<?php echo $project_id; ?>);

                    // Event delegation for dynamic content
                    $(document).on('click', '.toggle-children', function() {
                        $(this).toggleClass('fa-chevron-right fa-chevron-down');
                        $(this).closest('li').children('ul').toggle();
                    });

                    $(document).on('click', '.add-subpart', function() {
                        $(this).closest('li').find('> .add-part-form').toggle();
                    });

                    $(document).on('submit', '.add-part-form, .add-top-level-part-form', function(e) {
                        e.preventDefault();
                        var form = $(this);
                        $.post('project_management.php', form.serialize(), function(response) {
                            loadProjectDetails(<?php echo $project_id; ?>);
                        });
                    });

                    $(document).on('click', '.delete-part', function(e) {
                        e.preventDefault();
                        var partId = $(this).data('part-id');
                        var projectId = $(this).data('project-id');
                        if (confirm('Are you sure you want to delete this part and all its sub-parts?')) {
                            $.get('project_management.php?delete_part=' + partId, function() {
                                loadProjectDetails(projectId);
                            });
                        }
                    });

                    $(document).on('click', '.add-to-tasklist', function(e) {
                        e.preventDefault();
                        var partId = $(this).data('part-id');
                        var projectId = $(this).data('project-id');
                        $.post('project_management.php', { add_to_tasklist: true, part_id: partId }, function() {
                            alert('Task added to the task list!');
                            loadProjectDetails(projectId);
                        });
                    });

                    $(document).on('click', '.schedule-task', function() {
                        const partId = $(this).data('part-id');
                        const projectId = $(this).data('project-id');
                        $('#schedulePart').val(partId);
                        $('#scheduleProject').val(projectId);
                        $('#scheduleModal').removeClass('hidden');
                    });

                    $('#scheduleCancel').click(function() {
                        $('#scheduleModal').addClass('hidden');
                    });

                    $('#scheduleSubmit').click(function() {
                        if ($('#scheduleForm')[0].checkValidity()) {
                            $.ajax({
                                url: 'project_management.php',
                                method: 'POST',
                                data: $('#scheduleForm').serialize(),
                                dataType: 'json',
                                success: function(data) {
                                    if (data.success) {
                                        alert('Task scheduled successfully!');
                                        $('#scheduleModal').addClass('hidden');
                                        loadProjectDetails($('#scheduleProject').val());
                                    } else {
                                        alert('Error scheduling task: ' + data.error);
                                    }
                                },
                                error: function(jqXHR, textStatus, errorThrown) {
                                    console.error('Error:', errorThrown);
                                    alert('An error occurred while scheduling the task.');
                                }
                            });
                        } else {
                            alert('Please fill out all required fields.');
                        }
                    });
                });

                function loadProjectDetails(projectId) {
                    $.get('get_project_details.php', { project_id: projectId }, function(response) {
                        $('#project-details').html(response);
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        console.error("Error loading project details:", textStatus, errorThrown);
                    });
                }
            </script>
        </body>
        </html>
        <?php
    } else {
        echo '<p class="text-red-500">Project not found.</p>';
    }
} else {
    echo '<p class="text-gray-500">No project selected.</p>';
}

$conn->close();
?>