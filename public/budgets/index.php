<?php
require_once('../../class_lib/Account.php');
require_once('../../class_lib/AuditLog.php');
require_once('../../class_lib/Budgets.php');
require_once('../../class_lib/Calculate.php');
require_once('../../class_lib/Crypt.php');
require_once('../../class_lib/Forecast.php');
require_once('../../class_lib/Database.php');
require_once('../../class_lib/Form.php');
require_once('../../class_lib/Session.php');
require_once('../../class_lib/Transactions.php');
require_once('../../class_lib/user.php');
require_once('../_assets/globalFunctions.php');

Session::check();
$db = new Database();
$conn = $db->openConnection();
$userCurrencyLabel = $_SESSION['currencyLabel'];
setlocale(LC_MONETARY, $_SESSION['locale']);
$userID = $_SESSION['userID'];

// Setting constants for budget form input names - used for budgets class
define("ACCOUNT_FORM",  "txtAccBudget");
define("CATEGORY_FORM", "txtCatBudget");

// Form tokens
if($_SERVER['REQUEST_METHOD'] == "GET") {
    $catToken  = Form::generateFormToken('form-bud-cat-bud');
    $accToken  = Form::generateFormToken('form-bud-acc-bud');
    $fcstToken = Form::generateFormToken('form-bud-forecast');
}

// Get arrays for account & category budgets, and the income & expenditures for the current month
$budget = New Budgets();
$accountBudgets  = $budget->accountBudgets;
$categoryBudgets = $budget->categoryBudgets;
$monthlyIncomeExpenses = $budget->getMonthlyIncomeExpense();


//*********************************************** Overview Tab *******************************************************//

// Budget variances for accounts and categories
$ttlAccVariance = $accountBudgets['total']+$monthlyIncomeExpenses['expenses'];
$ttlCatVariance = $categoryBudgets['total']+$monthlyIncomeExpenses['expenses'];

if(is_array($accountBudgets)) {
    // Gets the total revenue for each account for the current month
    $query1 = "SELECT SUM(transactionAmount) as total
               FROM transactions LEFT JOIN accounts ON accounts.accountID = transactions.accountID
               WHERE transactions.userID=:userID AND transactions.accountID=:ID 
               AND MONTH(transactionDate) = MONTH(CURRENT_DATE) AND YEAR(transactionDate) = YEAR(CURRENT_DATE)";
    // Gets the account names
    $query2 = "SELECT MAX(accountName) FROM accounts WHERE userID=:userID AND accountID=:ID";
    $accVariance = $budget->generateVarianceTable($query1,$query2,$accountBudgets["budgets"]);
    if(!is_array($accVariance)) {
        $error = Form::error_alert($accVariance);
    }
}

if(is_array($categoryBudgets)) {
    $query1 = "SELECT SUM(transactionAmount) as total
               FROM transactions 
               WHERE transactions.userID=:userID AND transactions.categoryID=:ID 
               AND MONTH(transactionDate) = MONTH(CURRENT_DATE) AND YEAR(transactionDate) = YEAR(CURRENT_DATE)";
    $query2 = "SELECT MAX(catName) FROM categories WHERE userID=:userID AND categoryID=:ID";
    $catVariance = $budget->generateVarianceTable($query1,$query2,$categoryBudgets["budgets"]);
    if(!is_array($catVariance)) {
        $error = Form::error_alert($catVariance);
    }
}


//*********************************************** Budgets Tab ********************************************************//

$catData = $budget->monthIndivVariance(false); // Category budget bar chart data
$accData = $budget->monthIndivVariance(true); // Account bar chart data

$accQuery = "SELECT accountID as ID, accountName as `NAME`, accountBudget as BUDGET
             FROM accounts WHERE userID = :userID ORDER BY accounts.accountTypeID ASC, accountName ASC";
// Returns array with all budgeted/non budgeted fields with counters, along with array of all fields
$accountFormInputs = $budget->generateForm($accQuery, constant("ACCOUNT_FORM"));


$catQuery = "SELECT categoryID as ID, catName as `NAME`, catBudget as BUDGET
             FROM categories WHERE userID=:userID ORDER BY catName ASC";
$catFormInputs = $budget->generateForm($catQuery, constant("CATEGORY_FORM"));


//********************************************** Forecast Tab ********************************************************//

