document.addEventListener('DOMContentLoaded', function() {
    var tagSelect = document.getElementById('tag-select');
    var newTagInput = document.getElementById('new-tag-input');
    var frequencySelect = document.getElementById('frequency-select');
    var specificDateInput = document.getElementById('specific-date-input');

    if (tagSelect) {
        tagSelect.addEventListener('change', function() {
            if (this.value === 'new') {
                newTagInput.style.display = 'inline-block';
                newTagInput.required = true;
            } else {
                newTagInput.style.display = 'none';
                newTagInput.required = false;
            }
        });
    }

    if (frequencySelect) {
        frequencySelect.addEventListener('change', function() {
            if (this.value === 'specific_date') {
                specificDateInput.style.display = 'inline-block';
                specificDateInput.required = true;
            } else {
                specificDateInput.style.display = 'none';
                specificDateInput.required = false;
            }
        });
    }
});