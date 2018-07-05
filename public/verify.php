<?php
require_once("../class_lib/Database.php");
require_once("../class_lib/Crypt.php");
require_once("../class_lib/AuditLog.php");
require_once("../class_lib/user.php");


if(isset($_GET['id']) && !empty($_GET['id'])) {
    $db = New Database();
    $conn = $db->openConnection();

    $userEmail = $_GET['id']; // get email from query string

    try {
        $stmt = $conn->prepare("SELECT email FROM users WHERE email=:email");
        $stmt->execute(array(':email'=>$userEmail));
        $check = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt  = null;

        if($check['email'] == $userEmail) { // If emails match, set verified field to 'user_ver'

            // checks if user email is Ulster University registered, if so encrypt string for database insertion
            $uuCheck = null;
            $user = New user();
            $isUUStudent = $user->uuEmailCheck(Crypt::decryptWithKey($userEmail, "packmyboxwithfivedozenliquorjugs"));
            if(is_array($isUUStudent)) {
                if($isUUStudent['result'] == true) {
                    $uuCheck = Crypt::encryptWithKey($isUUStudent['userEmail'], "packmyboxwithfivedozenliquorjugs");
                }
            }
            $user = null;

            $stmt = $conn->prepare("UPDATE users SET verified='user_ver', uuCheck=:uuCheck WHERE email=:email");
            $stmt->execute(array(':email'=>$userEmail, ':uuCheck'=>$uuCheck));
            $stmt = null;
            $body = "You're all good to go. Your QuantumOne account has been verified.<br /><br />
                     Click <a href=\"login.php\" style=\"color:white; font-weight:bold\"><u>here</u></a> to login.";
            AuditLog::add("$userEmail account verified.");
        } else {
            $body = "Looks like something went wrong with your account verification. Please contact administrator if problem persists.";
        }
    } catch(PDOException $err) {
        AuditLog::add("Account verification failed for $userEmail - ".$err->getMessage());
        $body = "Looks like something went wrong with your account verification. Please contact administrator if problem persists.";
    }
}

$db = null;
$conn = null;
$activeNav = "verification";
require "_shared/header.php"; ?>

<style>
    body {
        background-image: url(_assets/img/simple_blue.png);
        -webkit-background-size: cover;
        -moz-background-size: cover;
        -o-background-size: cover;
        background-size: cover;
    }
    .title {
        width:100%;
        padding:15px 10px 15px 10px;
        font-size:22px;
        text-align:center;
        /*color: #e3e3e3;*/
    }
    .login-div > .regForm {
        margin-top:13px;
        height:280px;
        width:100%;
        padding:10px 35px 10px 35px;
        /*color: #dfdfdf;*/
        text-align:center;
    }
    .divButtons {
        width:100%;
        position: absolute;
        bottom:0;
        height:40px;
    }
    .divButtons input {
        width:100%;
        height:100%;
        border:none;
    }
</style>

<div class="login-div" style="height:275px !important; margin-top:14% !important; color:#dadada !important;">
    <div class="title">Account Verified</div>
    <div class="logo-divider-hz"></div>

    <div class="regForm">
        <?=$body?>
    </div>

    <div class="divButtons">
        <a href="login.php"><input type="button" class="btn btn-primary" value="HOME" /></a>
    </div>
</div>


</body>
</html>
