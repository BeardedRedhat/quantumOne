<?php
require_once('../../class_lib/Account.php');
require_once('../../class_lib/AuditLog.php');
require_once('../../class_lib/Calculate.php');
require_once('../../class_lib/Crypt.php');
require_once('../../class_lib/Database.php');
require_once('../../class_lib/Email.php');
require_once('../../class_lib/Form.php');
require_once('../../class_lib/Session.php');
require_once('../../class_lib/Transactions.php');
require_once('../../class_lib/user.php');
require_once('../_assets/globalFunctions.php');

Session::check();
$db = new Database();
$conn = $db->openConnection();
$user_currency_label = $_SESSION['currencyLabel'];
setlocale(LC_MONETARY, $_SESSION['locale']);
$userID = $_SESSION['userID'];

// Form token
if($_SERVER['REQUEST_METHOD'] === "GET")
    $token = Form::generateFormToken('form-profile-update');

$user = New user();
$user->userID = $userID;
$userData = $user->info(); // Get user data
$currencyDdl = $user->getCurrencyDdl($_SESSION['currencyID']); // currencyDdl

// If user data isn't returned
if(is_array($userData)) {
    // decrypt email
    $userData['email'] = Crypt::decryptWithKey($userData['email'], "packmyboxwithfivedozenliquorjugs");

    // Checks whether the account is registered student or standard user
    if(!empty($userData['uuCheck'])) {
        // Get the users local email name, e.g. email = mail2@mail.com, name = mail2
        $d = Text::splitEmail(Crypt::decryptWithKey($userData['email'], "packmyboxwithfivedozenliquorjugs"));

        if($d[0] == Crypt::decryptWithKey($userData['uuCheck'], "packmyboxwithfivedozenliquorjugs"))
            $accountType = "Student";
        else
            $accountType = "Standard";
    } else {
        $accountType = "Standard";
    }
} else {
    $message = Form::error_alert($userData);
}

