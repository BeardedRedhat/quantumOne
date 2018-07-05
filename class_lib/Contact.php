<?php
require_once("Database.php");
require_once("Crypt.php");
require_once("Session.php");

/**
 *
 */
class Contact
{
    private $userID;
    private $conn;

    public function __construct($userID)
    {
        $db = New Database();
        $this->conn = $db->openConnection();
        $this->userID = $userID;
    }

    private function query($sql) {
        $stmt = $this->conn->prepare($sql);
        return $stmt;
    }


    // contact form submission
    public function add($subject, $message) {
        $audit = "Contact form failed - ";

        // subject validation
        if(!empty($subject)) {
            if(strlen($subject)>50) {
                AuditLog::add("Contact form failed - character length exceeded.");
                return "Subject character length exceeded. Max: 50.";
            }
            if(!ctype_alnum(str_replace(' ','',$subject))) {
                AuditLog::add($audit."non alpha-numeric characters detected.");
                return "Only alpha-numeric characters allowed in subject.";
            }
        } else {
            AuditLog::add("Contact form failed - empty subject.");
            return "Empty Subject field. Please give it a subject before submitting.";
        }

        // message validation
        if(!empty($message)) {
            if(strlen($message)>1000) {
                AuditLog::add("Contact form failed - character length exceeded.");
                return "Message body character length exceeded. Max: 1000.";
            }
        } else {
            AuditLog::add("Contact form failed - empty message.");
            return "Empty message body. Please enter a message before submitting.";
        }

        try {
            $stmt = $this->query("INSERT INTO contact(userID,`date`, subject, message) VALUES (:userID, NOW(), :subject, :message)");
            $stmt->execute(array(':userID'=>$this->userID, ':subject'=>$subject, ':message'=>$message));
            $stmt = null;
            return true;
        } catch(PDOException $err) {
            AuditLog::add("Contact form failed - ".$err->getMessage());
            return "Looks like an internal server problem. If problem persists, contact administrator..";
        }
    }
}