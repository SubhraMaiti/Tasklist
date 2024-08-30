<?php
// ... (previous PHP code remains the same)

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
        /* ... (previous styles remain the same) */
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Task Manager V0.7</h1>
        
        <form method="post" action="" class="mb-4">
            <!-- ... (previous form content remains the same) -->
        </form>
        
        <h2 class="mb-3">Task List</h2>
        
        <!-- Add filter options -->
        <div class="mb-3">
            <form id="filter-form" class="row g-3">
                <div class="col-md-4">
                    <select name="filter_tag" id="filter-tag" class="form-select">
                        <option value="">All Tags</option>
                        <?php 
                        $tags_result->data_seek(0); // Reset the tags result pointer
                        while($tag = $tags_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $tag['id']; ?>" <?php echo ($filter_tag == $tag['id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($tag['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="date" name="filter_date" id="filter-date" class="form-control" value="<?php echo $filter_date; ?>">
                </div>
                <div class="col-md-2">
                    <select name="show_completed" id="show-completed" class="form-select">
                        <option value="false" <?php echo ($show_completed === 'false' ? 'selected' : ''); ?>>Pending</option>
                        <option value="true" <?php echo ($show_completed === 'true' ? 'selected' : ''); ?>>All</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>
        
        <div class="table-responsive">
            <!-- ... (table content remains the same) -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sortableHeaders = document.querySelectorAll('.sortable');
            sortableHeaders.forEach(header => {
                header.addEventListener('click', function() {
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
                    
                    // Redirect to the new URL
                    window.location.search = urlParams.toString();
                });
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

            // Handle filter form submission
            const filterForm = document.getElementById('filter-form');
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const urlParams = new URLSearchParams(formData);
                window.location.search = urlParams.toString();
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>