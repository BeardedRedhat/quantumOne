<?php

$activeNav = "Home";
require "_shared/header.php";
?>
<style>
    body {

    }

    .background-img {
        background-image: url(_assets/img/landscape_bkg.jpg);
        -webkit-background-size: cover;
        -moz-background-size: cover;
        -o-background-size: cover;
        background-size: cover;
    }

    .home-section {
        /*border:1px dashed red;*/
        display: block;
        width:100%;
        /*padding: 25px 25px 25px 25px;*/
        height:800px;
        position:relative;
        z-index:0;
    }
    div.tint {
        width:100%;
        height:100%;
        opacity:.7;
        color:#f0f0f0;
        z-index:1;
        background: #2b3f54; /* For browsers that don't support gradients */
        background: -webkit-linear-gradient(left,black,#202e3e,black);
        background: -o-linear-gradient(left,black,#202e3e,black);
        background: -moz-linear-gradient(left,black,#202e3e,black);
        background: linear-gradient(to right, black, #202e3e,black);
    }


    /** Login/Register buttons **/
    .divLoginReg a {
        padding:20px 10px 10px 10px;
        background-color: inherit !important;
        color:white;
        border:none;
        border-bottom:1px solid white;
    }
    .divLoginReg a:hover {
        color:#d95557;
        border-bottom: 1px solid #d95557;
    }

    /** First section **/
    #sectionOne {
        background-image: url(_assets/img/pexels-4.jpeg);
        -webkit-background-size: cover;
        -moz-background-size: cover;
        -o-background-size: cover;
        background-size: cover;
    }

    .div-sectionone {
        height:140px;
        padding:0 !important;
        margin-bottom:30px;
        /*box-shadow: 0 0 2cm rgba(0,0,0,0.5);*/
    }
    div.home-icon-left {
        height:100%;
        display:inline-block;
        padding:0;
        text-align:center;
    }
    div.home-icon-left > .icon {
        height:100%;
        width:140px;
        margin:0 auto;
        border-radius:200px;
        padding-top:15px;
        background-color: white;
        box-shadow: -8px 10px 5px rgba(0,0,0,.5);
    }
    div.home-icon-left span {
        font-size:100px;
        color:black;
    }

    div.home-content-left {
        height:100%;
        font-size:24px;
        padding-top:30px;
    }


</style>

<div class="background-img"></div>

<div class="home-section" id="sectionOne">
    <div class="tint">
        <div class="container-fluid" style="z-index:2;">

            <div class="row-fluid">
                <div class="col-xs-12 divLoginReg">
                    <div class="pull-right">
                        <a href="login.php" class="btn btn-default">Login</a>&nbsp;&nbsp;
                        <a href="register.php" class="btn btn-default">Register</a>
                    </div>
                </div>
            </div>

            <div class="clearfix"></div>

            <div class="row-fluid">
                <div class="title col-xs-12 font-comforta" style="text-align:center; font-size:55px; opacity:1; padding-top:55px; text-shadow:5px 5px 5px black;">
                    Quantum<span style="color:#d95557;">One</span>
                </div>
            </div>

            <div class="clearfix"></div>

            <div class="row-fluid">
                <div class="col-xs-12 font-comforta" align="center" style="font-size:15px">Your Student Friendly Finance Manager</div>
            </div>

            <div class="clearfix"></div>

            <div class="row-fluid" align="center">
<!--                <br /><br />*Still under development*-->
            </div>
        </div><!--container-->
    </div><!--tint-->
</div><!--section-->

<div class="home-section">

</div>

</body>
</html>
