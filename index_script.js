document.addEventListener('DOMContentLoaded', function() {
    var tagSelect = document.getElementById('tag-select');
    var newTagContainer = document.getElementById('new-tag-container');
    var newTagInput = document.getElementById('new-tag-input');

    if (tagSelect) {
        tagSelect.addEventListener('change', function() {
            if (this.value === 'new') {
                newTagContainer.style.display = 'block';
                newTagInput.required = true;
            } else {
                newTagContainer.style.display = 'none';
                newTagInput.required = false;
            }
        });
    }
});