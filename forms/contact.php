
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = strip_tags(trim($_POST["name"]));
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $subject = strip_tags(trim($_POST["subject"]));
    $message = trim($_POST["message"]);

    if (empty($name) OR empty($subject) OR empty($message) OR !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Bitte füllen Sie das Formular korrekt aus.";
        exit;
    }

    $recipient = "info@bruckner-codecraft.com";
    $email_subject = "Neue Kontaktanfrage von $name";
    $email_content = "Name: $name
";
    $email_content .= "Email: $email

";
    $email_content .= "Betreff: $subject

";
    $email_content .= "Nachricht:
$message
";

    $email_headers = "From: $name <$email>";

    if (mail($recipient, $email_subject, $email_content, $email_headers)) {
        http_response_code(200);
        echo "Ihre Nachricht wurde verschickt. Vielen Dank!";
    } else {
        http_response_code(500);
        echo "Es gab ein Problem beim Versenden Ihrer Nachricht. Bitte versuchen Sie es später erneut.";
    }

    // Uncomment the following lines to save the data in a database instead of sending an email
    /*
    $servername = "your_servername";
    $username = "your_username";
    $password = "your_password";
    $dbname = "your_dbname";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "INSERT INTO contact_form (name, email, subject, message) VALUES ('$name', '$email', '$subject', '$message')";

    if ($conn->query($sql) === TRUE) {
        http_response_code(200);
        echo "Ihre Nachricht wurde gespeichert. Vielen Dank!";
    } else {
        http_response_code(500);
        echo "Es gab ein Problem beim Speichern Ihrer Nachricht. Bitte versuchen Sie es später erneut.";
    }

    $conn->close();
    */
} else {
    http_response_code(403);
    echo "Es gab ein Problem mit Ihrer Anfrage. Bitte versuchen Sie es erneut.";
}
?>
