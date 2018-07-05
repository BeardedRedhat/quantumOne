<?php
require_once('../../class_lib/Account.php');
require_once('../../class_lib/AuditLog.php');
require_once('../../class_lib/Budgets.php');
require_once('../../class_lib/Crypt.php');
require_once('../../class_lib/Database.php');
require_once('../../class_lib/Form.php');
require_once('../../class_lib/Session.php');
require_once('../../class_lib/Stats.php');
require_once('../_assets/globalFunctions.php');

Session::check(); // Check for active session
$db = new Database();
$conn = $db->openConnection();
setlocale(LC_MONETARY, $_SESSION['locale']);
$user_currency_label = $_SESSION['currencyLabel'];
$userID = $_SESSION['userID'];

$stats   = New Stats();
$account = New accounts();
$budObj  = New Budgets();

$statPanels    = $stats->renderStatPanels(); // Fetches and returns html for total transactions, income, expenses for month, plus total accounts
$accBudgetProg = Stats::renderProgressBar("Monthly Account Budget",  $budObj->variance()); // Account budget progress bar data
$catBudgetProg = Stats::renderProgressBar("Monthly Category Budget", $budObj->variance(false)); // Category budget progress bar data
$accountsHTML  = Stats::renderAccountsWithTypes($account->listAccountsWithTypes(false)); // Account balances with types next to line chart

// Account Balance line chart
$accLabels   = Text::getYearDates(true, "m-Y"); // Gets all year dates in format MM-YYYY
$accDataSets = $stats->getAccountActivity();    // Account balance line chart data
$accColours  = array();
if(is_array($accDataSets)) {
    foreach($accDataSets as $k => $v)
        $accColours[] = $stats->getColor(); // Generate colours for each account line
    $stats->index = 0; // reset color index for next chart
} else {
    $accDataSets = 0;
}


// Get values for net worth pie chart & generate colours
$netWorthStats   = $stats->netWorthStats();
$netWorthColours = array();
if(is_array($netWorthStats)) {
    foreach($netWorthStats['labels'] as $k => $v)
        $netWorthColours[] = $stats->getColor();
    $stats->index = 0; // reset colour index
} else {
    $netWorthStats = 0;
}

$categoryRevenue = $stats->renderRevenueTable($stats->categoryStats()); // Returns table for category revenues, sorted ASC
$accountRevenue  = $stats->renderRevenueTable($stats->accountRevenue()); // Returns table for account revenue ASC


$stats   = null;
$budObj  = null;
$account = null;

$db   = null;
$conn = null;
$activeNav = "stats";
require "../_shared/header.php";
require "../navigation/sideNav.php"; ?>

<style>

    .no-pad { padding:0 !important;  }

    /** stat cards **/
    div.statCard { height:70px; }
    .col-xs-12.statLabel {
        font-size:12px;
        font-weight:bold;
    }
    .col-xs-12.statValue {
        font-size:20px;
        color: #1e214a;
    }

    .account-type {
        font-size:14px;
        margin-bottom:.5em;
        font-weight:bold;
    }

    table { width:100%; margin-bottom:0.5em !important;}
    table tr td {
        text-wrap:normal;
    }
    table tr td:nth-child(even) {
        text-align:right;
    }

</style>

<div class="main">
    <?php require "../navigation/navbar.php"; ?>

    <div class="col-xs-12 page-title">
        <span>Statistics | <span>View cumulative & periodical statistics for your accounts. The charts show your overall net worth and account balances for each account.</span></span>
</div>

    <div class="container-fluid">

        <div class="row-fluid">
            <div class="col-xs-12 no-pad">
                <?=$statPanels?>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="row-fluid">
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 no-pad">
                <?=$accBudgetProg?>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 no-pad">
                <?=$catBudgetProg?>
            </div>
        </div>

        <div class="clearfix" style="margin-bottom:2em;"></div>

        <div class="row-fluid">
            <div class="col-xs-12">
                <div class="panel panel-info">
                    <div class="panel-heading"><span class="fa fa-line-chart" aria-hidden="true"></span> Account Balances</div>
                    <div class="panel-body">
                        <div class="col-lg-8 col-md-8 col-sm-12 col-xs-12" id="divLineChart">
                            <canvas id="crtAccountBalances"></canvas>
                            <i>Note: Accounts that have no transaction history will not appear on the chart.</i>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                            <?=$accountsHTML?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="row-fluid">
            <div class="col-lg-6 col-md-8 col-sm-12 col-xs-12">
                <div class="panel panel-success">
                    <div class="panel-heading">Overall Net Worth</div>
                    <div class="panel-body">
                        <div class="col-lg-8 col-md-10 col-sm-12 col-xs-12 col-lg-offset-2 col-md-offset-1" align="center">
                            <canvas id="crtNetWorth"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-4 col-sm-12 col-xs-12">
                <div class="panel panel-warning">
                    <div class="panel-heading"><?=date('F Y')?> Category Revenues</div>
                    <div class="panel-body" style="max-height:400px; overflow-y:scroll;">
                        <table class="table table-condensed">
                            <thead>
                            <tr>
                                <th></th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?=$categoryRevenue?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-4 col-sm-12 col-xs-12">
                <div class="panel panel-warning">
                    <div class="panel-heading"><?=date('F Y')?> Account Revenues</div>
                    <div class="panel-body" style="max-height:400px; overflow-y:scroll;">
                        <table class="table table-condensed">
                            <thead>
                            <tr>
                                <th></th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?=$accountRevenue?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>


<script type="text/javascript">

    function checkViewport(width) {
        if(width < 768) {
            $('#divLineChart').css('display','none');
        } else {
            $('#divLineChart').css('display','block');
        }
    }

    $(document).ready(function() {

        // If screen resolution is below 768px (bootstrap xs screen), hide bar charts
        checkViewport($(window).width());
        // Do the same as above if the window is resized
        $(window).resize(function() { checkViewport($(window).width());} );

        var accLabels = <?=json_encode($accLabels, JSON_PRETTY_PRINT)?>,
            accData   = <?=json_encode($accDataSets, JSON_PRETTY_PRINT)?>,
            accColors = <?=json_encode($accColours)?>,
            accIndex  = -1;

        var datasets = [];

        // Create datasets for chart - each data set represents each account
        for(var key in accData) {
            if(accData.hasOwnProperty(key)) {
                accIndex++;
                datasets.push({
                    label: key,
                    backgroundColor: accColors[accIndex],
                    borderColor: accColors[accIndex],
                    data: accData[key],
                    fill: false
                });
            }
        }

        var ctx = document.getElementById('crtAccountBalances').getContext('2d');
        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: accLabels,
                datasets: datasets
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


        // Net worth pie chart
        var netData    = <?=json_encode($netWorthStats)?>,
            netColours = <?=json_encode($netWorthColours)?>;

        var ctx2 = document.getElementById('crtNetWorth').getContext('2d');
        var chart2 = new Chart(ctx2, {
            type: 'pie',
            data: {
                datasets: [{
                    data: netData['values'],
                    backgroundColor: netColours,
                    label: 'Dataset 1'
                }],
                labels: netData['labels']
            },
            options: {
                responsive: true,
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        });


    });
</script>

</body>
</html>
