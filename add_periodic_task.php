<?php
//New db path updated
include '/home/a57ewz3cwx9h/public_html/db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

//Set time zone to kolkata
date_default_timezone_set('Asia/Kolkata');

// Get current date
$current_date = date('Y-m-d');
$current_day_of_week = date('N'); // 1 (for Monday) through 7 (for Sunday)
$current_day_of_month = date('j');

// Fetch periodic tasks
$sql = "SELECT * FROM periodic_tasks";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $should_add = false;

    switch ($row['frequency']) {
        case 'daily':
            // Check if it hasn't been added today
            $should_add = empty($row['last_added']) || $row['last_added'] != $current_date;
            break;
        case 'monthly':
            // Check if it's the specified day of the month and it hasn't been added this month
            $should_add = $current_day_of_month == $row['day_of_month'] && 
                          (empty($row['last_added']) || date('Y-m', strtotime($row['last_added'])) != date('Y-m'));
            break;
        case 'weekly':
            // Check if it's the specified day of the week and it hasn't been added this week
            $should_add = $current_day_of_week == $row['day_of_week'] && 
                          (empty($row['last_added']) || strtotime($row['last_added']) < strtotime('last ' . date('l', strtotime("Sunday +{$row['day_of_week']} days"))));
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