$forecast = ""; // Holds html for chart or error message
$forecastData = ""; // Either holds array of forecast chart data, error message or blank
$forecastBreakdown = ""; // Holds breakdown data, like progress bars, averages etc.

$account = New accounts();
$accInfo = $account->info(); // get all user account information for select ddl
$accSelect = "";


if(array_filter($accInfo)) {
    foreach($accInfo as $id => $data) {
        $accSelect .= "<option value=\"".Crypt::encrypt($id)."\">".$data['name']." - ".$userCurrencyLabel.$data['balance']."</option>";
    }
} else {
    $accSelect = "<option>-- No Accounts created yet --</option>";
}


///********************** Forecast Ajax ************************/

if(isset($_POST['action'])) {
    switch($_POST['action']) {

        case "getForecast":
            if(Form::verifyFormToken('form-bud-forecast')) {
                $accountID = $_POST['accountID'];

                if($accountID === 0)
                    $accountID = key($accInfo); // Set default as the first account if one hasn't been set

                $accountID = Crypt::decrypt($accountID); // Decrypt account ID

                $f = New Forecast($conn,$accountID);
                $fcst = $f->calculate();

                if(is_array($fcst)) {
                    $forecastData  = $fcst; // chart data
                    $forecast      = "<canvas id=\"crtForecast\"></canvas>"; // chart html
                    $forecastBreakdown .= Forecast::progressStats("Month Complete", $f->monthProgress*100, true); // month progress

                    // forecast stats
                    $stats = array("Current Balance"       => $f->currentBalance,
                                   "Last Month's Balance"  => Text::getNthArrayElement($f->endOfMonthAccountBalances,2),
                                   "Average Balance"       => $f->accountStats["mean"],
                                   "Upcoming Transactions" => $f->upcomingPayments>=0 ? '+'.$f->upcomingPayments : $f->upcomingPayments,
                                   "Standard Deviation"    => $f->accountStats["stdDev"],
                                   "Forecast"              => Text::getNthArrayElement(array_reverse($forecastData['forecast'])));

                    foreach($stats as $label => $value) {
                        $forecastBreakdown .= Forecast::progressStats($label, money_format('%n',$value));
                    }

                    $result = "Ok";
                } else {
                    $forecast = Form::error_alert($fcst); // error message
                    $result   = "NotOk";
                }
            } else {
                AuditLog::hackAttempt("Forecast form in budgets page");
                Session::end();
                die("Hack attempt detected");
            }
            break;
    }

    $jsonResponse = array('Result'        => $result,
                          'fcstChart'     => $forecast,
                          'fcstData'      => $forecastData,
                          'fcstBreakdown' => $forecastBreakdown);

    echo json_encode($jsonResponse);
    die();
}



// Update category & account budgets
// Ajax not used due to problems with encryption on client-side
if($_SERVER['REQUEST_METHOD'] == "POST")
{
    // Save category budget changes
    if(!empty($_POST['btnSaveCatChanges'])) {
        if(Form::verifyFormToken('form-bud-cat-bud')) {
            // Run update query
            $message = $budget->updateBudgets(
                "UPDATE categories SET catBudget = :budget WHERE categoryID = :ID",
                $catFormInputs["allFields"],
                constant("CATEGORY_FORM")
            );
        } else {
            AuditLog::hackAttempt("Budgets update category budgets");
            Session::end();
            die("Hack attempt detected");
        }
        $stmt = null;
    }


    // Save Account budget changes (right column)
    if(!empty($_POST['btnSaveAccountChanges'])) {
        if(Form::verifyFormToken('form-bud-acc-bud')) {
            // Run update query
            $message = $budget->updateBudgets(
                "UPDATE accounts SET accountBudget = :budget WHERE accountID = :ID",
                $accountFormInputs["allFields"],
                constant("ACCOUNT_FORM")
            );
        } else {
            AuditLog::hackAttempt("Budgets update account budgets");
            Session::end();
            die("Hack attempt detected");
        }
        $stmt = null;
    }

}


$account = null;
$budget  = null;
$activeNav = "budgets";
require "../_shared/header.php";
require "../navigation/sideNav.php"; ?>

