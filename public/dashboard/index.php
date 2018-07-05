<?php
require_once('../../class_lib/Account.php');
require_once('../../class_lib/AuditLog.php');
require_once('../../class_lib/Budgets.php');
require_once('../../class_lib/Calculate.php');
require_once('../../class_lib/Crypt.php');
require_once('../../class_lib/Database.php');
require_once('../../class_lib/Forecast.php');
require_once('../../class_lib/Form.php');
require_once('../../class_lib/Session.php');
require_once('../../class_lib/Transactions.php');
require_once('../../class_lib/user.php');
require_once('../_assets/globalFunctions.php');

Session::check(); // Check for active session
$db = new Database();
$conn = $db->openConnection();
setlocale(LC_MONETARY, $_SESSION['locale']);
$user_currency_label = $_SESSION['currencyLabel'];
$userID      = $_SESSION['userID'];
$firstName   = $_SESSION['userFirstName'];
$currentDate = date("D jS F Y"); // todays date in format 'Thu 26th April 2018'

// Generate form tokens for hidden inputs
if($_SERVER['REQUEST_METHOD'] == "GET") {
    $trans_token = Form::generateFormToken('form-dash-add-trans');
    $cat_token   = Form::generateFormToken('form-dash-add-cat');
    $acc_token   = Form::generateFormToken('form-dash-add-account');
}

// Get total Account & Category budgets for the month - for stats at the top of the page
$bud = new Budgets();
$ttlAccountBudget  = $bud->accountBudgets["total"];
$ttlCategoryBudget = $bud->categoryBudgets["total"];
$bud = null;


// Instantiate transactions & account objects & generate dropdown lists for forms
$transaction    = New transactions($conn);
$account        = New accounts();
$categories_ddl = $transaction->generateCategoriesDdl();
$accounts_ddl   = $transaction->generateAccountsDdl();
$accountTypes_ddl = $account->accountTypesDdl(); // Fetch account type dropdown select

$chartData      = $account->activity(); // Get income/expenditure data for line chart
$recentTransTbl = $transaction->renderDashRecentActivity(); // Getting most recent transactions table

// Check recurring transactions if it hasn't been done and make any necessary additions
if(!Session::keyExists('recurCheck'))
    $transaction->checkRecurringTransactions();
$newTransCount = $_SESSION['recurCount']; // number of new transactions since last login


