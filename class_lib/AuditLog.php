<?php

/**
 * Created by PhpStorm.
 * User: AaronMcf
 * Date: 30/03/2017
 * Time: 16:55
 */
class AuditLog
{
    public static function add($auditItem)
    {
        if(empty($auditItem)) {
            Throw new Exception("Audit log add failed - no argument given.");
        }

        require_once('Database.php');
        require_once('Session.php');

        $userID = Session::keyExists('userID') ? $_SESSION['userID'] : 0;

        $query = "INSERT INTO auditlog(auditDate, auditItem, userID) VALUES (:curDay,:item,:userID)";
        $db = new Database();
        $conn = $db->openConnection();
        $stmt = $conn->prepare($query);
        $now = date('Y-m-d H:i:s');
        $stmt -> bindParam(':curDay', $now);
        $stmt -> bindParam(':item', $auditItem);
        $stmt -> bindParam(':userID', $userID);
        $stmt -> execute();
        $conn = null;
        $db = null;
    }

    public static function hackAttempt($where) {
        $ip = $_SERVER["REMOTE_ADDR"];
        $host = gethostbyaddr($ip);

    }
}