<style>

    .page-title { margin:0 0 0 0; }

    input.form-control {
        height:30px;
        font-size:12px;
    }
    .clearfix.seperator { padding:5px 0 5px 0; }

    span.subHead {
        margin-left:10px;
        display:block;
        font-size:13px;
    }

    /******/
    .budget-nav .budnav-item {
        height:30px;
        padding-top:6px;
        display:inline-block;
        background-color:inherit;
        border:none;
    }
    .budnav-item.active {
        border-bottom:2px solid cadetblue;
        border-left:none !important;
        font-size:13px;
    }
    .budnav-item:focus { outline:none; }

    .divChart {
        display:inline-block;
        width:49%;
    }

    #budgets-overview { display:block; }
    #budgets-budget { display:none; }
    #budgets-forecast { display:none; }

    /** Budget - Budgets tab **/
    .budgeted-accounts { width:100%; }
    .budgeted-accounts div.accountName {
        color:#5c83a3;
        margin-top:6px;
    }

    button {
        background-color: #51c0bf;
        color: #fd9e4b;
    }

    table.table-striped tr td { padding:2px 5px 2px 5px !important; }
    table.forecast-table tr td { padding:5px 5px 5px 5px !important;  }

    select#selAccount {
        border:none !important;
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
        border-bottom: 1px solid #e2e2e2 !important;
        font-size: 12px;
    }

</style>

