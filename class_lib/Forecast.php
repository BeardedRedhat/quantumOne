<?php
require_once("Text.php");
require_once("Calculate.php");
require_once("Account.php");

/**
 * Cash flow forecast based on individual accounts accounts
 *
 * Initial plans to incorporate category usage/statistics scrapped due to time constraints
 * Now only takes into account upcoming/recurring transactions, av
 *
 */


class Forecast
{
    private $conn;
    private $userID;
    private $accountID;
    public  $monthProgress; // % value of how far through the month the current date is.
    public  $currentBalance = 0.00;

    public  $error;
    private $result;

    // Stores all transactions on account in a 3D array, sorted by month DESC
    // "2018-03" => array( transactionID => array(transaction details from query) )
    public $transactions = array();
    // Total number of transactions for account - used for mean calculations.
    private $transactionCounter = 0;
    // Transactions array filtered to exclude any recurred payments - identical format
    public $nonRecurTransactions;

    // Holds end of month balances in format 'date'=>'balance', e.g. "2018-01"=>1242.56
    public $endOfMonthAccountBalances = array();
    // Holds the mean and standard deviation for the account: "mean"=>1302.12, "stdDev"=>234.32.
    public $accountStats = array();

    // Array holding total income/expenses for the account, plus the average inc/exp
    public $incomeExpenses = array();
    // Total upcoming transactions
    public $upcomingPayments = 0;

    // Holds an array of all user categories: ID => category name.
    public $categories = array();
    // Holds an array with the month date as key, and an array of the total category balances for each month.
    public $categorySpending = array();
    // A 2D array holding the category ID as key, and an array holding the total balance for each month it was used: cat ID => array("YYYY-MM" => 132.23).
    public $categoryBalances = array();
    // Parallel 2D array to categoryBalances, except holding the total uses (in each month) for each category, in the format: cat ID => array("YYYY-MM" => 5)
    // Both arrays together are used to calculate the mean for the category, i.e. dividing the overall balance.
    public $categoryUses = array();
    // Holds the mean and standard deviation for all categories: catID => array("mean"=>302.12, "stdDev"=>34.32, "avgUsage"=>4).
    public $categoryStats = array();


    public function __construct($conn,$accountID) {
        $this->conn = $conn;
        $this->userID = $_SESSION['userID'];
        $this->accountID = $accountID;

        $this->currentBalance = $this->currentBalance();
        $this->monthProgress  = $this->monthProgress();
        $this->result = $this->getAccountTransactionStats();
    }


    private function query($sql) {
        $stmt = $this->conn->prepare($sql);
        return $stmt;
    }


    // Returns the progress through the month, e.g. if the date was 15th Sep, then it would be 0.50 (50%)
    private function monthProgress() {
        $now = date("d");
        $d   = date("t", strtotime($now)); // Gets the number of days from current month
        $monthProg = ($now / $d); // Percent value of progress through month - used for
        $this->monthProgress = $monthProg;
        return $monthProg;
    }


    // Assigns the current account balance to obj property
    private function currentBalance() {
        try {
            $stmt = $this->query("SELECT accountBalance as balance FROM accounts WHERE accountID=:accountID");
            $stmt->bindColumn('balance', $balance);
            $stmt->execute(array(':accountID'=>$this->accountID));
            $stmt->fetch();
            $stmt = null;
            $this->currentBalance = $balance;
            return $balance;
        } catch(PDOException $err) {
            AuditLog::add("Get current balance failed on forecast obj - ".$err->getMessage());
            return "Looks like an internal server problem. If problem persists, contact administrator..";
        }
    }


    private function countIterations($itvl, $format) {
        $counter = 0;
        if($itvl == 1 || ($format==52 && $itvl==7)) {
            return 1;
        } else {
            for($i=1;$i<=$itvl;$i++) {
                if($format==52) {
                    if($i % 7 == 0) {
                        $counter++;
                    }
                } else {
                    $counter++;
                }
            }
            return $counter;
        }
    }


