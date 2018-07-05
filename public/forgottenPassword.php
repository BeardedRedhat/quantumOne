<?php
require_once("../class_lib/Database.php");
require_once("../class_lib/Crypt.php");
require_once("../class_lib/AuditLog.php");
require_once("../class_lib/Form.php");
require_once("../class_lib/Email.php");

$db = New Database();
$conn = $db->openConnection();

if($_SERVER['REQUEST_METHOD'] == "POST") {
    $email = strip_tags(trim($_POST['txtResetEmail']));

    if(!empty($email)) {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            AuditLog::add("");
            $message = Form::error_msg("Please enter a valid email address.");
        } else {
            try {
                $stmt = $conn->prepare("SELECT userID,firstName FROM users WHERE email=:email");
                $stmt->bindColumn('userID',$userID);
                $stmt->bindColumn('firstName',$firstName);
                $stmt->execute(array(':email'=>Crypt::encryptWithKey($email, "packmyboxwithfivedozenliquorjugs")));
                $stmt->fetch();
                $stmt = null;

                // If the email exists in users table
                if(isset($userID) && !empty($userID)) {
                    $stmt = $conn->prepare("INSERT INTO passwordResetRequests(userID, `date`) VALUES(:userID, NOW())");
                    $stmt->execute(array(':userID'=>$userID));
                    $resetID = $conn->lastInsertId();
                    $stmt = null;

                    // If query was successful
                    if(isset($resetID)) {
                        $encResetID = Crypt::encryptWithKey($resetID, "packmyboxwithfivedozenliquorjugs");
                        $encUserID  = Crypt::encryptWithKey($userID, "packmyboxwithfivedozenliquorjugs");
                        $encReset   = Crypt::encryptWithKey("forgotPassword", "packmyboxwithfivedozenliquorjugs"); // used for reset page to disable old password input

                        $mailMessage = Email::messageTpl("Hi ".$firstName.", you requested to reset your password. To do so, please click the link below. This link is active for today only.
                        <br /><br /><div align='center'><a href=\"http://quantumone:8888/manage/resetPassword.php?id=".$encUserID."&rid=".$encResetID."&reset=".$encReset."\">Click Here!</a></div>
                        <br /><br />If you did not request to change your password, contact us immediately at mcfarland-a4@ulster.ac.uk.");

                        // Send email with link to change password
                        Email::send(
                            $email,
                            "Reset Password",
                            $mailMessage
                        );

                        $message = Form::success_msg("Reset link has been sent.");
                    }
                }
            } catch(PDOException $err) {
                AuditLog::add("Reset password failed - ".$err->getMessage());
                $message = Form::error_msg("Looks like a server issue. If problem persists, contact administrator.");
            }
        }
    } else {
        $message = Form::error_msg("Email field is empty.");
    }
}

$db = null;
$conn = null;
$activeNav = "reset Password";
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

    .inpt {
        width:100%;
        margin-top:15px;
        margin-bottom:15px;
        color:white;
        background-color: #181f25;
        border:1px solid #0e151d;
        border-radius:0;
    }
    input:focus {
        outline:none !important;
        box-shadow:none !important;
        -webkit-box-shadow: none !important;
        -moz-box-shadow: none !important;
    }
</style>

<div class="login-div" style="height:330px !important; margin-top:13% !important; color:#dadada !important;">
    <div class="title">Reset Password</div>
    <div class="logo-divider-hz"></div>

    <div class="regForm" align="center">
        <form method="post">
            <span>Please enter the email address registered to your account:</span>
            <input type="text" class="form-control inpt" name="txtResetEmail" id="txtResetEmail" placeholder="Email Address" />
            <input type="submit" class="btn btn-primary" name="btnSubmit" id="btnSubmit" value="Submit" />
            <span style="display:block;margin-top:1em;">If the email is registered with us, a reset link will be sent. The link will be valid for <u>today</u> only.</span>
        </form>

        <div style="padding-top:10px;"><?=isset($message) ? $message : ''?></div>
    </div>

    <div class="divButtons">
        <a href="login.php"><input type="button" class="btn btn-primary" value="LOGIN" /></a>
    </div>
</div>


</body>
</html>
