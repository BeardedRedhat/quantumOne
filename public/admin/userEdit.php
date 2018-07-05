<?php
require_once('../../class_lib/Admin.php');
require_once('../../class_lib/AuditLog.php');
require_once('../../class_lib/Crypt.php');
require_once('../../class_lib/Database.php');
require_once('../../class_lib/Form.php');
require_once('../../class_lib/Session.php');
require_once('../../class_lib/Text.php');
require_once('../../class_lib/user.php');
require_once('../_assets/globalFunctions.php');

Session::check();
Session::adminCheck();
setlocale(LC_MONETARY, $_SESSION['locale']);
$db = New Database();
$conn = $db->openConnection();

// form token
if($_SERVER['REQUEST_METHOD'] == "GET") {
    $formToken = Form::generateFormToken("form-admin-save-user");
}

$userID = Crypt::decrypt($_GET['id']); // Get the user ID from query string

$user = New user();
$currencyDdl = $user->getCurrencyDdl($_SESSION['currencyID']); // currency dropdown list

$admin = New Admin();
$details = $admin->userInfo($userID); // Get user data
$admin = null;

if(is_array($details)) {
    $details['email'] = Crypt::decryptWithKey($details['email'], "packmyboxwithfivedozenliquorjugs");

    // access level check
    if(!empty($details['adminCheck'])) {
        $accessLvl = $details['adminCheck'] == "YWUyc3VFK0t5QjRhNXVQOUZBdWdSUT09" ? "Administrator" : "User";
    } else {
        $accessLvl = "User";
    }

    // account type check
    if(!empty($details['uuCheck'])) {
        $d = Text::splitEmail($details['email']);

        if($d[0] == Crypt::decryptWithKey($details['uuCheck'], "packmyboxwithfivedozenliquorjugs")) {
            $isStudent = "Student";
        } else {
            $isStudent = "Standard";
        }
    } else {
        $isStudent = "Standard";
    }

    // verified check
    if($details['verified'] == "yes")
        $verCheck = "<span class=\"fa fa-check\" aria-hidden=\"true\" style=\"color:green\"></span> Verified";
    else
        $verCheck = "<span class=\"fa fa-times\" aria-hidden=\"true\" style=\"color:red\"></span> Not Verified";
} else {
    $response = Form::error_alert($details);
}


// update user ajax
if(isset($_POST['action'])) {
    switch($_POST['action']) {
        case "update":
            $user = New user();
            $token = $_POST['token'];

            if(Form::verifyFormToken('form-admin-save-user')) {
                $result = $user->update(
                    $userID,
                    Crypt::decrypt($_POST['currency']),
                    strip_tags(trim($_POST['firstName'])),
                    strip_tags(trim($_POST['lastName'])),
                    strip_tags(trim($_POST['email'])),
                    strip_tags(trim($_POST['notes'])));
                if($result === true) {
                    $response = Form::success_alert("User updated.");
                } else {
                    $response = Form::error_alert($result);
                }
            } else {
                AuditLog::hackAttempt("Admin user edit");
                Session::end();
                die("Hack attempt detected");
            }
            break;
    }
    $jsonResponse = array('Result'=>'Ok', 'message'=>$response);
    echo json_encode($jsonResponse);
    die();
}


$user = null;
$db = null;
$conn = null;
$activeNav = "admin";
require "../_shared/header.php";
require "../navigation/sideNav.php"; ?>

<style>
    .clearfix.pad { margin: 20px 0 20px 0; }
    .no-pad { padding:0 !important; }

    .col-lg-4 {
        margin-bottom:1em;
    }
</style>

<div class="main">
    <?php require "../navigation/navbar.php"; ?>

    <div class="container-fluid">
        <form method="post" name="frmUpdateUser" enctype="multipart/form-data">
            <input type="hidden" value="<?=$formToken?>" id="token" name="token" />

            <div class="row-fluid">
                <span style="font-size:13px;padding-left:13px"><a href="index.php?location=users"><span class="fa fa-chevron-left" aria-hidden="true"></span> Back</a></span>
            </div>

            <div class="clearfix"></div>

            <div class="row-fluid">
                <div class="col-xs-12">
                    <span style="font-size:18px"><?=$details['firstName']." ".$details['lastName']?></span> &nbsp;&nbsp;<?=$verCheck?>
                    <hr style="margin: 0 10px 0 5px; background-color:#3b4a99; height:2px" />
                </div>
            </div>

            <div class="clearfix"></div>

            <div class="row-fluid">
                <div class="col-lg-6 col-md-8 col-sm-10 col-xs-12 col-lg-offset-3 col-md-offset-2 col-sm-offset-1" style="padding-top:20px">

                    <div class="row-fluid" id="response" style="color:red; text-wrap:normal">
                        <?= isset($response) ? $response : '' ?>
                    </div>

                    <div class="row-fluid" align="center">
                        <div class="col-lg-4 no-pad">
                            <b>Access</b> <br /> <?=$accessLvl?>
                            <br /><br />
                            <b>Account Type</b> <br /><?=$isStudent?>
                        </div>
                        <div class="col-lg-4 no-pad">
                            <b>Currency</b><br /><?=$details['curCode'] . " - " . $details['curType']?>
                            <br /><br />
                            <select class="form-control" id="selCurrency" name="selCurrency" title="Currency">
                                <?=$currencyDdl?>
                            </select>
                        </div>
                        <div class="col-lg-4 no-pad">
                            <b>Registered</b> <br /><?=$details['registerTime']?>
                            <br /><br />
                            <b>Last login</b> <br /><?=$details['lastSignIn']?>
                        </div>
                    </div>

                    <div class="clearfix" style="margin-bottom:35px"></div>

                    <div class="row-fluid">
                        Details
                        <hr />

                        <div class="row-fluid">
                            <div class="col-sm-6 col-xs-12" style="padding-left:0;">
                                <input type="text" name="txtFirstName" id="txtFirstName" class="form-control" title="First Name" value="<?=txtSafe($details['firstName'])?>" />
                            </div>
                            <div class="col-sm-6 col-xs-12" style="padding-right:0">
                                <input type="text" name="txtLastName" id="txtLastName" class="form-control" title="Last Name" value="<?=txtSafe($details['lastName'])?>" />
                            </div>
                        </div>

                        <div class="clearfix pad"></div>

                        <div class="row-fluid">
                            <input type="text" name="txtEmail" id="txtEmail" class="form-control" title="Email" value="<?=txtSafe($details['email'])?>" />
                        </div>

                        <div class="clearfix pad"></div>

                        <div class="row-fluid">
                            <textarea class="form-control" name="txtNotes" id="txtNotes" rows="4" title="User Notes" placeholder="Notes"><?=txtAreaSafe($details['notes'])?></textarea>
                        </div>

                        <div class="clearfix pad"></div>

                        <div class="row-fluid" align="center">
                            <input type="button" class="btn btn-primary" name="btnSubmit" id="btnSubmit" value="Save Changes" />
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="clearfix"></div>

        <div class="row-fluid" align="center" style="margin-top:45px">
            <div class="col-xs-12">
                <form method="post" name="frmDeleteUser" enctype="multipart/form-data">
                    <input type="button" class="btn btn-danger" name="btnDeleteUser" id="btnDeleteUser" value="Delete User" />
                </form>
            </div>
        </div>
    </div>

</div><!-- main -->

<!--- js --->
<script src="../_assets/js/admin/userEdit.js" type="text/javascript"></script>

</body>
</html>
