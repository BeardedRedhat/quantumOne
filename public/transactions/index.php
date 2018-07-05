<?php
require_once('../../class_lib/Account.php');
require_once('../../class_lib/AuditLog.php');
require_once('../../class_lib/Calculate.php');
require_once('../../class_lib/Crypt.php');
require_once('../../class_lib/Database.php');
require_once('../../class_lib/Form.php');
require_once('../../class_lib/Session.php');
require_once('../../class_lib/Transactions.php');
require_once('../../class_lib/Text.php');
require_once('../../class_lib/user.php');
require_once('../_assets/globalFunctions.php');

Session::check();
$db = New Database();
$conn = $db->openConnection();
$user_currency_label = $_SESSION['currencyLabel'];
setlocale(LC_MONETARY, $_SESSION['locale']);
$userID = $_SESSION['userID'];

if($_SERVER['REQUEST_METHOD'] == "GET") {
    // Generating form tokens for hidden inputs
    $transToken = Form::generateFormToken('form-trans-add');
    $catToken   = Form::generateFormToken('form-trans-add-cat');
}

$transaction        = New transactions($conn);
$accounts_ddl       = $transaction->generateAccountsDdl();    // Generate dropdown list options for accounts &
$categories_ddl     = $transaction->generateCategoriesDdl();  // categories ddl
$recentTrans        = $transaction->renderTransRecentActivity(); // Gets the first 5 most recent transactions, returns table, total income/expenses
$recurTable         = $transaction->renderRecurTrans();// Gets recurring transactions for table
$userCategories     = $transaction->showCategories(); // Renders user categories in table


// AJAX calls for adding new transaction/category
if(isset($_POST['action'])) {
    $ddl = "";

    switch($_POST['action']) {

        // Add new transaction
        case 'addTransaction':
            $token = $_POST['token'];  // Get form token from hidden input to be matched with SESSION token

            if(Form::verifyFormToken('form-trans-add')) {   // Verifying form token against SESSION token
                if(!isset($_POST['recur'])) {   // If transaction is non-recurring
                    $result = $transaction->addNew(
                        Crypt::decrypt($_POST['type']),
                        (isset($_POST['account']) ? Crypt::decrypt($_POST['account']) : null),
                        (isset($_POST['category']) ? Crypt::decrypt($_POST['category']) : null),
                        strip_tags($_POST['amount']),
                        strip_tags(trim($_POST['receiptNo'])),
                        strip_tags(trim($_POST['description'])));

                } else { // If recur transaction checkbox is selected
                    $result = $transaction->addNew(
                        Crypt::decrypt($_POST['type']),
                        (isset($_POST['account']) ? Crypt::decrypt($_POST['account']) : null),
                        (isset($_POST['category']) ? Crypt::decrypt($_POST['category']) : null),
                        strip_tags($_POST['amount']),
                        strip_tags(trim($_POST['receiptNo'])),
                        strip_tags(trim($_POST['description'])),
                        $_POST['recur'],
                        $_POST['startDate'],
                        $_POST['endDate'],
                        Crypt::decrypt($_POST['frequency']),
                        $_POST['repeatInd']);
                }
                if($result === true) {
                    $response = Form::success_alert("Transaction successfully added.");
                } else {
                    $response = Form::error_alert($result);
                }
            } else {   // If the form and SESSION tokens don't match
                AuditLog::hackAttempt("Transactions page add transaction form");
                Session::end();
                die("Hack attempt detected");
            }
            break;


        // Add new category
        case 'addCategory':
            $token = $_POST['token'];   // get form token from hidden input

            if(Form::verifyFormToken('form-trans-add-cat')) {
                $result = $transaction->addCategory(strip_tags(trim($_POST['name'])), strip_tags($_POST['budget']));
                if($result !== true) {
                    $response = Form::error_msg($result);
                } else {
                    $response = Form::success_msg("New Category Added: ".txtSafe($_POST['name']));
                    $ddl = $transaction->generateCategoriesDdl();
                }
            } else {
                AuditLog::hackAttempt("Dashboard Add Category Form");
                Session::end();
                die("Hack attempt detected.");
            }
            break;
    }

    $jsonResponse = array('Result'=>'Ok', 'message'=>$response, 'ddl'=>$ddl);
    echo json_encode($jsonResponse);
    die();
}



