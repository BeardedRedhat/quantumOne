<?php
// Functions taken from stack overflow

// Prevents Cross-site scripting, restores added slashes and strips HTML tags
// For text inputs
function txtSafe($val) {
    htmlentities(trim(strip_tags(stripslashes($val))), ENT_NOQUOTES, "UTF-8");
    return $val;
}

// Restores added slashes and encoded HTML tags
// For text areas
function txtAreaSafe($val) {
    return htmlentities(trim(stripslashes($val)), ENT_NOQUOTES, "UTF-8");
}
