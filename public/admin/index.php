<?php
require_once('../../class_lib/Account.php');
require_once('../../class_lib/Admin.php');
require_once('../../class_lib/AuditLog.php');
require_once('../../class_lib/Calculate.php');
require_once('../../class_lib/Crypt.php');
require_once('../../class_lib/Database.php');
require_once('../../class_lib/Form.php');
require_once('../../class_lib/Session.php');
require_once('../../class_lib/Text.php');
require_once('../../class_lib/Table.php');
require_once('../../class_lib/user.php');
require_once('../_assets/globalFunctions.php');

Session::check();
Session::adminCheck();
$db = New Database();
$conn = $db->openConnection();
setlocale(LC_MONETARY, $_SESSION['locale']);
$userID = $_SESSION['userID'];

if($_SERVER['REQUEST_METHOD'] === "GET") {
    $currencyToken = Form::generateFormToken('form-admin-add-cur'); // add new currency form token
    $enqToken      = Form::generateFormToken('form-admin-mark-read'); // mark user enquiry as read token
}

$admin           = New Admin();
$systemStats     = $admin->renderSystemStats(); // Generates system stats with HTML - at very top of page
$currencies      = $admin->showCurrencies();    // Returns all currencies in table format
$systemEnquiries = $admin->showEnquiries();     // Gets any user enquiries submitted via contact page - ordered by date DESC
$usersTable      = $admin->renderUsersTable();  // Generates paginated table with all users - clicking a row will redirect to edit users page


// ajax for adding currency
if(isset($_POST['action'])) {
    switch($_POST['action']) {

        case "addCurrency":
            if(Form::verifyFormToken('form-admin-add-cur')) {
                $currencyToken = $_POST['token'];
                $result = $admin->addNewCurrency(
                    strip_tags(trim($_POST['name'])), strip_tags(trim($_POST['code'])),
                    strip_tags(trim($_POST['label'])), strip_tags(trim($_POST['locale'])));
                if($result === true) {
                    $msg = Form::success_alert("New currency has been added.");
                } else {
                    $msg = Form::error_alert($result);
                }
            } else {
                AuditLog::hackAttempt("Admin add currency");
                Session::end();
                die("Hack attempt detected");
            }
            break;
    }
    $response = array("Result"=>"Ok", "message"=>$msg);
    echo json_encode($response);
    die();
}


// Marking a user enquiry as read
if(isset($_GET['id']) && !empty($_GET['id'])) {
    $enquiryID = $_GET['id'];
    $result    = $admin->markAsRead($enquiryID);

    if($result === true) {
        $message = Form::success_alert("Message read.");
        header('Location: index.php');
    } else {
        $message = Form::error_alert($result);
    }
}


$admin = null;
$db    = null;
$conn  = null;
$activeNav = "admin";
require "../_shared/header.php";
require "../navigation/sideNav.php"; ?>

<style>
    /*** System stats panels ***/
    .stat-panel>.panel-body>.current-stat { border-right:1px solid #dde8ed }
    .stat-panel>.panel-body>div.col-xs-12 { padding:0 !important; }
    .stat-panel>.panel-body>.overall-stat>div.col-xs-12:nth-child(odd) { font-size:11px; }
    .stat-panel>.panel-body>.overall-stat>div.col-xs-12:nth-child(even) {
        font-size:14px;
        color:#263745;
    }


    /*** navbar ***/
    .admin-nav .nav-item {
        height:30px;
        padding-top:6px;
        display:inline-block;
        background-color:inherit;
        border:none;
    }
    .nav-item.active {
        border-bottom:2px solid cadetblue;
        border-left:none !important;
        font-size:13px;
    }
    .nav-item:focus { outline:none; }

    /*** Currency panel ***/
    #divAddNewCurrency { display:block; }
    #divAddNewCurrency input {
        border:none;
        border-radius:0;
        font-size:12px;
        height:30px;
    }
    .currency-body .col-xs-12,
    .currency-body .col-lg-4 { padding:0 }
    input#btnAddCurrency {
        width:100%;
        border-top:1px solid #d6e8c7;
        border-bottom:1px solid #d6e8c7;
        color:#666666;
    }


    /*** User enquiries ***/
    .col-xs-12.div-enq {
        border-bottom:1px solid #faebce;
        padding:10px 15px 10px 15px;
    }
    .div-enq > .title { font-style:italic; display:block; }

    table { margin-bottom:0 !important;}

    .currencies>.table {  }