//Action to delete transaction or category
if(isset($_GET['id']) && !empty($_GET['id']) && isset($_GET['action']) && !empty($_GET['action']))
{
    $ID = Crypt::decrypt($_GET['id']);
    $action = Crypt::decrypt($_GET['action']);

    switch($action)
    {
        // Delete transaction
        case "delete":
            try {
                $stmt = $conn->prepare("DELETE FROM transactions WHERE transactionID = :transactionID AND userID=:userID");
                $stmt->execute(array(':transactionID'=>$ID, ':userID'=>$userID));
                $stmt = null;
                AuditLog::add("Transaction deleted by user");
                header('Location: index.php');
            } catch(PDOException $err) {
                AuditLog::add("Delete transaction failed - ".$err->getMessage());
                $error = Form::error_alert("Something went wrong. Please contact administrator.");
            }
            break;

        // Delete category
        case "deleteCat":
            try {
                $stmt = $conn->prepare("DELETE FROM categories WHERE categoryID=:catID AND userID=:userID");
                $stmt->execute(array(':catID'=>$ID, ':userID'=>$userID));
                $stmt = null;
                AuditLog::add("Category deleted by user");
                header('Location: index.php');
            } catch(PDOException $err) {
                AuditLog::add("Delete category failed - ".$err->getMessage());
                $error = Form::error_alert("Something went wrong. Please contact administrator.");
            }
            break;
    }
}

$db = null;
$conn = null;
$activeNav = "transactions";
require "../_shared/header.php";
require "../navigation/sideNav.php"; ?>

<style>
    .clearfix { margin-bottom: 1em; }
    select { width:100%; }
    input,select,textarea { font-size:12px !important; }
    .panel-body { padding-left:0; padding-right:0; }

    .divTransStat { padding: 1em 1em 1em 1em; }
    .divTransStat span { font-size:14px; float:right; }

    .page-title { margin:1em 0 2em 0; }
    .page-title > span { font-size:22px; }
    .page-title > span > span { font-size:12px; }

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

    .no-pad {
        padding:0 !important;
        margin-bottom:10px;
    }
</style>

