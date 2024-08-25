document.addEventListener('DOMContentLoaded', function() {
    var tagSelect = document.getElementById('tag-select');
    var newTagInput = document.getElementById('new-tag-input');

    tagSelect.addEventListener('change', function() {
        if (this.value === 'new') {
            newTagInput.style.display = 'inline-block';
            newTagInput.required = true;
        } else {
            newTagInput.style.display = 'none';
            newTagInput.required = false;
        }
    });
});