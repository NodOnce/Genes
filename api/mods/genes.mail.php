<?php
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

g::set("void.mail", array("views" => array(), "bits" => array(), "opts" => array(
    "FROM_NAME" => "",
    "FROM_EMAIL" => "",
    "USERNAME" => "",
    "PASSWORD" => "",
    "SMTP_SERVER" => "",
    "SMTP_PORT" => "",
), "tmpls" => array()));

g::def("mods.mail", array(
    "MailSend" => function ($to, $subject, $text, $html) {
        $from_name = g::get("config.mods.mail.opts.FROM_NAME");
        $from_email = g::get("config.mods.mail.opts.FROM_EMAIL");
        $username = g::get("config.mods.mail.opts.USERNAME");
        $password = g::get("config.mods.mail.opts.PASSWORD");
        $smtp_server = g::get("config.mods.mail.opts.SMTP_SERVER");
        $smtp_port = g::get("config.mods.mail.opts.SMTP_PORT");

        // Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->SMTPDebug = false; // SMTP::DEBUG_SERVER; // Enable verbose debug output
            $mail->isSMTP(); // Send using SMTP
            $mail->Host = $smtp_server; // Set the SMTP server to send through
            $mail->SMTPAuth = true; // Enable SMTP authentication
            $mail->Username = $username; // SMTP username
            $mail->Password = $password; // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
            $mail->Port = $smtp_port; // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
            $mail->CharSet = "UTF-8";
            //Recipients
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to); // Add a recipient
            //$mail->addAddress('ellen@example.com'); // Name is optional
            //$mail->addReplyTo('info@example.com', 'Information');
            //$mail->addCC('cc@example.com');
            //$mail->addBCC('bcc@example.com');

            // Attachments
            //$mail->addAttachment('/var/tmp/file.tar.gz'); // Add attachments
            //$mail->addAttachment('/tmp/image.jpg', 'new.jpg'); // Optional name

            // Content
            $mail->isHTML(true); // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;

            $mail->send();
            //echo 'Message has been sent';
            return true;
        } catch (Exception $e) {
            g::run("tools.Say", "Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    },
));
