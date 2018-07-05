<?php


class Form
{
    // Generates a unique token for each form to prevent third-party scripting
    public static function generateFormToken($form) {
        $token = md5(uniqid(microtime(), true));
        Session::set($form.'_token', $token);
        return $token;
    }

    // Verifies form token against session token, returns false otherwise
    public static function verifyFormToken($form) {
        if(!Session::keyExists($form.'_token'))
            return false;

        if(!isset($_POST['token']))
            return false;

        if($_SESSION[$form.'_token'] !== $_POST['token'])
            return false;

        return true;
    }

    public static function error_alert($msg) {
        return "<div class=\"alert alert-danger alert-dismissible\" role=\"alert\">
                   <button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button>
                   <strong>Oops!</strong> $msg
                </div>";
    }

    public static function error_msg($msg) {
        return "<span style=\"color:red; margin-top:1em;\"><span class=\"fa fa-exclamation-circle\">&nbsp;</span>".$msg."</span>";
    }

    public static function success_alert($msg) {
        return "<div class=\"alert alert-success alert-dismissible\" role=\"alert\">
                   <button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button>
                   <strong><span class=\"fa fa-check\">&nbsp;</span></strong> $msg
                </div>";
    }

    public static function success_msg($msg) {
        return "<span style=\"color:green; margin-top:1em;\"><span class=\"fa fa-check\">&nbsp;</span>".$msg."</span>";
    }

}