<?php
require_once('../../class_lib/Account.php');
require_once('../../class_lib/AuditLog.php');
require_once('../../class_lib/Crypt.php');
require_once('../../class_lib/Session.php');

Session::check();
setlocale(LC_MONETARY, $_SESSION['locale']);
$userID = $_SESSION['userID'];

$nav_account  = New accounts();
$nav_accounts = $nav_account->listAccountsWithTypes();
$nav_account  = null;

// Checking admin access to display or hide 'Admin' menu tab
$isAdmin = false;
if(Session::keyExists('adminCheck')) {
    if($_SESSION['adminCheck'] == "YWUyc3VFK0t5QjRhNXVQOUZBdWdSUT09")
        $isAdmin = true;
} ?>

<style>
    .divLogo {
        display:inline-block;
        width:100%;
        height:50px;
        font-size: 25px;
        /*font-weight:900;*/
        font-family: Comfortaa;
        color: white;
        padding: 10px 0 0 0;
        text-align:center;
    }
    .sideNav { font-size:14px; }
    a.list-group-item:focus { background-color: inherit; }
</style>

<div class="sideNav">
    <div class="list-group">

        <div class="divLogo">Quantum<span style="color:#d95557;">One</span></div>
        <div class="logo-divider-hz"></div>

        <a href="/dashboard/index.php" class="list-group-item <?=$activeNav == "dashboard" ? "active" : ""?>" style="<?=$activeNav == "dashboard" ? "border-left:3px solid #5ab165 !important;" : ""?>">
            <span class="fa fa-tachometer" aria-hidden="true" style="<?=$activeNav == "dashboard" ? "color: #5ab165;" : ""?>"></span> Dashboard
        </a>
        <a href="/transactions/index.php" class="list-group-item <?=$activeNav == "transactions" ? "active" : ""?>" style="<?=$activeNav == "transactions" ? "border-left:3px solid #377bb5 !important;" : ""?>">
            <span class="fa fa-exchange" aria-hidden="true" style="<?=$activeNav == "transactions" ? "color: #377bb5;" : ""?>"></span> Transactions
        </a>
        <a href="/budgets/index.php" class="list-group-item <?=$activeNav == "budgets" ? "active" : ""?>" style="<?=$activeNav == "budgets" ? "border-left:3px solid #eed765;" : ""?>">
            <span class="fa fa-money" aria-hidden="true" style="<?=$activeNav == "budgets" ? "color: #eed765;" : ""?>"></span> Budgets
        </a>
        <a href="/stats/index.php" class="list-group-item <?=$activeNav == "stats" ? "active" : ""?>" style="<?=$activeNav == "stats" ? "border-left:3px solid #d95557;" : ""?>">
            <span class="fa fa-bar-chart" aria-hidden="true" style="<?=$activeNav == "stats" ? "color: #d95557;" : ""?>"></span> Stats
        </a>
        <?php if($isAdmin) { ?>
            <a href="/admin/index.php" class="list-group-item <?=$activeNav == "admin" ? "active" : ""?>" style="<?=$activeNav == "admin" ? "border-left:3px solid #fdfffd" : ""?>">
                <span class="fa fa-user-circle-o" aria-hidden="true" style="padding-left:1px;"></span> Admin
            </a>
        <?php } ?>


        <div class="list-group-item noHover" style="text-align:center;">ACCOUNTS</div>
        <?php foreach($nav_accounts as $accountType => $accounts_of_type): ?>
            <div class="divAccounts">
                <a href="#" class="list-group-item navDropdwn">
                    <?= $accountType; ?>
                    <div class="divChevron"><span class="fa fa-chevron-down" aria-hidden="true"></span></div>
                </a>

                <?php foreach($accounts_of_type as $aot): ?>
                    <a href="/manage/manageAccounts.php?id=<?=Crypt::encrypt($aot['accountID'])?>" class="list-group-item navDropdwnHidden" title="View <?=$aot['accountName']?>"><?= $aot['accountName'] ?>
                        <span class="badge noFont"><?=money_format('%n', $aot['accountBalance'])?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {

        if($("list-group-item", this).hasClass("active")) {
            $("list-group-item", this).css("margin-left", "12px");
        }

        $('.navDropdwn', this).click(function() {
            if ($("span.fa", this).hasClass("fa-chevron-down")) {
                $("span.fa", this).removeClass("fa-chevron-down").addClass("fa-chevron-up")
            } else if ($("span.fa", this).hasClass("fa-chevron-up")) {
                $("span.fa", this).removeClass("fa-chevron-up").addClass("fa-chevron-down")
            }
            if($('~ .navDropdwnHidden', this).css('display') == 'none') {
                $('~ .navDropdwnHidden', this).css('display','block');
            } else {
                $('~ .navDropdwnHidden', this).css('display','none');
            }
        });
    });
</script>