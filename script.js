document.addEventListener('DOMContentLoaded', function() {
    var tagSelect = document.getElementById('tag-select');
    var newTagInput = document.getElementById('new-tag-input');
    var frequencySelect = document.getElementById('frequency-select');
    var specificDateInput = document.getElementById('specific-date-input');
    var dayOfWeekSelect = document.getElementById('day-of-week-select');
    var dayOfMonthSelect = document.getElementById('day-of-month-select');

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
            specificDateInput.style.display = 'none';
            dayOfWeekSelect.style.display = 'none';
            dayOfMonthSelect.style.display = 'none';
            specificDateInput.required = false;
            dayOfWeekSelect.required = false;
            dayOfMonthSelect.required = false;

            switch(this.value) {
                case 'specific_date':
                    specificDateInput.style.display = 'inline-block';
                    specificDateInput.required = true;
                    break;
                case 'weekly':
                    dayOfWeekSelect.style.display = 'inline-block';
                    dayOfWeekSelect.required = true;
                    break;
                case 'monthly':
                    dayOfMonthSelect.style.display = 'inline-block';
                    dayOfMonthSelect.required = true;
                    break;
            }
        });
    }
});