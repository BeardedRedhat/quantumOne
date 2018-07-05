<?php
require_once('../../class_lib/Account.php');
require_once('../../class_lib/Admin.php');
require_once('../../class_lib/AuditLog.php');
require_once('../../class_lib/Calculate.php');
require_once('../../class_lib/Contact.php');
require_once('../../class_lib/Crypt.php');
require_once('../../class_lib/Database.php');
require_once('../../class_lib/Form.php');
require_once('../../class_lib/Forecast.php');
require_once('../../class_lib/Session.php');
require_once('../../class_lib/Transactions.php');
require_once('../../class_lib/Text.php');
require_once('../../class_lib/user.php');
require_once('../_assets/globalFunctions.php');

Session::check();
$db = New Database();
$conn = $db->openConnection();
setlocale(LC_MONETARY, $_SESSION['locale']);
$userID = $_SESSION['userID'];

$contact = New Contact($userID);

// Form token
if($_SERVER['REQUEST_METHOD'] == "GET")
    $token = Form::generateFormToken('form-contact-message');

// contact form ajax
if(isset($_POST['action'])) {
    switch($_POST['action']) {
        case "submitForm":
            if(Form::verifyFormToken('form-contact-message')) {
                $result = $contact->add(
                    strip_tags(trim($_POST['subject'])),
                    strip_tags(trim($_POST['message']))
                );
                if($result === true) {
                    $message = Form::success_alert("Your message has been sent.");
                } else {
                    $message = Form::error_alert($result);
                }
            } else {
                AuditLog::hackAttempt("Contact page.");
                Session::end();
                die("Hack attempt detected.");
            }
            break;
    }
    $response = array("Result"=>"Ok", "message"=>$message);
    echo json_encode($response);
    die();
}

$contact = null;
$db = null;
$conn = null;
$activeNav = "contact";
require "../_shared/header.php"; ?>

<style>

    hr {
        border:none;
        height:2px;
        background-color: #666666;
    }

    .title {
        display:block;
        font-size:17px;
        margin-bottom:25px;
        text-align:center;
    }

    input, textarea { margin-bottom:25px; }

</style>

<?php require "../navigation/navbar.php"; ?>

<div class="container-fluid">
    <div class="row-fluid">
        <div class="col-xs-12 page-title">
            <span>Contact Us | <span>If you have any queries or issues, please don't hesitate to leave a message in the form below, or use the
                alternative contact details.</span></span>
            <br /><br />
            <hr />
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row-fluid">
        <div class="col-lg-8 col-md-8 col-sm-10 col-xs-12 col-lg-offset-2 col-md-offset-2 col-sm-offset-1">
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12" align="center" style="margin-bottom:35px">
                <span class="title">Contact Form</span>
                <form method="post" id="frmContact">
                    <input type="hidden" id="token" name="token" value="<?=$token?>" />
                    <div class="col-xs-12">
                        <input type="text" class="form-control" name="txtSubject" id="txtSubject" placeholder="Subject" title="Subject" />
                    </div>
                    <div class="col-xs-12">
                        <textarea class="form-control" rows="5" name="txtMessage" id="txtMessage" title="Message" placeholder="Message"></textarea>
                    </div>
                    <div class="col-xs-12">
                        <input type="button" class="btn btn-primary" name="btnSubmit" id="btnSubmit" value="Submit" />
                    </div>
                    <div class="col-xs-12" style="margin-top:25px;" id="response">

                    </div>
                </form>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <div class="title">Alternatively...</div>
                <div class="col-xs-12">
                    If you have any queries or issues you need to discuss, feel free to contact me via:<br /><br />
                    <table>
                        <tr>
                            <th style="width:30px;"></th>
                            <th></th>
                        </tr>
                        <tr>
                            <td><span class="fa fa-envelope" aria-hidden="true"></span> E:</td>
                            <td>mcfarland-a4@ulster.ac.uk</td>
                        </tr>
                        <tr>
                            <td><span class="fa fa-phone-square" aria-hidden="true"></span> T:</td>
                            <td>(028) 9446 0000</td>
                        </tr>
                        <tr>
                            <td><span class="fa fa-phone" aria-hidden="true"></span> M:</td>
                            <td>07812 345678</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div><!-- container -->

<!--- js --->
<script src="../_assets/js/manage/contact.js" type="text/javascript"></script>

</body>
</html>
