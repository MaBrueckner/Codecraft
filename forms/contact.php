<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = strip_tags(trim($_POST["name"]));
    $name = str_replace(array("\r","\n"), ' ', $name); // Schutz vor Header Injection
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $subject = strip_tags(trim($_POST["subject"]));
    $message = trim($_POST["message"]);

    if (empty($name) || empty($subject) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Bitte füllen Sie das Formular korrekt aus.";
        exit;
    }

    $recipient = "info@bruckner-codecraft.com";
    $email_subject = "Neue Kontaktanfrage von $name";
    $email_content  = "Name: $name\r\n";
    $email_content .= "Email: $email\r\n\r\n";
    $email_content .= "Betreff: $subject\r\n\r\n";
    $email_content .= "Nachricht:\r\n$message\r\n";

    $email_headers  = "From: Kontaktformular <no-reply@bruckner-codecraft.com>\r\n";
    $email_headers .= "Reply-To: $email\r\n";
    $email_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    if (mail($recipient, $email_subject, $email_content, $email_headers)) {
        http_response_code(200);
        echo "Ihre Nachricht wurde verschickt. Vielen Dank!";
    } else {
        http_response_code(500);
        echo "Es gab ein Problem beim Versenden Ihrer Nachricht. Bitte versuchen Sie es später erneut.";
    }
} else {
    http_response_code(403);
    echo "Es gab ein Problem mit Ihrer Anfrage. Bitte versuchen Sie es erneut.";
    exit;
}
?>