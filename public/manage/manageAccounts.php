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
require_once('../../class_lib/Email.php');
require_once('../_assets/globalFunctions.php');

Session::check();
$db = new Database();
$conn = $db -> openConnection();
$user_currency_label = "£";
setlocale(LC_MONETARY, 'en_GB');
$userID = $_SESSION['userID'];

if($_SERVER['REQUEST_METHOD'] == "GET")  // generate form token
    $token = Form::generateFormToken('form-edit-account');

$acc = New accounts();
$accountsInfo = $acc->info();
$accountTypes = array(1=>"Checking Accounts", 2=>"Savings Accounts", 3=>"Credits"); // Account types used for loops


$stmt = $conn -> prepare("SELECT COUNT(*) AS allAccounts, 
                                 COUNT(CASE WHEN incNet = 1 THEN accountName ELSE NULL END) AS accInNet
                          FROM accounts WHERE userID = $userID");
$stmt -> bindColumn('allAccounts', $allAccounts);
$stmt -> bindColumn('accInNet', $accountsNet);
$stmt -> execute();
$stmt -> fetch();
$stmt = null;


// If the user has clicked the edit button on an account
if(isset($_GET['id']) && !empty($_GET['id'])) {
    $getID  = Crypt::decrypt($_GET['id']);
    $select = "";

    $activityTable = $acc->accountActivity($getID); // Returns transactions made on specific account in paginated table
    $recurActivityTable = $acc->accountActivity($getID,true); // Returns recur only transactions in paginated table

    try {
        $stmt = $conn->prepare("SELECT accountName, accountTypeID, accountBudget, accountBalance, DATE_FORMAT(openingDate, '%d/%m/%Y %H:%i') as openingDate, incNet, COUNT(transactions.transactionAmount) as ttlTrans
                                FROM accounts LEFT JOIN transactions ON transactions.accountID = accounts.accountID 
                                WHERE accounts.accountID=:accountID AND accounts.userID=:userID");
        $stmt->execute(array(':accountID'=>$getID, ':userID'=>$userID));
        $accountData = $stmt->fetch();
        $stmt = null;

        $checked = $accountData['incNet'] == 1 ? "checked" : ""; // Checks the box if account is included in net worth

        // Get account types select options - select the one that applies to the account
        foreach($accountTypes as $id => $type) {
            if($accountData["accountTypeID"] == $id)
                $select .= "<option value=\"".Crypt::encrypt($id)."\" selected='selected'>$type</option>";
            else
                $select .= "<option value=\"".Crypt::encrypt($id)."\">$type</option>";
        }
    } catch(PDOException $err) {
        AuditLog::add("Fetching account details failed on edit account - ".$err->getMessage());
        $error = "Looks like an internal server problem. If problem persists, contact administrator..";
    }

    // ajax switch statement
    if(isset($_POST['action'])) {
        switch($_POST['action']) {

            // Update account
            case "update":
                $token = $_POST['token'];

                if(Form::verifyFormToken('form-edit-account')) {
                    $result = $acc->update(
                        $getID, strip_tags(trim($_POST['name'])), Crypt::decrypt($_POST['type']), strip_tags($_POST['budget']),
                        strip_tags($_POST['balance']), $_POST['incNet']);
                    if($result === true) {
                        $response = Form::success_alert("Account successfully updated.");
                    } else {
                        $response = Form::error_alert($result);
                    }
                } else {
                    AuditLog::hackAttempt("Edit accounts form");
                    Session::end();
                    die("Hack attempt detected");
                }
                break;


            // Delete account
            case "delete":
                if(Form::verifyFormToken('form-edit-account')) {
                    $result = $acc->delete($getID);
                    if($result === true) {
                        $response = Form::success_alert("Account successfully deleted.");
                    } else {
                        $response = Form::error_alert($result);
                    }
                } else {
                    AuditLog::hackAttempt("Delete account form");
                    Session::end();
                    die("Hack attempt detected");
                }
                break;
        }

        $jsonResponse = array('Result'=>'Ok', 'message'=>$response);
        echo json_encode($jsonResponse);
        die();
    }
}



$db = null;
$conn = null;
$activeNav = "Manage Accounts";
require "../_shared/header.php"; ?>

<style>

    hr { border:1px solid #989898; }

    .accHeader {
        font-size:15px;
        display:block;
        width:100%;
        text-align:center;
        padding:10px 0 10px 0;
        border-bottom:2px solid #dddddd;
    }

    .div-account-type {
        background-color:white;
        margin-bottom:30px;
    }
    .div-edit input, .div-edit select {
        font-size:12px !important;
        height:30px;
    }

    .divBuffer {
        margin:5px 0 0 5px;
        box-shadow: 0 0 .5cm rgba(0,0,0,0.5);
        display:none;
        position:absolute;
        text-align:center;
        width:100px;
        height:60px;
        padding-top:9px;
        color: #717171;
        z-index:1;
        background-color:white;
    }

    .div-form-input { margin-bottom:20px; }

    div.mTitle {
        font-size:22px;
        padding: 0 0 5px 0;
    }
    div.mStats {
        padding: 0 0 5px 0;
    }

    table { margin-bottom:0 !important; }

</style>

<?php require_once('../navigation/navbar.php'); ?>

<div class="container-fluid" style="margin:15px 20px 0 20px;">

    <div class="divBuffer">
        <span class="fa fa-spinner fa-pulse fa-2x fa-fw"></span>
        <span class="sr-only">Loading...</span><br />
        <span>Loading...</span>
    </div>

    <?php if(isset($error)) { ?>
        <div class="row-fluid">
            <?=Form::error_alert($error)?>
        </div>
        <div class="clearfix" style="margin-bottom:1em;"></div>
    <?php }?>

    <div class="row-fluid">
        <div class="col-xs-12" id="divResponse"></div>
    </div>
    <div class="clearfix"></div>


    <!------------------------------------------------- Manage -------------------------------------------------------->
    <div class="row-fluid">
        <div class="mTitle col-lg-8 col-md-7 col-sm-6 col-xs-12"><span class="fa fa-list" aria-hidden="true"></span> Manage Accounts</div>
        <div class="col-lg-4 col-md-5 col-sm-6 col-xs-12 mStats" style="text-align:right;">
            Active Accounts: <b><?=$allAccounts?></b><br />
            Accounts inc. Net Worth: <b><?=$accountsNet?></b>
        </div><hr />
    </div>

    <div class="clearfix" style="margin-bottom:1em"></div>

    <div class="row-fluid">
        <?php foreach($accountTypes as $ID => $type): ?>
            <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                <div class="div-account-type col-xs-12">
                    <span class="accHeader"><?=$type?></span>
                    <div class="div-account">
                        <table class="table table-condensed">
                            <thead><tr>
                                <th>Account Name</th>
                                <th>Monthly Budget</th>
                                <th>Balance</th>
                                <th style="width:5%">Net Worth</th>
                                <th>&nbsp;</th>
                            </tr></thead>
                            <tbody>
                            <?php
                            foreach($accountsInfo as $accID => $info):
                                if(array_key_exists('type',$info)) {
                                    if($info['type'] == $ID) { ?>
                                        <tr>
                                            <td><?=$info["name"]?></td>
                                            <td><?=money_format('%n', $info["budget"])?></td>
                                            <td><?=money_format('%n', $info["balance"])?></td>
                                            <td><?=$info['incNet'] == 1 ? "<span class=\"fa fa-check\" aria-hidden=\"true\" style=\"color:green\"></span>" : "<span class=\"fa fa-times\" aria-hidden=\"true\" style=\"color:red\"></span>"?></td>
                                            <td><a href="manageAccounts.php?id=<?=Crypt::encrypt($accID)?>" title="Edit Account" class="fa-edit-account"><span class="fa fa-pencil" aria-hidden="true"></span></a></td>
                                        </tr>
                                    <?php }
                                }
                            endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="clearfix"></div>


    <!-------------------------------------------------- Edit --------------------------------------------------------->
    <?php if(isset($_GET['id']) && !empty($_GET['id'])) { ?>
        <div class="row-fluid">
            <div class="mTitle col-lg-8 col-md-7 col-sm-6 col-xs-12"><span class="fa fa-pencil" aria-hidden="true"></span> Edit <?=$accountData['accountName']?></div>
            <div class="col-lg-4 col-md-5 col-sm-6 col-xs-12 mStats" style="text-align:right;">
                Total transactions: <b><?=$accountData['ttlTrans']?></b><br />
                Account opened: <b><?=$accountData['openingDate']?></b>
            </div><hr />
        </div>

        <div class="clearfix"></div>

        <form method="post" name="frmEditAccount" enctype="multipart/form-data">
            <div class="row-fluid div-edit">
                <input type="hidden" id="token" value="<?=$token?>" />
                <div class="col-xs-12" style="padding-top:20px">
                    <div class="div-form-input col-lg-3 col-md-3 col-sm-4 col-xs-6">
                        <label for="txtAccountName">Account Name</label>
                        <input type="text" class="form-control" name="txtAccountName" id="txtAccountName" title="Account Name" value="<?=txtSafe($accountData['accountName'])?>" />
                    </div>
                    <div class="div-form-input col-lg-3 col-md-3 col-sm-4 col-xs-6">
                        <label for="selAccountType">Account Type</label>
                        <select class="form-control" name="selAccountType" id="selAccountType" title="Account Type">
                            <?=$select?>
                        </select>
                    </div>
                    <div class="div-form-input col-lg-2 col-md-2 col-sm-3 col-xs-6">
                        <label class="control-label" for="txtAccountBudget">Monthly Budget</label>
                        <div class="input-group">
                            <span class="input-group-addon"><?=$user_currency_label?></span>
                            <input type="text" class="form-control" name="txtAccountBudget" id="txtAccountBudget" title="Account Budget" value="<?=txtSafe($accountData['accountBudget'])?>">
                        </div>
                    </div>
                    <div class="div-form-input col-lg-2 col-md-2 col-sm-3 col-xs-6">
                        <label class="control-label" for="txtAccountBalance">Balance</label>
                        <div class="input-group">
                            <span class="input-group-addon"><?=$user_currency_label?></span>
                            <input type="text" class="form-control" name="txtAccountBalance" id="txtAccountBalance" title="Account Balance" value="<?=txtSafe($accountData['accountBalance'])?>">
                        </div>
                    </div>
                    <div class="div-form-input col-lg-2 col-md-2 col-sm-3 col-xs-6" style="padding-top:25px;">
                        <div class="checkbox">
                            <label><input type="checkbox" name="chkIncNet" id="chkIncNet" style="height:0;" <?=$checked?> /> Include in overall Net Worth</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="clearfix"></div>

            <div class="row-fluid">
                <input type="hidden" id="token" name="token" value="<?=$token?>" />
                <div class="col-xs-12" align="center">
                    <input type="button" class="btn btn-default" name="btnSave" id="btnSave" value="Save Changes" />
                </div>
                <div class="col-xs-12" align="center" style="padding-top:25px">
                    <input type="button" class="btn btn-danger" name="btnDelete" id="btnDelete" value="Delete Account" />
                </div>
            </div>
        </form>

        <div class="clearfix"></div>

        <!---------------------------------------------- Activity ----------------------------------------------------->
        <div class="row-fluid" style="margin-bottom:30px">
            <div class="mTitle"><span class="fa fa-line-chart" aria-hidden="true"></span> Activity</div><hr/>
        </div>

        <div class="clearfix"></div>

        <div class="row-fluid">
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <div class="panel panel-default">
                    <div class="panel-heading">All transactions</div>
                    <div class="panel-body" style="padding:0">
                        <?=$activityTable?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <div class="panel panel-default">
                    <div class="panel-heading">Recurring transactions only</div>
                    <div class="panel-body" style="padding:0">
                        <?=$recurActivityTable?>
                    </div>
                </div>
            </div>
        </div>

    <?php } ?>


</div>

<!--- js --->
<script src="../_assets/js/manage/manageAccounts.js" type="text/javascript"></script>

</body>
</html>
