<?php
require_once('../class_lib/Database.php');
require_once('../class_lib/user.php');
require_once('../class_lib/Session.php');
require_once('../class_lib/AuditLog.php');
require_once('../class_lib/Form.php');

Session::start();
Session::set('loginCount',0);
$login = New User();

//if($_SERVER['REQUEST_METHOD'] === "GET") {
//    $token = Form::generateFormToken('form-home-login');
//}

// Login
if($_SERVER['REQUEST_METHOD'] == "POST")
{
    if(isset($_POST['btnLogin'])) {
//        if(Form::verifyFormToken('form-home-login')) {
            $userEmail = strip_tags($_POST['txtLoginEmail']);
            $userPass  = strip_tags($_POST['txtLoginPass']);

            if(empty($userEmail) || empty($userPass)) {
                $error = "Please enter all login details";
                AuditLog::add("Unsuccessful login attempt - empty fields");
            }
            if(!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['loginCount']++;
                $error = "Please enter a valid email address";
                AuditLog::add("Unsuccessful login attempt - invalid email");
            }
            if(strlen($userPass) < 8) {
                $_SESSION['loginCount']++;
                $error = "Password must be at least 8 characters";
                AuditLog::add("Unsuccessful login attempt - password too short");
            }

            if($_SESSION['loginCount'] <= 5) {
                if(!isset($error)) {
                    $result = $login->login($userEmail,$userPass);
                    if($result === true) {
                        $login->redirect('dashboard/index.php');
                        AuditLog::add("Successful login by " . $userEmail);
                    } else {
                        $error = $result;
                    }
                }
            } else {
                AuditLog::add("Login attempt limit reached.");
                $error = "Bye.";
            }
//        } else {
//            AuditLog::hackAttempt("Login page");
//            Session::end();
//            die("Hack attempt detected.");
//        }
    }

}

$activeNav = "Login";
require "_shared/header.php"; ?>


<style>
    body {
        /*background-image: url(_assets/img/city-bkg-pexels.jpeg);*/
        background-image: url(_assets/img/simple_blue.png);
        -webkit-background-size: cover;
        -moz-background-size: cover;
        -o-background-size: cover;
        background-size: cover;
    }

    .logo {
        color:white;
        font-size:30px;
        text-align:center;
        padding-top:15px;
        padding-bottom:10px;
    }
    .loginMain {
        width:100%;
        height:200px;
        padding: 30px 0 30px 0;
        color: #f0f0f0;
        text-align:center;
    }
    .loginMain a { color: #b4b0ad; margin-bottom:2em; }
    .loginMain:first-line { font-size:18px; }
    .loginMain .inpt {
         width:70%;
         margin: 0 15% 10px 15%;
         color:white;
         background-color: #181f25;
         border:1px solid #0e151d;
         border-radius:0;
     }
    .loginMain .divButtons {
        width:50%;
        display:inline-block;
        position: absolute;
        bottom:0;
        height:40px;
    }
    .divButtons input {
        width:100%;
        height:100%;
        border:none;
    }
    input:focus {
        outline:none !important;
        box-shadow:none !important;
        -webkit-box-shadow: none !important;
        -moz-box-shadow: none !important;
    }

    #btnHome:hover { background-color:#8a8784 !important; }

    div.tint {
        width:100%;
        height:100%;
        opacity:.7;
        color:#f0f0f0;
        z-index:1;
        background: #2b3f54; /* For browsers that don't support gradients */
        background: -webkit-linear-gradient(left,black,#202e3e,black);
        background: -o-linear-gradient(left,black,#202e3e,black);
        background: -moz-linear-gradient(left,black,#202e3e,black);
        background: linear-gradient(to right, black, #202e3e,black);
    }

</style>

<div class="tint"></div>

<div class="login-div">

    <div class="logo font-comforta">Quantum<span style="color:#d95557;">One</span></div>
    <div class="logo-divider-hz"></div>
    <div class="loginMain">
        <form method="post">
            <input type="hidden" name="token" id="token" value="<?=$token?>" />
            <span>Login</span><br /><br />

            <input type="text" class="form-control inpt" name="txtLoginEmail" id="txtLoginEmail" title="Email Address/Username" placeholder="Email/Username" />
            <input type="password" class="form-control inpt" name="txtLoginPass" id="txtLoginPass" title="Password" placeholder="Password" /><br />

            <a href="forgottenPassword.php">Forgotten Password?</a><br /><br />
            <a href="register.php">New user? Register here!</a><br /><br />

            <div style="width:100%; text-align:center;" id="response"><?php if(isset($error)) { echo Form::error_msg($error); }?></div>

            <div class="divButtons" style="left:0;">
                <a href="index.php"><input type="button" class="btn btn-default" name="btnHome" id="btnHome" value="HOME" style="background-color:#aba7a4; color:white;" /></a>
            </div>
            <div class="divButtons" style="right:0 !important;">
                <input type="submit" class="btn btn-primary" name="btnLogin" id="btnLogin" value="LOGIN" />
            </div>
        </form>
    </div>

</div>


</body>
</html>
