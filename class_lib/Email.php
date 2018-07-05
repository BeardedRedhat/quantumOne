<?php
/**
 * SMTP email class using PHPMailer library
 * Code for send function was found in PHPMailer documentation
 **/
class Email
{
    // Email template
    public static function messageTpl($body) {
        return "<div style='font-family: Comfortaa; font-size:18px; display:block; padding-bottom:25px; ' align='center'>Quantum<span style=\"color:#d95557;\">One</span></div>
                <div>  
                    $body
                </div>";
    }

    // Send email function
    public static function send($recipient, $subject, $message) {
        // Require PHPMailer/Autoloader
        require_once('PHPMailer/PHPMailerAutoload.php');
        // Create new PHPMailer instance
        $mail = new PHPMailer(true);
        $mailSent = false;

        try {
            // SMTP Email Settings
            $mail->isSMTP(); // Enable use of SMTP
            $mail->SMTPAuth = true; // Enable SMTP authentication
            $mail->SMTPDebug = 0; // Enable SMTP debugging (0 = off, 1 = client, 2 = client & server)
            $mail->SMTPSecure = 'tls'; // Set encryption system to TLS
            $mail->Host = 'smtp.gmail.com'; // Set hostname of the mail server
            $mail->Port = 587; // Set SMTP port number to 587 for authenticated TLS (RFC4409 SMTP submission)
            $mail->isHTML(true); // Enable HTML use
            $mail->Debugoutput = 'html'; // Enable HTML-friendly debug output
            $mail->Username = 'quantumonebudgeting@gmail.com'; // Set SMTP username
            $mail->Password = 'Qazeye75i2nwa9rd'; // Set SMTP password
            $mail->From = 'quantumonebudgeting@gmail.com'; // Set email address to be sent from
            $mail->FromName = 'QuantumOne [NO REPLY]'; // Set name to be sent from
            $mail->AddAddress($recipient); // Set address to send to
            $mail->Subject = $subject; // Set email subject line (subject)
            $mail->Body = $message; // Set email message cotent (body)
            $mail->WordWrap = "50"; // Set word wrap to 50 characters

            if ($mail->send()) {
                $mailSent = true;
            } else {
                $mailSent = false;
                throw new Exception(str_replace("2018-", "<br/>2018-", $mail->ErrorInfo));
            }

        } catch (phpmailerException $e) { // PHPMailer exception error message
            echo $e->errorMessage();
            throw new Exception($e->errorMessage());
        } catch (Exception $e) { // PDO exception error message
            echo $e->getMessage();
            throw new Exception($e->getMessage());
        }

        return $mailSent;
    }
}