<?php
require_once('../../class_lib/Account.php');
require_once('../../class_lib/AuditLog.php');
require_once('../../class_lib/Budgets.php');
require_once('../../class_lib/Calculate.php');
require_once('../../class_lib/Crypt.php');
require_once('../../class_lib/Database.php');
require_once('../../class_lib/Form.php');
require_once('../../class_lib/Session.php');
require_once('../../class_lib/Transactions.php');
require_once('../../class_lib/user.php');

Session::check();
$nav_db = New Database();
$nav_conn = $nav_db->openConnection();
setlocale(LC_MONETARY, $_SESSION['locale']);
$nav_userID = $_SESSION['userID'];
$user_currency = $_SESSION['currencyLabel'];

$calculate = New Calculate();
$bud       = New Budgets();
$trans     = New transactions($nav_conn);

$firstName  = $_SESSION['userFirstName']; // User first name
$netWorth   = $calculate->netWorth(); // Net Worth
$netWorthVariance = $calculate->netWorthVariance(); // Net worth variance
$totalTrans = $trans->totalTransactions(); // Total transactions

$variance = $bud->variance();
$nav_budgetVariance = money_format('%n', $variance['variance']);
$nav_budgetVariance = $variance['variance'] >=0 ? "+".$nav_budgetVariance : $nav_budgetVariance;

// Checking admin access to display or hide 'Admin' menu tab
$adminChk = false;
if(Session::keyExists('adminCheck')) {
    if($_SESSION['adminCheck'] == "YWUyc3VFK0t5QjRhNXVQOUZBdWdSUT09")
        $adminChk = true;
}

$nav_db = null;
$nav_conn = null; ?>
<style>

</style>
<div class="nn">
    <div class="xs-nav" style="display:none">
        <a class="closeMenu">X</a>
    </div>
</div>
<div class="navbar navbar-default">

    <div class="divXSmenu" style="padding-left:10px; display:none;">
        <!--- toggle menu button when xs screen resolution --->
        <ul class="nav navbar-nav pull-left raleway-font">
            <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><span class="fa fa-bars"></span> MENU </a>
                <ul class="dropdown-menu" style="font-size:13px !important; box-shadow:none !important;">
                    <li><a href="/dashboard/index.php"><span class="fa fa-tachometer" aria-hidden="true"></span> Dashboard</a></li>
                    <li><a href="/transactions/index.php"><span class="fa fa-exchange" aria-hidden="true"></span> Transactions</a></li>
                    <li><a href="/budgets/index.php"><span class="fa fa-money" aria-hidden="true"></span> Budgets</a></li>
                    <li><a href="/stats/index.php"><span class="fa fa-bar-chart" aria-hidden="true"></span> Stats</a></li>
                    <?php if($adminChk) { ?>
                        <li role="separator" class="divider"></li>
                        <li><a href="/admin/index.php"><span class="fa fa-user-circle-o" aria-hidden="true"></span> Admin</a></li>
                    <?php } ?>
                </ul>
            </li>
        </ul>
    </div>


    <div class="divProfilePic pull-right">
        <img src="/_assets/img/profilePic.png" class="img-circle" />
    </div>
    <div class="navStats pull-left noFont">
        Net Worth &nbsp;<?=$netWorthVariance?><br />
        <span class="spanStatAmount" style="<?=$netWorth >= 0 ? "color:green;" : "color:red;"?>">
            <?=money_format('%n', $netWorth);?>
        </span>
    </div>
    <div class="navStats pull-left noFont">
        Budget Variance &nbsp;<?=Calculate::pcentVariance(1,1);?><br />
        <span class="spanStatAmount" style="<?=$variance['variance'] >= 0 ? "color:green;" : "color:red;"?>"><?=$nav_budgetVariance?></span>
    </div>
    <div class="navStats pull-left noFont" style="text-align:center;">
        Transactions<br />
        <span class="spanStatAmount" style="color:#1d2044"><?=number_format($totalTrans,0)?></span>
    </div>

    <ul class="nav navbar-nav pull-right raleway-font">
        <li class="dropdown">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><?=$firstName?> <span class="caret"></span></a>
            <ul class="dropdown-menu" style="font-size:13px !important; box-shadow:none !important;">

                <?php if($activeNav !== "dashboard" || "transactions" || "budgets" || "stats" || "admin") { ?>
                    <li><a href="/dashboard/index.php">Home</a></li>
                    <li role="separator" class="divider"></li>
                <?php } ?>

                <li><a href="/manage/myProfile.php">My Profile</a></li>
                <li><a href="/manage/manageAccounts.php">Manage Accounts</a></li>
                <li role="separator" class="divider"></li>
                <li><a href="/manage/help.php">Help</a></li>
                <li><a href="/manage/contact.php">Contact</a></li>
                <li><a href="/logout.php">Logout</a></li>
            </ul>
        </li>
    </ul>

</div>

<script>

    $(document).ready(function() {

        $('.dropdown-toggle').dropdown(); // Toggle dropdown menu

        $('.fa.fa-bars').on('mouseleave', function() {
            $(this).css('-webkit-transform','').css('-ms-transform','').css('transition','.5s ease');
        });

        if($(window).width() <= 768) {
            $('.divXSmenu').css('display','block');
        } else {
            $('.divXSmenu').css('display','none');
        }

        $(window).resize(function() {
            if($(window).width() <= 768) {
                $('.divXSmenu').css('display','block');
            } else {
                $('.divXSmenu').css('display','none');
            }
        });

    });

</script>