<div class="main">
    <?php require "../navigation/navbar.php"; ?>

    <div class="container-fluid font-opensans">

        <div class="col-xs-12 page-title">
            <span>Transactions | <span>Create new transactions & categories, or view your transaction history.</span></span>
        </div>

        <div class="divBuffer">
            <span class="fa fa-spinner fa-pulse fa-2x fa-fw"></span>
            <span class="sr-only">Loading...</span><br />
            <span>Loading...</span>
        </div>

        <div class="col-xs-12"><?php if(isset($error)) { echo $error; } ?></div>

        <!-------------------------------------------- Transactions form ---------------------------------------------->
        <div class="row-fluid">
            <div class="col-lg-8 col-md-8 col-sm-12 col-xs-12" style="margin-bottom:10px">
                <form method="post" name="frmNewTransaction" id="frmNewTransaction" enctype="multipart/form-data">
                    <input type="hidden" name="token" id="transToken" value="<?=$transToken?>" />
                    <div class="panel panel-success">
                        <div class="panel-heading"><span class="fa fa-exchange" aria-hidden="true"></span> Add New Transaction</div>
                        <div class="panel-body">

                            <div class="row-fluid">
                                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                    <div class="col-xs-12 no-pad">
                                        <label for="selTransType">Type &nbsp;</label>
                                        <select class="form-control" name="selTransType" id="selTransType" title="Transaction Type">
                                            <option value="<?=Crypt::encrypt("income")?>">Income</option>
                                            <option value="<?=Crypt::encrypt("expense")?>">Expense</option>
                                        </select>
                                    </div>
                                    <div class="col-xs-12 no-pad">
                                        <label for="selAccounts">Account &nbsp;</label>
                                        <select class="form-control" name="selAccounts" id="selAccounts" title="Account">
                                            <?=$accounts_ddl?>
                                        </select>
                                    </div>
                                    <div class="col-xs-12 no-pad">
                                        <label for="selCategories">Category &nbsp;</label>
                                        <select class="form-control" name="selCategories" id="selCategories" title="Categories">
                                            <?=$categories_ddl?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                    <div class="col-xs-12 no-pad">
                                        <label for="txtTransAmount">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-addon"><?=$user_currency_label?></span>
                                            <input class="form-control required" name="txtTransAmount" id="txtTransAmount" type="text" title="Transaction Amount" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-xs-12 no-pad">
                                        <label for="txtReceipt">Receipt No.</label>
                                        <input class="form-control" name="txtReceipt" id="txtReceipt" title="Receipt Number" type="text">
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                    <div class="col-xs-12 no-pad">
                                        <label for="txtDescription">Description</label>
                                        <div class="divTxtArea">
                                            <textarea class="form-control" data-length="1000" id="txtDescription" name="txtDescription" rows="5" placeholder="Notes" onkeyup="countChar(this)"></textarea>
                                            <span id="count"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="clearfix"></div>

                            <div class="row-fluid">
                                <div class="col-xs-12" style="border-bottom:1px solid #dff0d8; border-top:1px solid #dff0d8; padding:.5em 0 .5em 0; text-align:center; font-size:13px;">Direct Debit Transaction</div>
                            </div>

                            <div class="clearfix"></div>

                            <div class="row-fluid">
                                <div class="col-xs-12">
                                    <div class="col-xs-12 no-pad">
                                        <div class="checkbox">
                                            <label><input type="checkbox" name="chkRecurTrans" id="chkRecurTrans" title="Recur Transaction" style="height:0;" /> Direct Debit transaction</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="clearfix"></div>

                            <div class="row-fluid">
                                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                                    <label for="txtStartDate">Start Date:</label>
                                    <input type="date" class="form-control inptRecur" name="txtStartDate" id="txtStartDate" placeholder="DD/MM/YYYY" disabled/><br />
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                                    <label for="txtEndDate">End Date:</label>
                                    <input type="date" class="form-control inptRecur" name="txtEndDate" id="txtEndDate" placeholder="DD/MM/YYYY" disabled/><br />
                                    <div class="checkbox">
                                        <label><input class="inptRecur" type="checkbox" name="chkRecurIndef" id="chkRecurIndef" title="Recur Transaction" style="height:0;" disabled/> Repeat Indefinitely</label>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                                    <label for="selRecurRepeat">Repeat</label>
                                    <select class="form-control inptRecur" name="selRecurRepeat" id="selRecurRepeat" title="Repeat Every" disabled>
                                        <option value="<?=Crypt::encrypt("365")?>">Daily</option>
                                        <option value="<?=Crypt::encrypt("52")?>">Weekly</option>
                                        <option value="<?=Crypt::encrypt("12")?>">Monthly</option>
                                        <option value="<?=Crypt::encrypt("1")?>">Yearly</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-xs-12" id="transResponse"></div>
                        </div>
                        <div class="panel-footer text-center" style="padding:0 0 0 0">
                            <input type="button" class="btn btn-default" name="btnSubmitTrans" id="btnSubmitTrans" value="Add Transaction" style="width:100%; height:2.5em; border:none;" />
                        </div>
                    </div><!--panel-->
                </form>
            </div><!--- col-lg-8 --->


            <!-------------------------------------------- Category Form ---------------------------------------------->
            <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12" style="margin-bottom:10px">
                <div class="panel panel-success">
                    <div class="panel-heading"><span class="fa fa-plus" aria-hidden="true"></span> Add new Category</div>
                    <form method="post" name="form-trans-add-cat" id="form-trans-add-cat" enctype="multipart/form-data">
                        <div class="panel-body" style="padding:10px 20px 0 20px">
                            <input type="hidden" name="token" id="catToken" value="<?=$catToken?>" />
                            <div class="row-fluid">
                                <div class="col-xs-12 no-pad">
                                    <label for="txtCategoryName">Name</label>
                                    <input class="form-control" name="txtCategoryName" id="txtCategoryName" title="Category Name" type="text" placeholder="E.g. Fuel" />
                                </div>
                                <div class="col-xs-12 no-pad">
                                    <label for="txtCatBudget">Budget</label>
                                    <div class="input-group">
                                        <span class="input-group-addon"><?=$user_currency_label?></span>
                                        <input class="form-control" name="txtCatBudget" id="txtCatBudget" type="text" title="Category Budget" placeholder="0.00" />
                                    </div>
                                </div>
                            </div>
                            <div class="clearfix"></div>
                            <div class="col-xs-12" id="catResponse" style="padding-bottom:15px"></div>
                        </div>
                        <div class="panel-footer text-center" style="padding:0 0 0 0;">
                            <input type="button" class="btn btn-default" name="btnAddCat" id="btnAddCat" value="Add Category" style="width:100%; height:2.5em; border:none; " />
                        </div>
                    </form>
                </div>
            </div>

            <!---------------------------------------------- Categories ----------------------------------------------->
            <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                <div class="panel panel-success">
                    <div class="panel-heading">Categories</div>
                    <div class="panel-body" style="max-height:181px; overflow-y:scroll; padding:0;">
                        <table class="table table-striped" style="margin-bottom:0;">
                            <thead>
                            <tr><th>Category</th><th>Date Created</th></tr>
                            </thead>
                            <tbody>
                            <?=$userCategories?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div><!--row-->

        <div class="clearfix"></div>

        <!----------------------------------------- Most recent transactions ------------------------------------------>
        <div class="row-fluid">
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <span class="fa fa-clock-o" aria-hidden="true"></span> Most Recent Transactions
                        <span class="pull-right">
                        <a href="viewAll.php"><span class="fa fa-search-plus" aria-hidden="true">&nbsp;</span>View All</a>
                    </span>
                    </div>
                    <div class="panel-body" style="padding:0 0 0 0;">

                        <table class="table table-striped" style="margin-bottom:0;">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Account</th>
                                <th>Amount</th>
                                <th>&nbsp;</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?=$recentTrans['table']?>
                            </tbody>
                        </table>

                        <div class="col-xs-2 divTransStat"><b>Income:</b></div>
                        <div class="col-xs-2 divTransStat" style="text-align:right; color:green;"><?=money_format('%n',$recentTrans['ttlIncome'])?></div>
                        <div class="col-xs-2 divTransStat"><b>Expenses:</b></div>
                        <div class="col-xs-2 divTransStat" style="text-align:right; color:red;"><?=money_format('%n',$recentTrans['ttlExpense'])?></div>
                        <div class="col-xs-2 divTransStat"><b>Variance:</b></div>
                        <div class="col-xs-2 divTransStat" style="color:<?=$variance >= 0 ? 'green' : 'red'?>"><?=$variance = money_format('%n',$recentTrans['ttlIncome']+$recentTrans['ttlExpense'])?></div>
                    </div>
                </div>
            </div>

            <!------------------------------------------ Recur Transactions ------------------------------------------->
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <div class="panel panel-info">
                    <div class="panel-heading" style="">
                        <span class="fa fa-repeat"></span> Repeat Transactions
                        <span class="pull-right">
                        <a href="viewAll.php"><span class="fa fa-search-plus" aria-hidden="true">&nbsp;</span>View All</a>
                    </span>
                    </div>
                    <div class="panel-body" style="padding:0 0 0 0;">
                        <table class="table table-striped" style="margin-bottom:0;">
                            <thead>
                            <tr>
                                <th>Next Payment</th>
                                <th>Account</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>End Date</th>
                                <th>Frequency</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?=$recurTable?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- container-fluid -->
</div><!-- main -->

<!--- js --->
<script src="../_assets/js/transactions/index.js" type="text/javascript"></script>

</body>
</html>
