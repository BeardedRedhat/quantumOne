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
setlocale(LC_MONETARY, $_SESSION['locale']);
$userID = $_SESSION['userID'];

//***** scope creep *****//

$db = null;
$conn = null;
$activeNav = "transactions";
require "../_shared/header.php";
require "../navigation/sideNav.php"; ?>

<style>
    input,select,textarea { font-size:12px !important; }

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

</style>

<div class="main">
    <?php require "../navigation/navbar.php"; ?>

    <div class="container-fluid font-opensans">

        <div class="col-xs-12 page-title">
            <span>Edit Transaction</span>
        </div>

        <div class="divBuffer">
            <span class="fa fa-spinner fa-pulse fa-2x fa-fw"></span>
            <span class="sr-only">Loading...</span><br />
            <span>Loading...</span>
        </div>

        <div class="col-xs-12"><?php if(isset($error)) { echo $error; } ?></div>

        <div class="row-fluid">

        </div>


    </div><!-- container-fluid -->
</div><!-- main -->

<script>

    function spinner(view) {
        if(view === "show") {
            $(".divBuffer").css('display','block');
        } else if(view === "hide") {
            $(".divBuffer").css('display','none');
        }
    }

    $(document).ready(function() {

    });
</script>

</body>
</html>