    // Calcuates the total amount to be added/taken away from account balance
    public function getUpcomingPayments() {
        $recur    = array(); // Recurring transactions with ID as key, and amount & frequency as value, e.g. 1=>array(12.50, 365)
        $upcoming = array(); // Holds any upcoming recurring payments

        try {
            $stmt = $this->query("SELECT ID,amount,frequency,startDate,endDate
                              FROM recurTransactions 
                              WHERE userID = :userID AND (endDate >= CURRENT_DATE() OR indefinite = 1) AND inactive = 0 AND accountID=$this->accountID");
            $stmt->execute(array(':userID'=>$this->userID));
            if($stmt->rowCount() > 0) {
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    $recur[$row['ID']] = array($row['amount'], $row['frequency'], $row['startDate'], $row['endDate']);
                }
                $stmt = null;

                $stmt = $this->query("SELECT MAX(transactionDate) as lastTrans FROM transactions WHERE recurID=:recurID AND userID=:userID ORDER BY transactionDate DESC LIMIT 1");
                foreach($recur as $recurID => $trans) {
                    $stmt->execute(array(':recurID'=>$recurID, ':userID'=>$this->userID)); // Execute the query to fetch last recur transaction

                    $lastTrans = $stmt->fetch()[0]; // Most recent recur transaction date
                    $endOfMth  = date("Y-m-t"); // Last date of the month

                    // if no prev transaction exists, use recur start date as last transaction date
                    if($lastTrans == null)
                        $lastTrans = $trans[2];

                    // If the transaction end date is before the end of the month, use end date instead
                    if($trans[3] !== null) {
                        if(strtotime($trans[3]) < strtotime($endOfMth)) {
                            $endOfMth = $trans[3];
                        }
                    }
                    // Format for dateDifference function, days %a, months %m or years %y
                    $format   = $trans[1] == 1 ? "%y" : ($trans[1] == 12 ? "%m" : "%a");
                    $interval = Calculate::dateDifference(date($lastTrans), $endOfMth, $format);

                    // if frequency is weekly, count how many weeks of transactions
                    if($trans[1] == 52)
                        $interval = $this->countIterations($interval,$trans[1]);

                    // Multiply the date difference with transaction amount and push to array, e.g. £4.50 for 5 weeks = £22.50
                    $upcoming[] = ($interval * $trans[0]);

                }
                $this->upcomingPayments = array_sum($upcoming); // Assign property value
                return array_sum($upcoming); // Return total amount to be added/taken away for the month

            } else {
                return 0;
            }
        } catch(PDOException $err) {
            AuditLog::add("Forecast upcoming payments - ".$err->getMessage());
            $this->error = "Upcoming payments calculation error: 1052.";
            return false;
        }
    }


    // Populates transactions property and calculates the mean and standard deviation for the account balances
    public function getAccountTransactionStats() {
        $transactions = array();
        try {
            $stmt = $this->query("SELECT transactionID,transactions.accountID,transactions.categoryID,
                                         transactionDate,balance,transactionAmount,recurID,accounts.accountName
                                  FROM transactions 
                                  LEFT JOIN accounts ON accounts.accountID=transactions.accountID
                                  WHERE transactions.userID=:userID AND transactions.accountID=:accountID
                                  ORDER BY transactionDate DESC, transactionID DESC");
            $stmt->execute(array(':userID'=>$this->userID, ':accountID'=>$this->accountID));
            if($stmt->rowCount() > 0) {

                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    $this->transactionCounter++; // Increment number of transactions
                    $transactions[$row['transactionID']] = array(
                        "catID"       => $row['categoryID'],
                        "date"        => $row['transactionDate'],
                        "balance"     => $row['balance'],
                        "amount"      => $row['transactionAmount'],
                        "recurID"     => $row['recurID'],
                        "accountID"   => $row['accountID'],
                        "accName"     => $row['accountName']);
                }
                $stmt = null;

                $temp = array();    // Temporary array used to hold all transactions within month
                $loopPrevDate = 0;
                $loopCurrentDate = 0;

                // Used to detect end of foreach
                $i=0;
                $len=count($transactions);

                // Creates 3D array of the transactions for the specified account sorted by month (DESC)
                // Updates account balances - pushed onto endOfMonthBalances property
                foreach($transactions as $id => $info) {
                    $i++; // Increment counter
                    $currentDate = substr($info['date'],0,7); // Get the date of current transaction - YYYY-MM

                    if($loopCurrentDate === 0) // If its the first iteration, set current loop date
                        $loopCurrentDate = $currentDate;

                    if($currentDate == $loopCurrentDate) {
                        $temp[$id] = $info;

                        if($i == $len) { // if its the last transaction, add to transactions and endOfMonthAccountBalances arrays with current month
                            $this->endOfMonthAccountBalances[$loopCurrentDate] = Text::getNthArrayElement($temp)["balance"];
                            $this->transactions[$loopCurrentDate] = $temp;
                        }
                    } else if ($currentDate !== $loopCurrentDate || $currentDate == $loopPrevDate) {
                        $loopPrevDate = $loopCurrentDate;
                        $loopCurrentDate = $currentDate;

                        // Get the end of month balance (first array element, i.e. most recent transaction in the month) and push onto array with the date as key
                        $this->endOfMonthAccountBalances[$loopPrevDate] = Text::getNthArrayElement($temp)["balance"];

                        // Insert transactions for the month into array, with the date YYYY-MM as the key
                        // Empty the temp array and populate with new transaction of the month
                        $this->transactions[$loopPrevDate] = $temp;
                        unset($temp);
                        $temp[$id] = $info;
                    }
                }

                if(count($this->endOfMonthAccountBalances) < 1) {
                    AuditLog::add("Forecast failed - insufficient transaction data.");
                    return "Not enough data to calculate forecast. At least 1 month of transactions is needed.";
                }

                $this->accountStats["mean"]   = Calculate::mean($this->endOfMonthAccountBalances);
                $this->accountStats["stdDev"] = Calculate::standardDeviation($this->endOfMonthAccountBalances);

                return true;

            } else {
                $stmt = null;
                AuditLog::add("Forecast failed - no transactions found.");
                return "Cannot calculate forecast - No transactions found on this account.";
            }
        } catch(PDOException $err) {
            AuditLog::add("Forecast failed - getMonthlyBalances error: ".$err->getMessage());
            return "Looks like an internal server problem. If problem persists, contact administrator..";
        }
    }


