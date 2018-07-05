<?php
require_once('../../class_lib/Account.php');
require_once('../../class_lib/AuditLog.php');
require_once('../../class_lib/Calculate.php');
require_once('../../class_lib/Crypt.php');
require_once('../../class_lib/Database.php');
require_once('../../class_lib/Form.php');
require_once('../../class_lib/Session.php');
require_once('../../class_lib/Transactions.php');
require_once('../../class_lib/user.php');

$audit = "Password reset attempt failed - "; // audit logging
$input = "";

if(isset($_GET['id']) && !empty($_GET['id']) && isset($_GET['rid']) && !empty($_GET['rid'])) {
    $db = new Database();
    $conn = $db->openConnection();

    // decrypt userID and resetID from query string
    $resetID = Crypt::decryptWithKey($_GET['rid'], "packmyboxwithfivedozenliquorjugs");
    $userID  = Crypt::decryptWithKey($_GET['id'], "packmyboxwithfivedozenliquorjugs");

    // If user has forgotten password and wants to reset it, disable old password input
    if(isset($_GET['reset']) && Crypt::decryptWithKey($_GET['reset'], "packmyboxwithfivedozenliquorjugs") === "forgotPassword") {
        $input = "disabled=\"disabled\""; // disables old password input field
    }

    try {
        // select reset request from db
        $stmt = $conn->prepare("SELECT DATE_FORMAT(`date`, '%Y-%m-%d') as requestDate FROM passwordResetRequests WHERE userID=:userID AND resetID=:resetID");
        $stmt->execute(array(':userID'=>$userID, ':resetID'=>$resetID));
        $requestDate = $stmt->fetch()[0];
        $stmt = null;

        // checks if request is over a day old
        if(!$requestDate == date('Y-m-d')) {
            AuditLog::add($audit."request is over a day old.");
            $msg = Form::error_alert("Password reset link has expired");
        }
    } catch(PDOException $err) {
        AuditLog::add($audit.$err->getMessage());
        $msg = Form::error_alert("Looks like a server problem. Please try again later.");
    }

    if($_SERVER['REQUEST_METHOD'] === "POST") {
        if(isset($_POST['btnUpdatePassword'])) {
            $newP  = strip_tags($_POST['txtNewPassword']);
            $confP = strip_tags($_POST['txtConfPassword']);

            $user = New user();
            $user->userID = $userID; // set user ID property

            if(empty($input)) {
                $oldP  = strip_tags($_POST['txtOldPassword']);
                $result = $user->changePassword($oldP,$newP,$confP); // If password is being changed from myProfile page
            } else
                $result = $user->changePassword('',$newP,$confP,true); // If password is forgotten and is being reset

            if($result === true) {
                AuditLog::add("Password reset complete - ".$userID);
                $msg = Form::success_alert("Your password has been successfully updated. Click <a href='/login.php' style='text-decoration:underline'>here</a> to login.");
            } else {
                $msg = $result;
            }
        }
    }
} else { // If query string values aren't set or empty
    AuditLog::add($audit."empty query string values");
    $msg = Form::error_alert("Your request is no longer valid.");
}


$db = null;
$conn = null;
$activeNav = "change Password";
require "../_shared/header.php"; ?>

<style>

    hr { border:1px solid #989898; }

    input {
        margin-bottom:1em;
    }

</style>

<div class="container-fluid" style="margin:15px 20px 0 20px;">

    <div class="row-fluid">
        <span style="font-size:22px;"><span class="fa fa-unlock"></span> Change Password</span>
        <hr />
    </div>

    <div class="clearfix" style="margin-bottom:1em"></div>

    <div class="row-fluid">
        <div class="col-xs-12"><?=isset($msg) ? $msg : ''?></div>
    </div>

    <div class="clearfix" style="margin-bottom:1em"></div>

    <div class="row-fluid">
        <div class="col-lg-4 col-md-6 col-sm-8 col-xs-12 col-lg-offset-4 col-md-offset-3 col-sm-offset-2">
            <form method="post">
                <label for="txtOldPassword">Old Password:</label>
                <input type="password" class="form-control" name="txtOldPassword" id="txtOldPassword" title="Old Password" <?=$input?> /><br />

                <label for="txtNewPassword">New Password:</label>
                <input type="password" class="form-control" name="txtNewPassword" id="txtNewPassword" title="New Password" />

                <label for="txtConfPassword">Confirm Password:</label>
                <input type="password" class="form-control" name="txtConfPassword" id="txtConfPassword" title="Confirm Password" />

                <div class="col-xs-12" align="center">
                    <input type="submit" class="btn btn-primary" name="btnUpdatePassword" value="Update" />
                </div>
            </form>
        </div>
    </div>

</div>


</body>
</html>
