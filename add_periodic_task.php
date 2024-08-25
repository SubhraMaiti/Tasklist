<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create periodic_tasks table if not exists
$sql = "CREATE TABLE IF NOT EXISTS periodic_tasks (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(255) NOT NULL,
    tag_id INT(6) UNSIGNED,
    frequency ENUM('monthly', 'weekly', 'specific_date') NOT NULL,
    specific_date DATE,
    last_added DATE,
    FOREIGN KEY (tag_id) REFERENCES tags(id)
)";
$conn->query($sql);

// Get current date
$current_date = date('Y-m-d');

// Fetch periodic tasks
$sql = "SELECT * FROM periodic_tasks";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $should_add = false;

    switch ($row['frequency']) {
        case 'monthly':
            // Check if it's been a month since last added
            $should_add = (strtotime($current_date) - strtotime($row['last_added'])) >= 30 * 24 * 60 * 60;
            break;
        case 'weekly':
            // Check if it's been a week since last added
            $should_add = (strtotime($current_date) - strtotime($row['last_added'])) >= 7 * 24 * 60 * 60;
            break;
        case 'specific_date':
            // Check if it's the specific date and hasn't been added today
            $should_add = $current_date == $row['specific_date'] && $current_date != $row['last_added'];
            break;
    }

    if ($should_add) {
        // Add task to the main tasks table
        $sql = "INSERT INTO tasks (description, date_added, tag_id, completed) VALUES (?, ?, ?, FALSE)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $row['description'], $current_date, $row['tag_id']);
        $stmt->execute();
        $stmt->close();

        // Update last_added date for the periodic task
        $sql = "UPDATE periodic_tasks SET last_added = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $current_date, $row['id']);
        $stmt->execute();
        $stmt->close();
    }
}

$conn->close();

echo "Periodic tasks have been processed.";
?>