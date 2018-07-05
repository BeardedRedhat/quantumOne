<?php

// create session class
// Taken from a previous group project on placement

class Session
{
    public static function start() {
        if(!Session::isActive()) {
            session_start();
        }
    }


    public static function isActive() {
        if(version_compare(phpversion(), '5.4.0', '>=')) {
            return session_status() === PHP_SESSION_ACTIVE;
        } else {
            return session_id() === '' ? false : true;
        }
    }


    public static function check($check='userID', $redirectTo='../login.php') {
        if(!Session::keyExists($check)) {
            header('location: ' . $redirectTo);
            die();
        }
    }


    public static function get($key) {
        //Make sure there is an active session.
        Session::start();
        //If no value exists for the specified index then throw an exception.
        if(!isset($_SESSION[$key])) {
            throw new Exception('No value has been set for the key ' . $key . ' in session.');
        }

        //return the value for the given key.
        return $_SESSION[$key];
    }


    public static function set($name, $value) {
        Session::start();
        $_SESSION[$name] = $value;
    }


    public static function keyExists($key) {
        Session::start();
        return isset($_SESSION[$key]);
    }


    public static function adminCheck() {
        if($_SESSION['adminCheck'] == null) {
            Session::end();
            header('location:../login.php');
            die();
        }

        if($_SESSION['adminCheck'] !== 'YWUyc3VFK0t5QjRhNXVQOUZBdWdSUT09') {
            Session::end();
            header('location:../login.php');
            die();
        }
    }


    public static function uuCheck() {
        if(!Session::keyExists('uuCheck')) {
            return false;
        } else {
            return true;
        }
    }

    public static function end() {
        if(!Session::isActive()) {
            Session::start();
        }
        $_SESSION = array();

        session_destroy();
        setcookie(session_name(), '', time()-3600,'/', '', 0, 0);
        return true;
    }
}