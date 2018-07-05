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
require_once('../../class_lib/Table.php');

Session::check();
$db = new Database();
$conn = $db -> openConnection();
$user_currency_label = $_SESSION['currencyLabel'];
setlocale(LC_MONETARY, $_SESSION['locale']);
$userID = $_SESSION['userID'];

// query to fetch all transactions
$query = "SELECT 
            transactions.transactionID, 
            accounts.accountName as Account,
            categories.catName as Category,
            transactions.transactionDate as `Date`,
            transactions.transactionType as `Type`,
            transactions.transactionAmount as Amount,
            transactions.receiptNo as ReceiptNo
          FROM transactions
          LEFT JOIN accounts ON accounts.accountID = transactions.accountID
          LEFT JOIN categories ON categories.categoryID = transactions.categoryID
          WHERE transactions.userID = $userID
          ORDER BY transactionDate DESC, transactionID DESC";


$settings = '{
		"tableName" : "tblTransactions",
		"showHeader" : true,
		"paging" : true,
		"pageSize" : 15,
		"sorting" : true,
		"rowURL" : "edit.php",
		"rowURLQS" : { "id" : "transactionID" },
		"columns" : 
		[
		    "Account",
			"Category", 
			"Date",
			"Type",
			"Amount",
			"ReceiptNo"
		]
	}';

//render paginated table using table class
$table = Table::render($query, $settings);

$db = null;
$conn = null;
$activeNav = "transactions";
require "../_shared/header.php";
require "../navigation/sideNav.php"; ?>

<div class="main">
    <?php require "../navigation/navbar.php"; ?>

    <div class="container-fluid font-opensans" style="margin-top:1em;">
        <div class="col-xs-12">
            <span style="font-size:13px"><a href="index.php"><span class="fa fa-chevron-left" aria-hidden="true"></span> Back</a></span>
        </div>
        <div class="col-xs-12 page-title">

            <span>All Transactions</span>
        </div>

        <div class="col-xs-12">
            <?=$table?>
        </div>

    </div>
</div>

</body>
</html>
