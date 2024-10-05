<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        form {
            margin-bottom: 20px;
        }
        input[type="text"], select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .completed {
            text-decoration: line-through;
            color: #888;
        }
        .task-actions button {
            margin-right: 5px;
        }
        #filterControls {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Task Manager</h1>
        
        <form id="taskForm">
            <input type="text" id="taskDescription" placeholder="Enter task description" required>
            <select id="taskTag">
                <option value="">Select a tag</option>
                <!-- Tags will be populated here -->
            </select>
            <button type="submit">Add Task</button>
        </form>

        <div id="filterControls">
            <select id="tagFilter">
                <option value="">All Tags</option>
                <!-- Tags will be populated here -->
            </select>
            <input type="date" id="dateFilter">
            <button id="applyFilters">Apply Filters</button>
            <button id="clearFilters">Clear Filters</button>
        </div>

        <table id="taskList">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Date Added</th>
                    <th>Tag</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Tasks will be populated here -->
            </tbody>
        </table>
    </div>
    <script src="config.js"></script>
    <script>
        const API_URL = config.API_URL;

        // Fetch all tasks
        function getTasks(tagFilter = '', dateFilter = '') {
            let url = `${API_URL}/tasks`;
            if (tagFilter || dateFilter) {
                url += '?';
                if (tagFilter) url += `tag_id=${tagFilter}&`;
                if (dateFilter) url += `date_added=${dateFilter}`;
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const taskList = document.getElementById('taskList').getElementsByTagName('tbody')[0];
                    taskList.innerHTML = '';
                    data.forEach(task => {
                        const row = taskList.insertRow();
                        row.innerHTML = `
                            <td class="${task.completed ? 'completed' : ''}">${task.description}</td>
                            <td>${task.date_added}</td>
                            <td>${task.tag_name || 'No Tag'}</td>
                            <td class="task-actions">
                                <button onclick="toggleTaskCompletion(${task.id}, ${!task.completed})">
                                    ${task.completed ? 'Uncomplete' : 'Complete'}
                                </button>
                                <button onclick="deleteTask(${task.id})">Delete</button>
                            </td>
                        `;
                    });
                })
                .catch(error => console.error('Error:', error));
        }

        // Fetch all tags
        function getTags() {
            fetch(`${API_URL}/tags`)
                .then(response => response.json())
                .then(data => {
                    const taskTag = document.getElementById('taskTag');
                    const tagFilter = document.getElementById('tagFilter');
                    data.forEach(tag => {
                        taskTag.innerHTML += `<option value="${tag.id}">${tag.name}</option>`;
                        tagFilter.innerHTML += `<option value="${tag.id}">${tag.name}</option>`;
                    });
                })
                .catch(error => console.error('Error:', error));
        }

        // Create a new task
        function createTask(taskData) {
            fetch(`${API_URL}/tasks`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(taskData),
            })
            .then(response => response.json())
            .then(data => {
                console.log('Success:', data);
                getTasks();
            })
            .catch((error) => {
                console.error('Error:', error);
            });
        }

        // Update a task
        function updateTask(id, taskData) {
            fetch(`${API_URL}/tasks/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(taskData),
            })
            .then(response => response.json())
            .then(data => {
                console.log('Success:', data);
                getTasks();
            })
            .catch((error) => {
                console.error('Error:', error);
            });
        }

        // Delete a task
        function deleteTask(id) {
            if (confirm('Are you sure you want to delete this task?')) {
                fetch(`${API_URL}/tasks/${id}`, {
                    method: 'DELETE',
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Success:', data);
                    getTasks();
                })
                .catch((error) => {
                    console.error('Error:', error);
                });
            }
        }

        // Toggle task completion
        function toggleTaskCompletion(id, completed) {
            updateTask(id, { completed: completed });
        }

        // Event Listeners
        document.getElementById('taskForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const description = document.getElementById('taskDescription').value;
            const tagId = document.getElementById('taskTag').value;
            createTask({ description: description, tag_id: tagId, completed: false });
            this.reset();
        });

        document.getElementById('applyFilters').addEventListener('click', function() {
            const tagFilter = document.getElementById('tagFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            getTasks(tagFilter, dateFilter);
        });

        document.getElementById('clearFilters').addEventListener('click', function() {
            document.getElementById('tagFilter').value = '';
            document.getElementById('dateFilter').value = '';
            getTasks();
        });

        // Initial load
        getTasks();
        getTags();
    </script>
</body>
</html>