if($_SERVER['REQUEST_METHOD'] === "POST") {

    // Change password button clicked
    if(isset($_POST['btnChangePassword'])) {
        try {
            // insert reset request into Db
            $stmt = $conn->prepare("INSERT INTO passwordResetRequests(userID, `date`) VALUES(:userID,NOW())");
            $stmt->execute(array(':userID'=>$userID));
            $resetID = $conn->lastInsertId();
            $stmt = null;

            // encrypt the reset ID and user ID
            $encResetID = Crypt::encryptWithKey($resetID, "packmyboxwithfivedozenliquorjugs");
            $encUserID  = Crypt::encryptWithKey($userID, "packmyboxwithfivedozenliquorjugs");

            // Email body with reset link
            $mailMessage = Email::messageTpl("Hi ".$userData['firstName'].", you requested to change your password. To do so, please click the link below. This link is active for today only.
                <br /><br /><div align='center'><a href=\"http://quantumone:8888/manage/resetPassword.php?id=".$encUserID."&rid=".$encResetID."\">Click Here!</a></div>
                <br /><br />If you did not request to change your password, contact us immediately at mcfarland-a4@ulster.ac.uk.");

            // Send email with link to change password
            Email::send(
                $userData['email'],
                "Change Password",
                $mailMessage
            );

            $message = Form::success_alert("An email containing a reset link has been sent. The link will be valid for today only.");

        } catch(PDOException $err) {
            AuditLog::add("Change password failed - ".$err->getMessage());
            $message = Form::error_alert("Looks like a server problem. Please contact administrator.");
        }
    }
}


// update user details ajax
if(isset($_POST['action'])) {
    switch($_POST['action']) {
        case "update":
            if(Form::verifyFormToken('form-profile-update')) {
                $result = $user->update(
                    $userID,
                    Crypt::decrypt($_POST['currency']),
                    strip_tags(trim($_POST['firstName'])),
                    strip_tags(trim($_POST['lastName'])),
                    strip_tags(trim($_POST['email'])),
                    '',false);
                if($result === true) {
                    $msg = Form::success_alert("Details have been updated. <br />If you have changed your currency, please log out and login again to allow changes to take effect.");
                } else {
                    $msg = Form::error_alert($result);
                }
            } else {
                AuditLog::hackAttempt("My profile update details");
                Session::end();
                die("Hack attempt detected.");
            }
            break;
    }

    $response = array('Result'=>'Ok', 'message' => $msg);
    echo json_encode($response);
    die();
}


$db = null;
$conn = null;
$activeNav = "my Profile";
require "../_shared/header.php"; ?>

<style>

    hr { border:1px solid #989898; }

    .no-pad {
        padding:0 !important;
        margin-bottom:15px;
    }

    img {
        width:50%;
        border-radius: 10000px;
        border: 3px solid white;
    }

    div.subtitle { font-size:18px; margin-bottom:30px; }

    input,select { margin-bottom:1em; }
</style>

<?php require_once('../navigation/navbar.php'); ?>

<div class="container-fluid" style="margin:15px 20px 0 20px;">

    <div class="row-fluid">
        <span style="font-size:22px;">My Profile</span><hr />
    </div>

    <div class="clearfix" style="margin-bottom:1em"></div>

    <div class="row-fluid">
        <div class="col-xs-12" id="response"><?=isset($message) ? $message : ''?></div>
    </div>

    <div class="clearfix" style="margin-bottom:1em"></div>

    <div class="row-fluid">
        <!-- Personal details left panel -->
        <div class="col-lg-4 col-md-5 col-sm-6 col-xs-12" align="center" style="margin-bottom:2em;">
            <div class="col-xs-12 no-pad">
                <a href="#"><img src="/_assets/img/profilePic.png" /></a>
            </div>
            <div class="col-xs-12 no-pad" style="font-size:18px">
                <?=$userData['firstName']." ".$userData['lastName']?>
            </div>
            <div class="col-xs-12 no-pad">
                <?php if($userData['verified'] == "user_ver") { ?>
                    <span class="fa fa-check" aria-hidden="true" style="color:green"></span> Account Verified
                <?php } else { ?>
                    <span class="fa fa-times" aria-hidden="true" style="color:red"></span> Not Verified
                <?php }?>
            </div>
            <div class="col-xs-12 no-pad">Account Type:<br /><?=$accountType?></div>
            <div class="col-xs-12 no-pad">Registered On:<br /><?=$userData['registerDate']?></div>
            <form method="post" name="frmChangePassword" enctype="multipart/form-data">
                <div class="col-xs-12 no-pad"><input type="submit" class="btn btn-primary" id="btnChangePassword" name="btnChangePassword" value="Change Password" style="font-size:12px" /></div>
            </form>
        </div>

        <div class="col-lg-8 col-md-7 col-sm-6 col-xs-12">
            <div class="col-xs-12 subtitle">Update Personal Details</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="token" id="token" value="<?=$token?>" />
                <div class="row-fluid">
                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                        <label for="txtFirstName">First Name:</label>
                        <input type="text" class="form-control" name="txtFirstname" id="txtFirstName" title="First Name" value="<?=txtSafe($userData['firstName'])?>" />
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                        <label for="txtLastName">Last Name:</label>
                        <input type="text" class="form-control" name="txtLastName" id="txtLastName" title="Last Name" value="<?=txtSafe($userData['lastName'])?>" />
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                        <label for="txtEmail">Email:</label>
                        <input type="text" class="form-control" name="txtEmail" id="txtEmail" title="Email" value="<?=txtSafe($userData['email'])?>" />
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                        <label for="selCurrency">Currency:</label>
                        <select class="form-control" name="selCurrency" id="selCurrency" title="Currency">
                            <?=$currencyDdl?>
                        </select>
                    </div>
                </div>

                <div class="clearfix"></div>

                <div class="row-fluid">
                    <div class="col-xs-12" align="center">
                        <br /><input type="button" class="btn btn-primary" name="btnUpdate" id="btnUpdate" value="Save Changes" />
                    </div>
                </div>
            </form>
        </div>

    </div><!--row-->

</div>

<!--- js --->
<script src="../_assets/js/manage/myProfile.js" type="text/javascript"></script>

</body>
</html>