    // **** Now removed from calculation due to time constraints ****
    public function getCategoryStats() {
        try {
            $stmt = $this->query("SELECT categoryID, catName FROM categories WHERE userID=:userID");
            $stmt->execute(array(':userID'=>$this->userID));
            if($stmt->rowCount() > 0) {
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    $this->categories[$row['categoryID']] = $row['catName'];
                }
            } else {
                AuditLog::add("No categories found on fething category stats on forecast.");
                return "No categories found on forecast.";
            }
            $stmt = null;

            // Temporary variables
            $tempTotal = 0.00;
            $catUses   = 0;
            $temp      = array();

            // Iterate through each month in transactions array
            foreach($this->transactions as $date => $transactions) {
                // Iterate through each user category
                foreach($this->categories as $catID => $name) {
                    // Iterate through each transaction in the current month
                    foreach($transactions as $transID => $info) {
                        // If the category ID of transaction matches the loop category, add transaction amount into temp total
                        if($info['catID'] == $catID) {
                            $catUses++;
                            $tempTotal += $info['amount'];
                        }
                    }

                    $temp[$catID] = array(
                        "total" => $tempTotal,
                        "uses"  => $catUses
                    );

                    $tempTotal = 0.00;
                    $catUses   = 0;
                }
                // Update category array with date as key, and array of category totals as value. Clear temp array for next iteration
                $this->categorySpending[$date] = $temp;
                unset($temp);
            }


            // Iterate through each user category as cat ID => cat name
            foreach($this->categories as $categoryID => $catName) {
                // Iterate through each month => categories used during month (total and uses)
                foreach($this->categorySpending as $month => $allCats) {
                    // Iterate through each category in the month as catID => values (total & uses)
                    foreach($allCats as $checkID => $values) {
                        // If category ID equals cat ID in
                        if($categoryID == $checkID) {
                            $this->categoryBalances[$categoryID][$month] = $values['total'];
                            $this->categoryUses[$categoryID][$month] = $values['uses'];
                        }
                    }
                }
                if(array_key_exists($categoryID,$this->categoryBalances)) {
                    $meanBalance = Calculate::mean($this->categoryBalances[$categoryID]);
                    $meanUsage   = Calculate::mean($this->categoryUses[$categoryID]);
                    settype($meanBalance,"double");
                    settype($meanUsage,"double");

                    if($meanBalance !== 0.00) {
                        $this->categoryStats[$categoryID]['mean']     = $meanBalance / $meanUsage;
                        $this->categoryStats[$categoryID]['stdDev']   = Calculate::standardDeviation($this->categoryBalances[$categoryID]) / $meanUsage;
                        $this->categoryStats[$categoryID]['avgUsage'] = Calculate::mean($this->categoryUses[$categoryID]);
                    } else {
                        $this->categoryStats[$categoryID]["mean"]     = 0.00;
                        $this->categoryStats[$categoryID]['stdDev']   = 0.00;
                        $this->categoryStats[$categoryID]['avgUsage'] = 0;
                    }
                }
            }

            return true;

        } catch(PDOException $err) {
            AuditLog::add("getCategories function failed - ".$err->getMessage());
            return "Looks like an internal server problem. If problem persists, contact administrator..";
        }
    }


    // Filters the transactions array to remove any transactions that are recurring
    // Used for calculating the average income/expenditure after upcoming payments are subtracted from equation
    public function sortNonRecurTransactions() {
        $sorted = $this->transactions;
        foreach($sorted as $month => $mthTransactions) {
            foreach($mthTransactions as $transID => $values) {
                if($values['recurID'] !== null || !empty($values['recurID'])) {
                    unset($sorted[$month][$transID]); // Remove transaction if a recur ID is set
                }
            }
        }
        $this->nonRecurTransactions = $sorted;
        return $sorted;
    }


    // returns the average income and expenses for the specific account
    private function getAverageIncExp() {
        $account   = New accounts();
        $accIncExp = $account->activity($this->accountID); // Returns array containing monthly income/expenses excluding upcoming recur transactions
        $account   = null;

        if(!empty($accIncExp)) {
            $ttlIncome   = array();
            $ttlExpenses = array();

            foreach($accIncExp as $month => $values) {
                array_push($ttlIncome, $values['inc']);
                array_push($ttlExpenses, $values['exp']);
            }
            // If there are less than 2 values, mean cannot be calculated
            if(count($ttlIncome) < 2 || count($ttlExpenses) < 2) {
                AuditLog::add("Forecast failed - insufficient account data to get average income/expenditure");
                return "Not enough account history to calculate forecast.";
            }
            // Set the average income and expenses or the account
            return $avgIncomeExpenses = array("inc"=>Calculate::mean($ttlIncome), "exp"=>Calculate::mean($ttlExpenses));
        } else {
            // if activity() returns nothing
            AuditLog::add("Forecast failed - insufficient account data to get average income/expenditure");
            return "Not enough account history to calculate forecast.";
        }
    }


    // Function to calculate the forecast
    public function calculate() {
        if($this->result !== true) {
            AuditLog::add("Forecast failed - No transactions yet created");
            return $this->result;
        }

        $curBalance = $this->currentBalance;      // gets the current account balance
        $upcoming   = $this->getUpcomingPayments(); // Gets total value of upcoming payments
        $avgIncExp  = $this->getAverageIncExp();    // Fetches average income and expenses for the account

        // if there is enough transaction history to continue calculation
        if(is_array($avgIncExp)) {
            // gets actual average based on the remainder of the month, e.g. if average income is 500 and month is 75% complete, then 125 is the actual average
            $monthRemaining = 1-$this->monthProgress;
            $actualAvgInc = $avgIncExp['inc'] * $monthRemaining;
            $actualAvgExp = $avgIncExp['exp'] * $monthRemaining;
            $p = ($actualAvgInc-$actualAvgExp); // Get the difference between actual average income minus act. avg. expenses
            // Current balance minus any upcoming payments, plus $p (if negative variance $p will be subtracted as +- == -)
            $f = ($curBalance+$upcoming) + $p;

            // TODO:: get % variance of actual inc/exp against mean inc/exp

            $counter  = 0;
            $curMonth = date("Y-m"); // Current month YYYY-MM
            $curDate  = date("d"); // Current day in format DD
            $mthTransactions = array_reverse($this->transactions[$curMonth]);

            // Creates an array with all the days of the current month (ASC) as key, with blank value e.g. "2018-03-01"=>""
            $datesOfMonth  = array_fill_keys(Text::getMonthDates(false, "Y-m-d", $curDate), "");
            $temp          = array();
            $forecastVals  = array("labels"=>array(), "actual"=>array(), "forecast"=>array());

            // Iterate through each transaction, saving date as key and balance as value
            // Used later to remove duplicate dates
            $y = count($mthTransactions);
            for($i=0; $i<$y; $i++)
                $temp[$mthTransactions[$i]['date']] = $mthTransactions[$i]['balance'];

            $temp = array_unique($temp); // Remove duplicate dates and keeps last balance in the day as balance value

            // Iterate through each date and add corresponding balance to $datesOfMonth array
            // Any days without transaction are blank, e.g. "2018-03-01"=>"", "2018-03-01"=>456.78
            foreach($temp as $date => $balance) {
                if(array_key_exists($date, $datesOfMonth)) {
                    $datesOfMonth[$date] = $balance;
                }
            }

            // Loop through and fill any blanks with 'NaN'
            $tmpBalance = 0;
            foreach($datesOfMonth as $date => $balance) {
                if(!empty($balance)) {
                    $tmpBalance = $balance;
                } else {
                    if($tmpBalance !== 0) {
                        $datesOfMonth[$date] = $tmpBalance;
                    } else {
                        $datesOfMonth[$date] = "NaN";
                    }
                }
            }

            $temp = $datesOfMonth;

            // Push to $forecastVals with the dates in labels and balance in actual
            foreach($temp as $date => $balance) {
                $counter++;
                array_push($forecastVals["labels"], $date);
                array_push($forecastVals["actual"], $balance);
            }

            // Get latest transaction date and end of month - calculate difference in days
            $lastDate = date(end($forecastVals['labels']));
            $endOfMth = date("Y-m-t");
            $dateDiff = Calculate::dateDifference($lastDate,$endOfMth);

            if($dateDiff > 0) {
                // Push remaining days onto labels array, i.e. if last date is 25/02/2018, then it would add 26, 27, 28th
                for($i=1;$i<=$dateDiff;$i++) {
                    array_push($forecastVals['labels'], Calculate::subtractDate($lastDate, -$i));
                }
                // Push value NaN onto forecast array parallel with actual figures - so the forecast line begins at the end of the actual figure line
                for($i=0; $i<$counter-1; $i++)
                    array_push($forecastVals['forecast'], "NaN");

                // Get difference between forecast and current balance & divide by date difference - to create a straight line on the chart
                $fcstDiff    = round(($f - $curBalance) / ($dateDiff), 2);
                $loopBalance = $curBalance;

                // If its the 1st iteration, push current balance so both lines connect on chart, if not, add loop balance
                for($i=0; $i<$dateDiff; $i++) {
                    $i==0 ? array_push($forecastVals['forecast'], $curBalance) : array_push($forecastVals['forecast'], round($loopBalance,2));
                    $loopBalance += $fcstDiff; // Add forecast difference to loop balance
                }

                // Push forecast calculation as last value
                array_push($forecastVals['forecast'], round($f,0));
            }

            return $forecastVals;

        } else {
            return $avgIncExp; // not enough account history to calculate forecast
        }
    }


    // Returns forecast statistics in a progress bar
    public static function progressStats($label, $value, bool $progBar=false) {
        if($progBar === true) {
            if($value<100 && $value>=95)
                $progColour = "progress-bar-danger";  // red
            else if($value >=85)
                $progColour = "progress-bar-warning"; // yellow
            else
                $progColour = "progress-bar-info"; // blue

            $value = round($value,1);

            return "<div class=\"row-fluid\">
                        <div class=\"col-lg-6 col-md-6 col-sm-12 col-xs-12\">$label:</div>
                        <div class=\"col-lg-6 col-md-6 col-sm-12 col-xs-12\">
                            <div class=\"progress\">
                                <div class=\"progress-bar $progColour progress-bar-striped\" role=\"progressbar\"
                                    aria-valuenow=\"$value\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width:$value%\">
                                    $value%
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class=\"clearfix\"></div>";
        } else {
            return "<div class=\"row-fluid\">
                        <div class=\"col-lg-6 col-md-6 col-sm-12 col-xs-12\">$label:</div>
                        <div class=\"col-lg-6 col-md-6 col-sm-12 col-xs-12\"><b>$value</b></div>
                    </div>
                    <div class=\"clearfix\" style='margin-bottom:1em'></div>";
        }
    }

    // Destructor
    public function __destruct()
    {
        $this->conn   = null;
        $this->userID = null;
    }

}