// AJAX for add Account, Category & Transaction
if(isset($_POST['action'])) {
    $ddl = null;

    switch($_POST['action']) {

        // Add transaction form
        case 'addTransaction':
            if(Form::verifyFormToken('form-dash-add-trans')) {
                $result = $transaction->addNew(
                    Crypt::decrypt($_POST['type']),
                    (isset($_POST['account']) ? Crypt::decrypt($_POST['account']) : null),
                    (isset($_POST['category']) ? Crypt::decrypt($_POST['category']) : null),
                    strip_tags($_POST['amount']));
                if($result !== true) {
                    $response = Form::error_msg($result);
                } else {
                    $response = Form::success_msg("Transaction successfully added.");
                }
            } else {
                AuditLog::hackAttempt("Dashboard add transaction form");
                Session::end();
                die("Hack attempt detected");
            }
            break;


        // Add category form
        case 'addCategory':
            if(Form::verifyFormToken('form-dash-add-cat')) {
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


        // Add account form
        case 'addAccount':
            if(Form::verifyFormToken('form-dash-add-account')) {
                $result = $account->add(
                    strip_tags(trim($_POST['name'])),
                    Crypt::decrypt($_POST['type']),
                    strip_tags($_POST['balance']),
                    isset($_POST['incNet']) ? $_POST['incNet'] : 0);
                if($result !== true) {
                    $response = Form::error_msg($result);
                } else {
                    $response = Form::success_msg("New account added: ".txtSafe($_POST['name']));
                    $ddl = $transaction->generateAccountsDdl();
                }
            } else {
                AuditLog::hackAttempt("Dashboard add account form");
                Session::end();
                die("Hack attempt detected");
            }
            break;
    }

    $jsonResponse = array('Result'=>'Ok', 'message'=>$response, 'ddl'=>$ddl);
    echo json_encode($jsonResponse);
    die();
}


$transaction = null;
$account = null;
$db = null;
$conn = null;
$activeNav = "dashboard";
require "../_shared/header.php";
require "../navigation/sideNav.php"; ?>

<style>
    .clearfix { margin-bottom:1em; }

    .divContentLeft {
        display: inline-block;
        /*width: 550px;*/
        /*padding:0 0 0 0;*/
    }
    .divContentRight {
        padding-left:0 !important;
        /*padding-right:0 !important;*/
    }

    .divContentRight select, .divContentRight input {
        width: 100%;
        height: 2.5em;
        font-size:12px;
    }

    .divContentRight .panel.panel-default {
        border: none;
        margin-bottom:0;
    }
    .divContentRight .panel.panel-default > .quick-add-body {
        border-top: 5px solid #f5f5f5;
        margin-bottom:1em;
    }
    .quick-add-body label {
        font-weight:normal;
    }

    .quick-add-body .col-xs-12 {
        padding: 0 !important;
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

    .table-condensed>tbody>tr>td { border-top:none !important; }

    .table-striped > tbody > tr:nth-child(2n+1) > td,
    .table-striped > tbody > tr:nth-child(2n+1) > th {
        /*background-color: #e8f0fe;*/
    }

    .quick-add-body ul li { margin-top:1em; }
    span.help-title {
        font-size:1.1em;
        font-weight:bold;
        display:block;
        margin-bottom:.5em;
    }

    div.dashStat {
        height:60px;
        padding: 5px 0 5px 0;
        text-align:center;
        margin-bottom:1em;
        text-wrap:normal;
        /*border-left:1px solid #3b7cb3;*/
    }
    div.dashStat>.dashStatTitle {
        font-weight:bold;
        display:block;
    }
    div.dashStat>.dashStatBody{
        font-size:1.6em;
        color: #21303e;
    }
</style>


<div class="main">
    <?php require "../navigation/navbar.php";?>

    <div class="container-fluid">

        <div class="col-xs-12 page-title">
            <span>Dashboard | <span>Take a look at your recent activity with a monthly income & expenses chart, or add a new transaction, category or account.</span></span>
        </div>

        <div class="divBuffer">
            <span class="fa fa-spinner fa-pulse fa-2x fa-fw"></span>
            <span class="sr-only">Loading...</span><br />
            <span>Loading...</span>
        </div>

        <div class="row-fluid">
            <div class="col-xs-12">
                <?php if(isset($error)) { echo "<div style=\"margin-bottom:1em\">".Form::error_alert($error)."</div>"; } ?>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="row-fluid">
            <div class="col-xs-12">
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-6 dashStat">
                    <div class="dashStatTitle">New Transactions Since Last Login</div>
                    <div class="dashStatBody"><?=$newTransCount?></div>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-6 dashStat">
                    <div class="dashStatTitle">Total Account Budget for the Month</div>
                    <div class="dashStatBody"><?=money_format('%n',$ttlAccountBudget)?></div>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-6 dashStat">
                    <div class="dashStatTitle">Total Category Budget for the Month</div>
                    <div class="dashStatBody"><?=money_format('%n',$ttlCategoryBudget)?></div>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-6 dashStat">
                    <div class="dashStatTitle">Date</div>
                    <div class="dashStatBody" style="font-size:1.4em"><?=$currentDate?></div>
                </div>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="row-fluid" id="divContentLeft">
            <!--- Content Left --->
            <div class="divContentLeft col-lg-9 col-md-8 col-sm-12 col-xs-12" >

                <div class="row-fluid">
                    <div class="col-xs-12">
                        <div class="panel panel-info" id="panelIncExp">
                            <div class="panel-heading"><span class="fa fa-line-chart" aria-hidden="true"></span> Income/Expenses </div>
                            <div class="panel-body">
                                <!-- line chart -->
                                <canvas id="crtAccountVariance"></canvas>
                                <div class="col-xs-12" align="center" style="padding: 10px 0 10px 0; font-size:14px; border-bottom:1px solid #ededed; border-top:1px solid #ededed; margin-bottom:5px">Recent Activity</div>
                                <div class="col-xs-12">
                                    <table class="table table-condensed table-striped" style="margin-bottom:0" id="tblRecent">
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Account</th>
                                            <th>Category</th>
                                            <th style="text-align:center">In</th>
                                            <th style="text-align:center">Out</th>
                                            <th style="text-align:center">Balance</th>
                                        </tr>
                                        </thead>
                                        <tbody><?=$recentTransTbl?></tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="clearfix"></div>

            </div>


            <div class="divContentRight col-lg-3 col-md-4 col-sm-12 col-xs-12">

                <!--- Add new account panel --->
                <div class="row-fluid">
                    <div class="col-xs-12" style="padding:0 !important;">
                        <div class="panel panel-default">
                            <div class="panel-heading quick-add-btn" style="background-color:white">
                                <b>Add Account</b>
                                <span class="pull-right">
                                    <a href="#" class="" data-value="account"><span class="fa fa-plus" aria-hidden="true"></span></a>
                                </span>
                            </div>
                            <div class="panel-body quick-add-body" id="divAccForm" style="display:none">
                                <form method="post" name="form-dash-add-account" id="form-dash-add-account" enctype="multipart/form-data">
                                    <input type="hidden" name="token" id="accountToken" value="<?=$acc_token?>" />
                                    <div class="row-fluid">
                                        <label class="control-label" for="txtAccountName">Account Name</label>
                                        <input class="form-control" name="txtAccountName" id="txtAccountName" type="text" placeholder="E.g. Current Account">
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid">
                                        <label for="accountTypeSelect">Account Type</label><br />
                                        <select class="form-control" title='accountType' id="accountTypeSelect" name='accountTypeSelect'>
                                            <?=$accountTypes_ddl?>
                                        </select>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid">
                                        <label class="control-label" for="txtOpeningBalance">Opening Balance</label>
                                        <div class="input-group">
                                            <span class="input-group-addon"><?=$user_currency_label?></span>
                                            <input class="form-control" name="txtOpeningBalance" id="txtOpeningBalance" type="text" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid">
                                        <div class="checkbox">
                                            <label><input type="checkbox" name="chkIncNet" id="chkIncNet" value="1" style="height:0;" checked="checked"> Include in overall Net Worth</label>
                                        </div><br />
                                        <span style="font-size:11px; text-wrap:normal;">This option can be changed anytime in Account > Manage Accounts.</span>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid" id="accountResponse">

                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid" style="margin-bottom:5px;">
                                        <input type="button" class="btn btn-primary" name="btnAddAccount" id="btnAddAccount" value="Add Account" />
                                    </div>

                                    <?php if(isset($accountadd_msg)) { ?>
                                        <div class="err">
                                            <?=$accountadd_msg;?>
                                        </div>
                                    <?php } ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="clearfix"></div>

                <!--- Add Category panel --->
                <div class="row-fluid">
                    <div class="col-xs-12" style="padding:0 !important;">
                        <div class="panel panel-default">
                            <div class="panel-heading quick-add-btn" style="background-color:white">
                                <b>Add Category</b>
                                <span class="pull-right">
                                    <a href="#" class="" data-value="category"><span class="fa fa-plus" aria-hidden="true"></span></a>
                                </span>
                            </div>
                            <div class="panel-body quick-add-body" id="divCatForm" style="display:none">
                                <form method="post" name="form-dash-add-cat" id="form-dash-add-cat" enctype="multipart/form-data">
                                    <input type="hidden" name="token" id="catToken" value="<?=$cat_token?>" />
                                    <div class="row-fluid">
                                        <div class="col-xs-12">
                                            <label for="txtCatName">Name</label>
                                            <input type="text" class="form-control" name="txtCatName" id="txtCatName" title="Category Name" placeholder="e.g. Groceries" />
                                        </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid">
                                        <div class="col-xs-12">
                                            <label class="control-label" for="txtCatBudget">Monthly Budget (blank if none)</label>
                                            <div class="input-group">
                                                <span class="input-group-addon"><?=$user_currency_label?></span>
                                                <input type="text" class="form-control" name="txtCatBudget" id="txtCatBudget" title="Category Budget" placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid" id="catResponse">

                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid">
                                        <input type="button" class="btn btn-primary" name="btnAddCat" id="btnAddCat" value="Add Category" />
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="clearfix"></div>

                <!--- Add transaction panel --->
                <div class="row-fluid">
                    <div class="col-xs-12" style="padding:0 !important;">
                        <div class="panel panel-default">
                            <div class="panel-heading quick-add-btn" style="background-color:white">
                                <b>Add Transaction</b>
                                <span class="pull-right">
                                    <a href="#" class="" data-value="transaction"><span class="fa fa-plus" aria-hidden="true"></span></a>
                                </span>
                            </div>
                            <div class="panel-body quick-add-body" id="divTransForm" style="display:none">
                                <form method="post" name="frm-dash-add-trans" id="frm-dash-add-trans" enctype="multipart/form-data">
                                    <input type="hidden" name="token" id="transToken" value="<?=$trans_token?>" />
                                    <div class="row-fluid">
                                        <div class="col-xs-12">
                                            <label for="selTransType">Type &nbsp;</label>
                                            <select class="form-control" name="selTransType" id="selTransType" title="Transaction Type">
                                                <option value="<?=Crypt::encrypt("income")?>">Income</option>
                                                <option value="<?=Crypt::encrypt("expense")?>">Expense</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid">
                                        <div class="col-xs-12">
                                            <label for="selAccounts">Account &nbsp;</label>
                                            <select class="form-control" name="selAccounts" id="selAccounts" title="Account">
                                                <?=$accounts_ddl?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid">
                                        <div class="col-xs-12">
                                            <label for="selCategories">Category &nbsp;</label>
                                            <select class="form-control" name="selCategories" id="selCategories" title="Categories">
                                                <?=$categories_ddl?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid">
                                        <div class="col-xs-12">
                                            <label class="control-label" for="txtTransAmount">Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-addon"><?=$user_currency_label?></span>
                                                <input type="text" class="form-control" name="txtTransAmount" id="txtTransAmount" title="Transaction Amount" placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid" id="transResponse">

                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="row-fluid">
                                        <input type="button" class="btn btn-primary" name="btnNewTrans" id="btnNewTrans" value="Add Transaction" />
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="clearfix"></div>


                <!--- help panel --->
                <div class="row-fluid">
                    <div class="col-xs-12" style="padding:0 !important;">
                        <div class="panel panel-default">
                            <div class="panel-heading quick-add-btn" style="background-color:white">
                                <b>Help</b>
                                <span class="pull-right">
                                    <a href="#" class="" data-value="account"><span class="fa fa-plus" aria-hidden="true"></span></a>
                                </span>
                            </div>
                            <div class="panel-body quick-add-body" style="display:none; text-wrap:normal; font-size:.9em; text-align: justify">
                                <div class="col-xs-12">
                                    <span class="help-title">Accounts</span>
                                    Here you can add any account that you own (which can be real accounts or ones created superficially) that will fall under
                                    <i>three</i> different types:
                                    <ul>
                                        <li>
                                            <b>Checking</b> or Current accounts, which can be personal banking accounts like a Santander 123
                                            current account, payment transfer systems like PayPal, or a company account;
                                        </li>
                                        <li><b>Savings accounts</b>, can include any bank savings , personal savings or to save for specific things (e.g. New Car or a Holiday);</li>
                                        <li>
                                            <b>Credits</b>, will deduct from your overall net worth (unless changed otherwise in <a href="/manage/manageAccounts.php"><u>Manage Accounts</u></a>),
                                            can include anything like credit cards, mortgages, or insurance.
                                        </li>
                                    </ul>
                                    <br />
                                    When adding an account, simply give it a name (e.g. Current account), assign an account type, set the current balance (balance will be set to <?=money_format('%n',0)?>
                                    if left blank), give it a monthly budget if you want to keep track of how much you're spending (this can be changed in the <a href="/budgets/index.php"><u>Budgets</u></a>
                                    page), and select - using the checkbox - if you want the account to be included in your overall net worth*.<br /><br />

                                    <span class="help-title">Categories</span>
                                    These are used to help you segregate your transactions, making it easier to view and manage them. Example categories might include groceries, phone bill or rent. They can then be
                                    individually tracked in the <a href="/budgets/index.php"><u>Budgets</u></a> & <a href="/stats/index.php"><u>Stats</u></a> page to see the overall income/expenditure for each.
                                    <br /><br />Same as the accounts, you can set a monthly budget to help you manage how much you're spending on each. <br /><br />

                                    <span class="help-title">Transactions</span>
                                    Transactions are used to either add money in (<b>income</b>), or take money out (<b>expense</b>) of a specific account. Adding a transaction can be done by simply giving
                                    it a type, set which account the transaction will be applied to, categorise it (using your created categories), then set the amount.<br/> <br />
                                    For any transactions that repeat over a period of time, for example, a phone bill, mortgage payments or paycheck, go to the <a href="/transactions/index.php"><u>Transactions</u></a>
                                    page to apply a recurring payment over a <b>daily</b>, <b>weekly</b>, <b>monthly</b> or <b>yearly</b> time period. You can also give it a start date (required), an end date (or select 'Repeat Indefinitely'
                                    if there is no end date).<br /><br />
                                    All transactions can be viewed <a href="/transactions/viewAll.php"><u>Here</u></a>, or for transactions on a specific account, head over to <a href="/manage/manageAccounts.php"><u>Manage Accounts</u></a>
                                    and click the <span class="fa fa-pencil" aria-hidden="true"></span> edit button to see the account's activity.<br /><br /><br />

                                    <span style="font-size:.9em;"><i>*Net worth is the sum of all your accounts, minus any credits. If you want to exclude any accounts from the net worth calculation,
                                        go to <a href="/manage/manageAccounts.php"><u>Manage Accounts</u></a> to change it.</i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!--divContentRight-->
        </div>


    </div> <!-- container -->

</div>

<script>

    function spinner(view) {
        if(view === "show") {
            $(".divBuffer").css('display','block');
        } else if(view === "hide") {
            $(".divBuffer").css('display','none');
        }
    }

    function checkViewport(width) {
        if(width < 768)
            $('#panelIncExp').css('display','none');
        else
            $('#panelIncExp').css('display','block');
    }

    function errorMsg(msg) {
        return "<span style=\"color:red; margin-top:1em;\"><span class=\"fa fa-exclamation-circle\">&nbsp;</span>"+msg+"</span>"
    }

    $(document).ready(function() {

        // Quick-add form dropdowns for transaction, account & category
        $('.quick-add-btn', this).on('click',function(e) {
            e.preventDefault();
            if ($("span.fa", this).hasClass("fa-plus")) {
                $("span.fa", this).removeClass("fa-plus").addClass("fa-minus")
            } else if ($("span.fa", this).hasClass("fa-minus")) {
                $("span.fa", this).removeClass("fa-minus").addClass("fa-plus")
            }

            if($('~ .quick-add-body', this).css('display') == 'none') {
                $('~ .quick-add-body', this).css('display','block');
            } else {
                $('~ .quick-add-body', this).css('display','none');
            }
        });

        // If screen resolution is below 768px (bootstrap xs screen), hide graph
        checkViewport($(window).width());
        // Do the same as above if the window is resized
        $(window).resize(function() { checkViewport($(window).width());} );

        /******* AJAX *******/

        // Add transaction ajax
        $('#btnNewTrans').on('click', function() {

            var formToken = $('#transToken').val(),
                type      = $('#selTransType').val(),
                account   = $('#selAccounts').val(),
                category  = $('#selCategories').val(),
                amount    = $('#txtTransAmount').val();

            var error = "";

            if($.isNumeric(amount) == false) {
                error = "Transaction amount must be a number.";
            }

            if(error == "") {
                spinner("show");

                $.post(document.location.href, {
                    'action'  : 'addTransaction',
                    'token'   : formToken,
                    'type'    : type,
                    'account' : account,
                    'category': category,
                    'amount'  : amount
                }, function(data) {
                    try {
                        data = $.parseJSON(data);
                        if(data.Result == "Ok") {
                            spinner("hide");
                            $('#transResponse').empty().append(data.message);
                            $('#frm-dash-add-trans')[0].reset();
                        }
                    } catch(err) {
                        console.log(err)
                    }
                })

            } else {
                error = errorMsg(error);
                $('#transResponse').empty().append(error);
            }
        });



        // Add category ajax
        $('#btnAddCat').on('click', function() {
            var formToken = $('#catToken').val(),
                catName = $('#txtCatName').val(),
                catBudget = $('#txtCatBudget').val();

            spinner("show");

            $.post(document.location.href, {
                'action' : 'addCategory',
                'token'  : formToken,
                'name'   : catName,
                'budget' : catBudget
            }, function(data) {
                try {
                    data = $.parseJSON(data);
                    if(data.Result == "Ok") {
                        spinner("hide");
                        $('#catResponse').empty().append(data.message);
                        $('#form-dash-add-cat')[0].reset();
                        $('#selCategories').empty().append(data.ddl);
                    }
                } catch(err) {
                   console.log(err);
                    $('#catResponse').empty().append("Oops! Looks like an internal server problem. If problem persists, contact administrator.");
                }
            })
        });



        // Add account ajax
        $('#btnAddAccount').on('click', function() {
            var formToken = $('#accountToken').val(),
                name = $('#txtAccountName').val(),
                type = $('#accountTypeSelect').val(),
                balance = $('#txtOpeningBalance').val(),
                incNet = $('#chkIncNet').prop('checked');

            spinner("show");

            incNet = incNet==true ? 1 : 0;

            $.post(document.location.href, {
                'action'  : 'addAccount',
                'token'   : formToken,
                'name'    : name,
                'type'    : type,
                'balance' : balance,
                'incNet'  : incNet
            }, function(data) {
                try {
                    data = $.parseJSON(data);
                    if(data.Result = "Ok") {
                        spinner("hide");
                        $('#accountResponse').empty().append(data.message);
                        $('#form-dash-add-account')[0].reset();
                        $('#selAccounts').empty().append(data.ddl);
                    }
                } catch(err) {
                    console.log(err);
                    $('#accountResponse').empty().append("Oops! Looks like an internal server problem. If problem persists, contact administrator..");
                }
            })
        });


        /******* Charts *******/

        var chartData = <?=json_encode($chartData)?>;

        var ctx = document.getElementById('crtAccountVariance').getContext('2d');
        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData['labels'],
                datasets: [
                    {
                        label: "Income",
                        backgroundColor: '#3ca0e3',
                        borderColor: '#3ca0e3',
                        data: chartData['inc'],
                        fill: false
                    },
                    {
                        label: "Expenses",
                        backgroundColor: '#fd6585',
                        borderColor: '#fd6585',
                        data: chartData['exp'],
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                tooltips: {
                    mode: 'index',
                    intersect: false
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                },
                scales: {
                    xAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Month',
                            fontStyle: 'bold'
                        }
                    }],
                    yAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Amount (<?=$user_currency_label?>)',
                            fontStyle: 'bold'
                        }
                    }]
                }
            }
        });

    });

</script>
</body>
</html>
