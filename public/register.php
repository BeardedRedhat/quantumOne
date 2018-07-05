<?php
require_once('../class_lib/Database.php');
require_once('../class_lib/user.php');
require_once('../class_lib/Session.php');
require_once('../class_lib/AuditLog.php');
require_once('../class_lib/Form.php');
require_once('../class_lib/Email.php');

function passwordGen($length = 12) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*-=+?";
    $password = substr(str_shuffle($chars),0,$length);
    return $password;
}

$user = new user();
$ddl_currency = $user->getCurrencyDdl();

// Google reCAPTCHA api constants
define("RECAPTCHA_API_SERVER", "http://www.google.com/recaptcha/api");
define("RECAPTCHA_API_SECURE_SERVER", "https://www.google.com/recaptcha/api");
define("RECAPTCHA_VERIFY_SERVER", "www.google.com");


if($_SERVER['REQUEST_METHOD'] == "POST")
{
    if(isset($_POST['btnRegister'])) {

        // reCAPTCHA check commented out for local development as it only works on the live server

        // reCAPTCHA API check
//        if(isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) {
//
//            // API site secret key
//            $secret = '6Lck1VUUAAAAAG527N9ro8_5OmzhehUBObkHUpS6';
//
//            // verify response data
//            $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.
//                '&response='.$_POST['g-recaptcha-response'].'&remoteip='.$_SERVER['REMOTE_ADDR']);
//
//            $responseData = json_decode($verifyResponse);
//
//            // If reCAPTCHA verification is successful
//            if($responseData->success) {
                $user_firstname    = strip_tags(trim($_POST['txtFirstName']));
                $user_lastname     = strip_tags(trim($_POST['txtLastName']));
                $user_email        = strip_tags(trim($_POST['txtEmail']));
                $user_pass         = strip_tags($_POST['txtPass']);
                $user_pass_confirm = strip_tags($_POST['txtConfirmPass']);
                $user_currency     = Crypt::decrypt($_POST['selCurrency']);
                $register_time     = date('Y-m-d H:i:s');

                $result = $user->register(
                    $user_email,
                    $user_pass,
                    $user_pass_confirm,
                    $user_firstname,
                    $user_lastname,
                    $register_time,
                    $user_currency);

                if($result === true) {
                    $verifyID = Crypt::encryptWithKey($user_email, "packmyboxwithfivedozenliquorjugs"); // encrypt user email for link

                    $message = Email::messageTpl("Hi $user_firstname, thanks for signing up to QuantumOne - you're one step away from completing your account registration. Please click the link below to verify your email address:<br /><br />
                        <div style='width:100%;' align='center'><a href=\"http://quantumone:8888/verify.php?id=".$verifyID."\">Click Here!</a></div>
                        <br /><br />If you did not register for a QuantumOne account, please contact us.");

                    // send SMTP email to verify account
                    Email::send(
                        $user_email,
                        "Verify your Account",
                        $message);

                    $user->redirect("registerSent.php");
                    AuditLog::add("Successful account registration " . $user_email);
                    die();

                } else {
                    $error = $result;
                }
//            } else {
//                $error = Form::error_msg("Oops .. are you a robot?");
//            }
//        } else {
//            $error = Form::error_msg("Please check the reCAPTCHA box.");
//        }
    }
}


$user = null;
$db   = null;
$conn = null;
$activeNav = "register";
require "_shared/header.php"; ?>

<style>
    body {
        background-image: url(_assets/img/simple_blue.png);
        -webkit-background-size: cover;
        -moz-background-size: cover;
        -o-background-size: cover;
        background-size: cover;
    }
    .login-div .input {
        color:white;
        background-color: #181f25;
        border:1px solid #0e151d;
        border-radius:0;
        display:inline-block;
        margin-bottom:1em;
    }
    .title {
        width:100%;
        padding:15px 10px 15px 10px;
        font-size:22px;
        text-align:center;
        color: #e3e3e3;
    }
    .login-div > .regForm {
        margin-top:15px;
        height:280px;
        width:100%;
        padding:10px 20px 10px 20px;
    }

    #selCurrency {
        background-color:#181f25 !important;
        color:#e3e3e3 !important;
        border-color:black;
        border-radius:0 !important;
    }

    .login-div .divButtons {
        width:100%;
        position: absolute;
        bottom:0;
        height:40px;
    }
    .login-div .span {
        width:100%;
        margin-top: 15px;
        text-align:center;
    }
    .divButtons input {
        width:100%;
        height:100%;
        border:none;
    }
</style>

<div class="login-div" style="height:490px; margin-top:8% !important;">
    <div class="title">Register</div>
    <div class="logo-divider-hz"></div>

    <div class="regForm">
        <form method="post">
            <input type="text" class="form-control input required" name="txtFirstName" id="txtFirstName" title="First Name" placeholder="First Name" style="width:48%" />
            <input type="text" class="form-control input required" name="txtLastName" id="txtLastName" title="Last Name" placeholder="Last Name" style="width:48%; float:right;" />
            <input type="email" class="form-control input required" name="txtEmail" id="txtEmail" title="Email Address" placeholder="Email" />
            <input type="password" class="form-control input required" name="txtPass" id="txtPass" title="Password" placeholder="Password" style="width:48%;" />
            <input type="password" class="form-control input required" name="txtConfirmPass" id="txtConfirmPass" title="Confirm Password" placeholder="Confirm Password" style="width:48%; float:right;" />
            <select class="form-control" name="selCurrency" id="selCurrency" title="Currency">
                <?=$ddl_currency?>
            </select>

            <div class="span"><a href="login.php" style="color:#b4b0ad;">Already have an account? Login here</a></div>

            <div class="span" style="margin-top:10px!important; color:#b4b0ad;">
                Suggested Password: <?=passwordGen();?>
                <a href="#"><span class="fa fa-question-circle" aria-hidden="true" style="color:white;" title="What's this?"></span></a>
            </div>

            <div style="width:100%; margin-top:1em" align="center">
                <div class="g-recaptcha" data-sitekey="6Lck1VUUAAAAAPT6Zo55mpfT5a5PCf3BIk0A3hqx" data-theme="dark"></div>
            </div>


            <?php if(isset($error)) { ?>
                <div style="width:100%; text-align:center; margin-top:.5em;"><?=$error?></div>
            <?php } ?>

            <div class="divButtons" style="right:0;">
                <input type="submit" class="btn btn-primary" name="btnRegister" id="btnRegister" title="Register" value="Register" />
            </div>
        </form>
    </div>
</div>

<script>

</script>

</body>
</html>