<div class="main font-opensans">
    <?php require "../navigation/navbar.php"; ?>

    <div class="col-xs-12 page-title">
        <span>Budgets | <span>Manage your monthly spending budgets, with a calculated cash flow forecast for the remainder of the month.</span></span>
    </div>

    <div class="col-xs-12" id="response" style="margin-top:1em;">
        <?php if(isset($success)) { echo $success; }
              if(isset($error))   { echo $error;   }
              if(isset($message))   { echo $message;   } ?>
    </div>

    <div class="row-fluid" align="center">
        <div class="budget-nav col-xs-12" style="margin-bottom:2em; margin-top:1em;">
            <input type="button" class="budnav-item col-xs-4 active" value="Overview" id="btnOverview" data-div="budgets-overview" />
            <input type="button" class="budnav-item col-xs-4" value="Budgets" id="btnBudgets" data-div="budgets-budget" />
            <input type="button" class="budnav-item col-xs-4" value="Forecast" id="btnForecast" data-div="budgets-forecast" />
        </div>
    </div>

    <div class="clearfix" style="height:42px !important;"></div>

    <div class="container-fluid">

        <!---------------------------------------------- (NAV) Overview ----------------------------------------------->
        <div class="content" id="budgets-overview">

            <div class="row-fluid page-title">
                <span><span style="font-size:16px; padding-left:15px;">This Month</span></span>
            </div>

            <div class="clearfix" style="margin-bottom:1em;"></div>


            <!------- Category budgets overview ------->
            <div class="row-fluid">
                <div class="col-xs-12">
                    <div class="panel panel-info">
                        <div class="panel-heading"><b>Category</b> Budgets this month</div>
                        <div class="panel-body">
                            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12" style="border-right:1px solid #c5c5c5">
                                <input type="hidden" id="monthExpenses" value="<?=$monthlyIncomeExpenses['expenses']?>" />
                                <input type="hidden" id="categoriesBudget" value="<?=$categoryBudgets['total']?>" />
                                <canvas id="crtIncomeExpense" style="height:100px;margin-bottom:2em"></canvas>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12" style="overflow-y:scroll; max-height:100%">
                                <div class="col-xs-12">
                                    <?=$_SESSION['userFirstName']?>, your categories are <b><u><?=money_format('%n',$ttlCatVariance).($ttlCatVariance<0 ? ' over ' : ' under ')?> budget</u></b> this month.<br />
                                </div>
                                <div class="col-xs-12">
                                    <div style="margin-bottom:10px; margin-top:10px"><b>Over Budget</b></div>
                                    <table class="table table-striped"><?=isset($catVariance) ? $catVariance['over'] : ""?></table>
                                </div>
                                <div class="col-xs-12">
                                    <div style="margin-bottom:10px"><b>Under Budget</b></div>
                                    <table class="table table-striped"><?=isset($catVariance) ? $catVariance['under'] : ""?></table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="clearfix"></div>


            <!------- Account budgets overview -------->
            <div class="row-fluid">
                <div class="col-xs-12">
                    <div class="panel panel-success">
                        <div class="panel-heading"><b>Account</b> Budgets this month</div>
                        <div class="panel-body">
                            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12" style="border-right:1px solid #c5c5c5">
                                <input type="hidden" id="accountsBudget" value="<?=$accountBudgets['total']?>" />
                                <canvas id="crtBudgetDiff" style="height:100px;margin-bottom:2em"></canvas>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12" style="overflow-y:scroll; max-height:100%">
                                <div class="col-xs-12">
                                    <?=$_SESSION['userFirstName']?>, your accounts are <b><u><?=money_format('%n',$ttlAccVariance).($ttlAccVariance<0 ? ' over ' : ' under ')?> budget</u></b> this month.<br />
                                </div>
                                <div class="col-xs-12">
                                    <div style="margin-bottom:10px; margin-top:10px"><b>Over Budget</b></div>
                                    <table class="table table-striped"><?=isset($accVariance) ? $accVariance['over'] : ""?></table>
                                </div>
                                <div class="col-xs-12">
                                    <div style="margin-bottom:10px"><b>Under Budget</b></div>
                                    <table class="table table-striped"><?=isset($accVariance) ? $accVariance['under'] : ""?></table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="clearfix"></div>

        </div>



        <!---------------------------------------------- (NAV) Budgets ------------------------------------------------>
        <div class="content" id="budgets-budget">

            <div class="col-lg-8 col-md-7 col-sm-12 col-xs-12" id="divBarCharts" style="padding:0 !important;">
                <div class="col-xs-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">Category Budget Variance</div>
                        <div class="panel-body">
                            <canvas id="crtCatBudgets"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-xs-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">Account Budget Variance</div>
                        <div class="panel-body">
                            <canvas id="crtAccBudgets"></canvas>
                        </div>
                    </div>
                </div>
            </div><!--col (8)-->

            <div class="col-lg-4 col-md-5 col-sm-12 col-xs-12" style="padding:0 !important;">
                <div class="col-xs-12">
                    <div class="panel panel-info">
                        <div class="panel-heading">Monthly Category Budgets</div>
                        <div class="panel-body">
                            <form method="post" name="frmCatBudget" enctype="multipart/form-data">
                                <input type="hidden" id="catToken" name="token" value="<?=$catToken?>" />
                                <span class="subHead">Budgeted Categories <b>(<?=number_format($catFormInputs["budgeted"][1],0)?>)</b></span><hr>
                                <div class="budgeted-accounts">
                                    <?=$catFormInputs["budgeted"][0]?>
                                </div>
                                <br />
                                <span class="subHead">Non-Budgeted Categories <b>(<?=number_format($catFormInputs["nonBudgeted"][1],0)?>)</b></span><hr>
                                <div class="budgeted-accounts">
                                    <?=$catFormInputs["nonBudgeted"][0]?>
                                </div>
                                <br />
                                <div align="center">
                                    <input type="submit" class="btn btn-primary" name="btnSaveCatChanges" id="btnSaveCatChanges" value="Save Changes" />
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xs-12">
                    <div class="panel panel-info">
                        <div class="panel-heading">Monthly Account Budgets</div>
                        <div class="panel-body">
                            <form method="post" name="frmAccountBudget" enctype="multipart/form-data">
                                <input type="hidden" id="accToken" name="token" value="<?=$accToken?>" />
                                <span class="subHead">Budgeted Accounts <b>(<?=number_format($accountFormInputs["budgeted"][1],0)?>)</b></span><hr>
                                <div class="budgeted-accounts">
                                    <?=$accountFormInputs["budgeted"][0]?>
                                </div>
                                <br />
                                <span class="subHead">Non-Budgeted Accounts <b>(<?=number_format($accountFormInputs["nonBudgeted"][1],0)?>)</b></span><hr>
                                <div class="budgeted-accounts">
                                    <?=$accountFormInputs["nonBudgeted"][0]?>
                                </div>
                                <br />
                                <div align="center">
                                    <input type="submit" class="btn btn-primary" name="btnSaveAccountChanges" id="btnSaveAccountChanges" value="Save Changes" />
                                </div>
                            </form>
                        </div><!--panel-body-->
                    </div><!--panel-->
                </div><!--col (12)-->
            </div><!--col (4)-->

        </div>



        <!---------------------------------------------- (NAV) Forecast ----------------------------------------------->
        <div class="content" id="budgets-forecast">

            <div class="row-fluid">
                <div class="col-xs-12" style="margin-bottom:25px">Cash Flow Forecast is an <b>approximated</b> end of month account balance based on the account's income & expenditure patterns, represented in
                the graph by the <span style="color:red"><u>red</u></span> line. To forecast an account, at least <b>2 months</b> of transaction history is required.</div>
            </div>

            <div class="clearfix"></div>

            <form method="post" name="frmForecast" id="frmForecast" enctype="multipart/form-data">
                <input type="hidden" id="fcstToken" name="token" value="<?=$fcstToken?>" />
                <div class="row-fluid">
                    <div class="col-xs-12">
                        <div class="panel panel-info">
                            <div class="panel-heading"><span class="fa fa-line-chart" aria-hidden="true"></span> Cash Flow Forecast</div>
                            <div class="panel-body" style="padding:15px 0 0 0; min-height:100px">
                                <div class="container-fluid">
                                    <div class="col-lg-8 col-md-8 col-sm-12 col-xs-12">
                                        <select class="form-control" id="selAccount" name="selAccount" title="Account Select" style="margin-bottom:10px">
                                            <?=$accSelect?>
                                        </select>
                                        <div id="div-foreast">
                                            <?=$forecast?>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                                        <span style="font-size:14px;">Forecast Breakdown</span><hr style="padding-bottom:15px" />
                                        <div id="div-forecast-bkdwn">
                                            <?php if(isset($mthProgBar))    { echo $mthProgBar; } ?>
                                            <?php if(isset($curBalProgBar)) { echo $curBalProgBar; } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>

    </div><!-- container -->
