<?php
include '../public_html/db/connection.php';

// Function to send email
function sendEmail($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Daily Task <task_alert@subhra-maiti.in>' . "\r\n";

    return mail($to, $subject, $message, $headers);
}

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch uncompleted tasks with the "home" tag
$sql = "SELECT tasks.description, tasks.date_added 
        FROM tasks 
        JOIN tags ON tasks.tag_id = tags.id 
        WHERE tags.name = 'Home' AND tasks.completed = FALSE 
        ORDER BY tasks.date_added DESC";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $message = "<h2>Pending Tasks</h2><ul>";
    
    while($row = $result->fetch_assoc()) {
        $message .= "<li>" . htmlspecialchars($row["description"]) . " (Added on: " . $row["date_added"] . ")</li>";
    }
    
    $message .= "</ul>";

    $to = "subhramaiti0@gmail.com"; // Replace with the recipient's email address
    $subject = "Pending Task";

    if (sendEmail($to, $subject, $message)) {
        echo "Email sent successfully.";
    } else {
        echo "Email sending failed.";
    }
} else {
    echo "No uncompleted home tasks found.";
}

$conn->close();
?>