</style>

<div class="main">
    <?php require "../navigation/navbar.php"; ?>

    <div class="row-fluid">
        <!-- Shows transaction, account and user stats -->
        <?=$systemStats?>
    </div>

    <div class="clearfix"></div>

    <div class="container-fluid">

        <!-- sub navbar -->
        <div class="row-fluid" align="center">
            <div class="admin-nav col-xs-12" style="margin-bottom:3em; margin-top:0;">
                <input type="button" class="nav-item col-xs-6 active" value="Dashboard" id="btnDashboard" />
                <input type="button" class="nav-item col-xs-6" value="Users" id="btnUsers"  />
            </div>
        </div>

        <div class="clearfix"></div>

        <!----------------------------------------------- Dashboard --------------------------------------------------->
        <div class="adminContent" id="adminDashboard">
            <div class="row-fluid">
                <div class="col-xs-12" id="divResponse">
                    <?php if(isset($message)) { echo $message; } ?>
                </div>
            </div>

            <div class="clearfix"></div>

            <div class="row-fluid">
                <div class="col-g-6 col-md-6 col-sm-12 col-xs-12">
                    <div class="panel panel-success">
                        <div class="panel-heading">
                            <span class="fa fa-money" aria-hidden="true"></span> Currencies
                            <div class="pull-right"><a href="#" id="addNew"><span class="fa fa-plus" aria-hidden="true"></span> Add New</a></div>
                        </div>
                        <div class="panel-body currency-body" style="padding:0; max-height:500px; overflow-y:scroll;">
                            <div id="divAddNewCurrency" style="display:none">
                                <form method="post" name="frmAddCurrency" id="frmAddCurrency">
                                    <input type="hidden" name="token" id="token" value="<?=$currencyToken?>" />
                                    <div class="col-xs-12">
                                        <input type="text" class="form-control" name="txtCurrencyName" id="txtCurrencyName" title="New Currency Name" placeholder="Currency Name" />
                                    </div>
                                    <div class="col-lg-4 col-md-4 col-sm-4 col-xs-4">
                                        <input type="text" class="form-control" name="txtCurrencyCode" id="txtCurrencyCode" title="Currency Code" placeholder="Code" />
                                    </div>
                                    <div class="col-lg-4 col-md-4 col-sm-4 col-xs-4">
                                        <input type="text" class="form-control" name="txtCurrencyLabel" id="txtCurrencyLabel" title="Currency Label" placeholder="Label" />
                                    </div>
                                    <div class="col-lg-4 col-md-4 col-sm-4 col-xs-4">
                                        <input type="text" class="form-control" name="txtCurrencyLocale" id="txtCurrencyLocale" title="Currency Locale" placeholder="Locale" />
                                    </div>
                                    <div class="col-xs-12">
                                        <input type="button" class="btn btn-default" id="btnAddCurrency" name="btnAddCurrency" value="Add Currency" />
                                    </div>
                                </form>
                            </div>
                            <div class="currencies">
                                <table class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th style="width:10px"></th>
                                        <th>Name</th>
                                        <th>Code</th>
                                        <th>Label</th>
                                        <th>Locale</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?=$currencies?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-g-6 col-md-6 col-sm-12 col-xs-12">
                    <div class="panel panel-warning">
                        <div class="panel-heading"><span class="fa fa-envelope" aria-hidden="true"></span> User Enquiries</div>
                        <div class="panel-body" style="padding:0; max-height:500px; overflow-y:scroll;">
                            <?=$systemEnquiries?>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!------------------------------------------------- Users ----------------------------------------------------->
        <div class="adminContent" id="adminUsers" style="display:none">
            <div class="row-fluid">
                <div class="col-lg-10 col-md-10 col-sm-10 col-xs-12 col-lg-offset-1 col-md-offset-1 col-sm-offset-1">
                    <div class="panel panel-info">
                        <div class="panel-heading"><span class="fa fa-users" aria-hidden="true"></span> Users</div>
                        <div class="panel-body" style="padding:0 !important;">
                            <?=$usersTable?>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </div><!-- container -->

</div><!-- main -->

<!--- home page js --->
<script src="../_assets/js/admin/index.js" type="text/javascript"></script>

</body>
</html>