</div>

<script type="text/javascript">

    var allMonths = ["January","February","March","April","May","June","July","August","September","October","November","December"];
    var today = new Date();
    var month = today.getMonth();
    month = allMonths[month];
    var year = today.getFullYear();
    var date = month + ' ' + year;


    // Overview tab charts
    function overviewCharts(date) {
        // Categories budget overview chart (doughnut)
        var expenses = 0 - $('#monthExpenses').val();
        var catBudget = $('#categoriesBudget').val() - expenses;
        if(catBudget < 0)
            catBudget = 0;
        var ctx = document.getElementById('crtIncomeExpense').getContext('2d');
        var chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [
                        catBudget,
                        expenses
                    ],
                    backgroundColor: [
                        '#3da3e8',
                        '#fc6585'
                    ],
                    label: 'Dataset 1'
                }],
                labels: [
                    "Budget",
                    "Actual"
                ]
            },
            options: {
                responsive: true,
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: date+' Spending'
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });

        // Accounts budget overview chart (doughnut)
        var accountBudget = $('#accountsBudget').val() - expenses;
        if(accountBudget<0)
            accountBudget=0;
        var ctx2 = document.getElementById('crtBudgetDiff').getContext('2d');
        var chart2 = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [
                        accountBudget,
                        expenses
                    ],
                    backgroundColor: [
                        '#51c0bf',
                        '#fd9e4b'
                    ],
                    label: 'Dataset 1'
                }],
                labels: [
                    "Budget",
                    "Actual"
                ]
            },
            options: {
                responsive: true,
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: date+' Budget'
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });
    }


    // Budgets tab charts
    function budgetCharts(date) {
        var catData = <?=json_encode($catData, JSON_PRETTY_PRINT)?>;
        var accData = <?=json_encode($accData, JSON_PRETTY_PRINT)?>;

        var catChartData = {
            labels: catData['labels'],
            datasets: [{
                label: 'Actual',
                backgroundColor: '#fc6585',
                data: catData['actual']
            }, {
                label: 'Budget',
                backgroundColor: '#3da3e8',
                data: catData['budget']
            }]

        };

        var ctx3 = document.getElementById('crtCatBudgets').getContext('2d');
        var chart3 = new Chart(ctx3, {
            type: 'bar',
            data: catChartData,
            options: {
                title: {
                    display: true,
                    text: date+' Category Budgets'
                },
                tooltips: {
                    mode: 'index',
                    intersect: false
                },
                responsive: true,
                scales: {
                    xAxes: [{
                        stacked: true
                    }],
                    yAxes: [{
                        stacked: true
                    }]
                }
            }
        });

        var accChartData = {
            labels: accData['labels'],
            datasets: [{
                label: 'Actual',
                backgroundColor: '#fd9e4b',
                data: accData['actual']
            }, {
                label: 'Budget',
                backgroundColor: '#51c0bf',
                data: accData['budget']
            }]
        };

        var ctx4 = document.getElementById('crtAccBudgets').getContext('2d');
        var chart4 = new Chart(ctx4, {
            type: 'bar',
            data: accChartData,
            options: {
                title: {
                    display: true,
                    text: date+' Account Budgets'
                },
                tooltips: {
                    mode: 'index',
                    intersect: false
                },
                responsive: true,
                scales: {
                    xAxes: [{
                        stacked: true
                    }],
                    yAxes: [{
                        stacked: true
                    }]
                }
            }
        });
    }


    // Forecast tab chart
    function forecastChart(date, values) {
        var fcstData = values;
        var ctx5 = document.getElementById('crtForecast').getContext('2d');
        var chart5 = new Chart(ctx5, {
            type: 'line',
            data: {
                labels: fcstData['labels'],
                datasets: [
                    {
                        label: "Actual",
                        backgroundColor: '#3ca0e3',
                        borderColor: '#3ca0e3',
                        data: fcstData['actual'],
                        fill: false
                    },
                    {
                        label: "Forecast",
                        backgroundColor: '#fd6585',
                        borderColor: '#fd6585',
                        data: fcstData['forecast'],
                        fill: false
                    }
                ]
            },
            options: {
                title: {
                    display: true,
                    text: date+' Forecast'
                },
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
                            labelString: date,
                            fontStyle: 'bold'
                        }
                    }],
                    yAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Amount (<?=$userCurrencyLabel?>)',
                            fontStyle: 'bold'
                        }
                    }]
                }
            }
        });
    }


    // Hides the bar charts if in smartphone viewports
    function checkViewport(width) {
        if(width < 768) {
            $('#divBarCharts').css('display','none');
        } else {
            $('#divBarCharts').css('display','block');
        }
    }


    // Load charts for overview page
    window.onload = function() { overviewCharts(date) };

    $(document).ready(function() {

        // If screen resolution is below 768px (bootstrap xs screen), hide bar charts
        checkViewport($(window).width());
        // Do the same as above if the window is resized
        $(window).resize(function() {
            checkViewport($(window).width());
        });

        // Top navigation menu functionality, showing relevant charts
        $('.budnav-item').click(function() {
            if($(this).hasClass('active')) {
                return true;
            } else {
                $('.budnav-item').removeClass('active');
                $(this).addClass('active');
                $('.content').css('display','none');

                switch($(this).val()) {
                    case "Overview":
                        $('#budgets-overview').css('display','block');
                        overviewCharts(date);
                        break;

                    case "Budgets":
                        $('#budgets-budget').css('display','block');
                        budgetCharts(date);
                        break;

                    case "Forecast":
                        $('#budgets-forecast').css('display','block');
                        forecastAJAX();
                        break;
                }
            }
        });

        var forecastData;

        // Forecast AJAX
        function forecastAJAX() {
            var formToken = $('#fcstToken').val(),
                accountID = $('#selAccount').val();

            $.post(document.location.href, {
                'action'    : 'getForecast',
                'token'     : formToken,
                'accountID' : accountID
            }, function(data) {
                try {
                    data = $.parseJSON(data);
                    if(data.Result = "Ok") {
                        $('#response').empty().append(data.message);
                        $('#div-foreast').empty().append(data.fcstChart); // chart html
                        $('#div-forecast-bkdwn').empty().append(data.fcstBreakdown); // forecast stats next to chart
                        forecastData = data.fcstData;
                        forecastChart(date,forecastData); // create chart with data
                    } else if(data.Result = "NotOk") {
                        $('#response').empty().append(data.message);
                        $('#div-foreast').empty().append(data.fcstChart);
                    }
                } catch(err) {
                    console.log(err);
                    $('#response').empty().append("Oops! Looks like an internal server problem. If problem persists, contact administrator..");
                }
            })
        }

        // Forecast account dropdown, runs ajax to calculate forecast
        $('#selAccount').on('change', function() {
            forecastAJAX();
        });

    });
</script>

<?php $db = null; $conn = null; ?>

</body>
</html>
