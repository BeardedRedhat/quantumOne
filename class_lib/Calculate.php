<?php

require_once "Database.php";


class Calculate
{
    private $userID;
    private $conn;

    public function __construct() {
        $db = New Database();
        $conn = $db->openConnection();

        $this->conn = $conn;
        $this->userID = $_SESSION['userID'];
    }

    private function query($sql) {
        $stmt = $this->conn->prepare($sql);
        return $stmt;
    }

    // Returns number of decimal places of value
    public static function decimalPlaces($val) {
        return strlen(substr(strrchr($val, "."), 1));
    }

    //Calculate percentage variance between 2 values. Returns variance to 1 decimal place with up/down arrow
    public static function pcentVariance($currentVal, $comparisonVal, bool $navbar=false)
    {
//        if (!preg_match('/^[0-9]*$/', $currentVal) && !preg_match('/^[0-9]*$/', $comparisonVal)) {
//            return "Calculate::pcentVariance - values must be numeric";
//        } else {
            if($currentVal == null || $comparisonVal == null) {
                return 0;
            } else {
                settype($currentVal, "double");
                settype($comparisonVal, "double");

                $diff = $currentVal - $comparisonVal;

                if($navbar == true) {
                    $incIcon = "<span class=\"fa fa-caret-up\" aria-hidden=\"true\" style=\"color:green;\"></span>";
                    $decIcon = "<span class=\"fa fa-caret-down\" aria-hidden=\"true\" style=\"color:red;\"></span>";
                    $variance = ($diff < 0 ? $decIcon . " " : $incIcon . " +") . number_format(($diff / $comparisonVal) * 100, 1) . "%";

                } else {
                    $variance = ($diff / $comparisonVal) * 100;
                    return $variance;
                }
                return $variance;
            }
//        }
    }


    // Calculates the standard deviation of an array of values - used for the cash flow forecast
    public static function standardDeviation(array $a) {
        $n = count($a);
        if ($n === 0) {
            trigger_error("Standard deviation array has no elements", E_USER_WARNING);
            return false;
        }
        $mean  = array_sum($a) / $n;
        $carry = 0.0;
        foreach ($a as $val) {
            $d = ((double) $val) - $mean;
            $carry += $d * $d;
        }
        return round(sqrt($carry / $n),2);
    }


    // Calculates the mean of an array of values
    public static function mean($array) {
        if(!is_array($array)) {
            trigger_error("Invalid parameter given on mean calculation", E_USER_WARNING);
            return false;
        }
        $n = count($array);
        if($n < 1) {
            trigger_error("No values to calculate average", E_USER_WARNING);
            return false;
        }
        $mean = array_sum($array) / $n;
        return round($mean,2);
    }


    // Works out the difference between two dates and returns the numerical value in specified format
    // Default format is number of days (%a)
    // Other formats: %m => months, %y => years
    public static function dateDifference($date, $compDate, $format="%a") {
        $transactionDate = date_create($date);
        $todaysDate      = date_create($compDate);
        $difference      = date_diff($transactionDate, $todaysDate);

        // Bug fix for months not recognising the year - if it was one year and one month, it returns 1
        // Takes the difference in years & multiplies by 12, then adds original difference
        if($format == "%m") {
            $check = $difference->format("%y");
            if($check > 0) {
                return ($check*12)+$difference->m;
            }
        }
        return $difference->format($format);
    }


    // Subtracts given number of days/months/years from given date
    // Used for recurring transactions to calculate transaction date before db insertion
    public static function subtractDate($originalDate, $interval, $type="days", $format="Y-m-d") {
        $date = date_create($originalDate);
        date_sub($date, date_interval_create_from_date_string($interval.' '.$type));
        return date_format($date, $format);
    }


    // Returns the overall net worth for the user - excludes the credit accounts (and liabilities if included)
    public function netWorth() {
        try {
            $stmt = $this->query("SELECT 
                            SUM(CASE WHEN accountTypeID IN (1,2,4) THEN accountBalance ELSE 0 END) AS totalIncome,
                            SUM(CASE WHEN accountTypeID IN (3,5) THEN accountBalance ELSE 0 END) AS totalExpenditure
                          FROM accounts 
                          WHERE userID = :userID
                          AND incNet = 1");
            $stmt -> bindColumn('totalIncome', $ttlIncome);
            $stmt -> bindColumn('totalExpenditure', $ttlExpenditure);
            $stmt -> execute(array(':userID'=>$this->userID));
            $stmt -> fetch();
            $stmt = null;
            return $ttlIncome - $ttlExpenditure;
        } catch(PDOException $err) {
            AuditLog::add("Calculate net worth failed - ".$err->getMessage());
            return 0;
        }
    }


    // Calculates the % variance next to net worth on navbar
    // Calculated just from the previous month's total transactions against this month
    public function netWorthVariance() {
        try {
            $stmt = $this->query("SELECT 
                                  SUM(CASE WHEN transactionType = 'income' THEN transactionAmount ELSE NULL END) as income,
                                  SUM(CASE WHEN transactionType = 'expense' THEN transactionAmount ELSE NULL END) as expenses
                                FROM transactions
                                WHERE 
                                  YEAR(transactionDate) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)
                                  AND MONTH(transactionDate) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
                                  AND userID = :userID
                                ORDER BY transactionID DESC");
            $stmt->bindColumn('income', $oldInc);
            $stmt->bindColumn('expenses', $oldExp);
            $stmt->execute(array(':userID'=>$this->userID));
            $stmt->fetch();
            $stmt = null;

            $stmt = $this->query("SELECT 
                                    SUM(CASE WHEN transactionType = 'income' THEN transactionAmount ELSE NULL END) as income,
                                    SUM(CASE WHEN transactionType = 'expense' THEN transactionAmount ELSE NULL END) as expenses
                                  FROM transactions
                                  WHERE 
                                    YEAR(transactionDate) = YEAR(CURRENT_DATE)
                                    AND MONTH(transactionDate) = MONTH(CURRENT_DATE)
                                    AND userID = :userID
                                  ORDER BY transactionID DESC");
            $stmt->bindColumn('income',$currInc);
            $stmt->bindColumn('expenses', $currExp);
            $stmt->execute(array(':userID'=>$this->userID));
            $stmt->fetch();
            $stmt = null;

            $lastMonthVariance = $oldInc + $oldExp;
            $thisMonthVariance = $currInc + $currExp;

            $variance = Calculate::pcentVariance($thisMonthVariance,$lastMonthVariance,true);
            return $variance;
        } catch(PDOException $err) {
            AuditLog::add("Net worth variance function failed - ".$err->getMessage());
            return 0;
        }
    }


    public function destruct()
    {
        $this->userID = null;
        $this->conn = null;
    }


}