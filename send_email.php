<?php
$to = "subhramaiti0@gmail.com";  // The recipient's email address
$subject = "Test Email";  // Subject of the email
$message = "This is a test email sent from PHP.";  // Body of the email
$headers = "From: sender@example.com" . "\r\n" . // Sender's email address
           "Reply-To: sender@example.com" . "\r\n" . // Reply-to email address
           "X-Mailer: PHP/" . phpversion();  // Additional headers

// Send the email
if(mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully!";
} else {
    echo "Failed to send email.";
}
?>