<?php



class Text
{
    // splits the likes of FirstNames into First Names
    // Function was written by ISArc team as part of the Table class
    public static function splitPascalCase($word){
        $newWord = "";
        $length = strlen($word);

        //Anything in this array will cause a capital letter not to have a space before it if this is the character before it ie hyphenated words (Non-Split)
        $specChar = array('-');

        for ($i=0; $i<$length; $i++) {
            if($newWord != ""){
                if(in_array($word[$i -1], $specChar)){
                    $newWord .= $word[$i];
                }
                elseif($i < ($length -1) && ctype_upper($word[$i]) && ctype_lower($word[$i +1])){
                    $newWord .= " ".$word[$i];
                }
                elseif(ctype_upper($word[$i]) && ctype_lower($word[$i -1])){
                    $newWord .= " ".$word[$i];
                }
                else{
                    $newWord .= $word[$i];
                }
            }
            else{
                $newWord .= $word[$i];
            }
        }
        return $newWord;
    }


    // Checks if the string is a valid date, and follows the YYYY-MM-DD format
    public static function isValidDate($date) {
        if(strtotime($date) === false) {
            return false;
        }
        list($year, $month, $day) = explode('-', $date);
        return checkdate($month, $day, $year);
    }


    // Returns specified element of array (n), default is first element
    public static function getNthArrayElement($array, $n=1) {
        if($n == 1) {
            foreach($array as $val) {
                return $val;
            }
        } else {
            $x = 0;
            foreach($array as $val) {
                $x++;
                if($x == $n) {
                    return $val;
                }
            }
        }
        return null;
    }


    // Splits email into 2 sections - the local recipient and the domain
    public static function splitEmail($email) {
        $local  = substr($email, 0, strpos($email, '@'));
        $domain = substr($email, strpos($email, '@') + 1);
        return array($local, $domain);
    }


    // Returns an array with all months of the year in format YYYY-MM
    // If reverse is true then the earliest date will appear first in the array
    public static function getYearDates(bool $reverse=false, $format="Y-m") {
        $dates = array();
        for($i=0; $i<13; $i++) {
            $dates[] = substr(Calculate::subtractDate(date("Y-m"), $i, "months", $format), 0,7);
        }
        return ($reverse===false ? $dates : array_reverse($dates));
    }


    // Returns array with all days of the current month in format DD-MM-YYYY
    public static function getMonthDates(bool $reverse=false, $format="d-m-Y", $lastDay=false) {
        $dates = array();
        // If a last day has been set as a parameter, use it instead of last day of the current month
        $lastDay = ($lastDay===false ? substr(date("Y-m-t"), -2) : $lastDay);
        for($i=0;$i<$lastDay; $i++) {
            $dates[] = Calculate::subtractDate(date("01-m-Y"), -$i, "days", $format);
        }
        return ($reverse === false ? $dates : array_reverse($dates));
    }
}