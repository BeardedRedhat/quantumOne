<?php
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
    <div class="title">Registration Complete</div>
    <div class="logo-divider-hz"></div>

    <div class="regForm">
        Registration was successfully sent.<br /><br /> A <b>confirmation email</b> containing a link will be sent to the registered email address. Click the link to verify the account. <br /><br />
        Click <a href="login.php" style="color:white; font-weight:bold"><u>here</u></a> to login.
    </div>

    <div class="divButtons">
        <a href="login.php"><input type="button" class="btn btn-primary" value="HOME" /></a>
    </div>
</div>


</body>
</html>
