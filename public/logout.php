<?php

require_once('../class_lib/Session.php');
Session::end();
header('Location:login.php');
