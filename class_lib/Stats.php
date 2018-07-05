<?php
require_once("Database.php");
require_once("Budgets.php");
require_once("Calculate.php");
require_once("Crypt.php");
require_once("Account.php");
require_once("Forecast.php");
require_once("Text.php");

class Stats
{
    private $userID;
    private $conn;

    private $accountIDs;

    public $index = 0;
    public $chartColours = array(
        1 => "#fd6585",  2  => "#3da3e8",
        3 => "#fac95f",  4  => "#51c0bf",
        5 => "#996cfb",  6  => "#fd9e4b",
        7 => "#5db068",  8  => "#b05144",
        9 => "#3c45b5",  10 => "#edd66d"
    );


    public function __construct()
    {
        $db = New Database();
        $account = New accounts();
        $conn = $db->openConnection();

        $this->conn = $conn;
        $this->accountIDs = $account->allAccountIDs; // all encrypted account IDs
        $this->userID = $_SESSION['userID'];
    }

    private function query($sql) {
        $stmt = $this->conn->prepare($sql);
        return $stmt;
    }

    public function getColor() {
        if($this->index == count($this->chartColours))
            $this->index = 0;
        $this->index++;
        $color = $this->chartColours[$this->index];
        return $color;
    }


    // Returns the values for account balances line chart - last 12 months balances
    // 2D array returned with the account name as the key, and an array with the last 12 months balances in ascending order
    public function getAccountActivity() {
        $activity = array();
        $temp     = array();
        $js       = "";
        $months   = array_fill_keys(Text::getYearDates(), ""); // Set array with months of year as keys YYYY-MM=>""

        $accObj = New accounts();
        $allAccountIDs = $accObj->allAccountIDs;

        if(!empty($allAccountIDs)) {
            // Loop through each account
            foreach($allAccountIDs as $accountID => $accountName) {
                $fcstObj = New Forecast($this->conn, Crypt::decrypt($accountID));
                $fcstObj->getAccountTransactionStats(); // populates transaction properties in forecast obj - to get end of month account balances
                $accBalances = $fcstObj->endOfMonthAccountBalances; // account balances for each month

                if(!empty($accBalances)) {
                    $temp = $months; // Set temp array
                    // Loop through each balance and set it to month key if it exists in $temp keys (current 12 months) - any above a year old is excluded
                    foreach($accBalances as $month => $balance) {
                        if(array_key_exists($month, $temp)) {
                            $temp[$month] = $balance;
                        }
                    }

                    $tmpBalance = 0;
                    // Reverse $temp and find earliest balance - if any balances are blank after it (no transactions within month), use last known balance
                    // If any are blank before, insert 'NaN' for chart so the line doesn't begin from start of y axis
                    foreach(array_reverse($temp) as $mth => $balance) {
                        if(!empty($balance)) {
                            $tmpBalance = $balance;
                        } else {
                            if($tmpBalance !== 0) {
                                $temp[$mth] = $tmpBalance;
                            } else {
                                $temp[$mth] = "NaN"; // for chart.js
                            }
                        }
                    }

                    $temp = array_values($temp); // get values excluding month keys
                    // Set activity with account name as key and balances as value - array needs to be reversed again to go in ASC order by date (most recent is last)
                    $activity[$accountName] = array_reverse($temp);
                }
                unset($temp);
                $fcstObj = null;
            }
            if(!empty($activity)) {
                return $activity;
            } else {
                return 0;
            }
        } else {
            // No accounts created
            AuditLog::add("getAccountActivity function failed - no accounts found.");
            return "No information to show.";
        }
    }


    // returns array for net worth pie chart - 2 parallel arrays with the account names in one, and balances in the other
    public function netWorthStats() {
        $accounts = array("labels"=>array(), "values"=>array());
        try {
            $stmt = $this->query("SELECT accountName,accountBalance FROM accounts WHERE userID=:userID AND incNet=1 AND accountTypeID <> 3");
            $stmt->execute(array(':userID'=>$this->userID));
            if($stmt->rowCount()>0) {
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    array_push($accounts['labels'], $row['accountName']);
                    if($row['accountBalance'] < 0)
                        $row['accountBalance'] = 0;
                    array_push($accounts['values'], $row['accountBalance']);
                }
                return $accounts;
            } else {
                AuditLog::add("Net worth chart function failed - no accounts");
                return 0;
            }
        } catch(PDOException $err) {
            AuditLog::add("Net worth chart function failed - ".$err->getMessage());
            return 0;
        }
    }


    // Generate html for progress bar
    public static function renderProgressBar($label, $valuesArr) {
        if(is_array($valuesArr)) {
            if(0-$valuesArr['exp'] >= $valuesArr['bud']) {
                $value = 100;
            } else {
                $value = round(((0-$valuesArr['exp']) / $valuesArr['bud']) * 100, 1); // gets % value
            }
        } else {
            $value = 0;
        }

        if($value>=95)
            $progColour = "progress-bar-danger";  // red
        else if($value >=85)
            $progColour = "progress-bar-warning"; // yellow
        else
            $progColour = "progress-bar-info"; // blue

        return "<div class='col-xs-12'><b>$label</b></div>
                <div class='col-xs-12'>
                    <div class=\"progress\">
                        <div class=\"progress-bar $progColour progress-bar-striped\" role=\"progressbar\"
                             aria-valuenow=\"$value\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width:$value%\">
                            <b>$value%</b> used
                        </div>
                    </div>
                </div>";
    }


    // Fetches the total amount of user transactions in the current month
    private function getMonthTransactions() {
        try {
            $stmt = $this->query("SELECT COUNT(transactionID) FROM transactions WHERE userID=:userID AND MONTH(transactionDate) = MONTH(CURRENT_DATE) AND YEAR(transactionDate) = YEAR(CURRENT_DATE)");
            $stmt->execute(array(':userID'=>$this->userID));
            $ttlTransactions = $stmt->fetch()[0];
            $stmt = null;
            return $ttlTransactions;
        } catch(PDOException $err) {
            AuditLog::add("getMonthTransactions failed - ".$err->getMessage());
            return "";
        }
    }


    // returns total number of user accounts
    private function getTotalAccounts() {
        try {
            $stmt = $this->query("SELECT COUNT(accountID) FROM accounts WHERE userID=:userID");
            $stmt->execute(array(':userID'=>$this->userID));
            $ttlAccounts = $stmt->fetch()[0];
            $stmt = null;
            return $ttlAccounts;
        } catch(PDOException $err) {
            AuditLog::add("getTotalAccounts failed - ".$err->getMessage());
            return "";
        }
    }


    // Renders statistics for top of page on stats - total transactions, income and expenses for the month, and total accounts
    public function renderStatPanels() {
        $html = "";
        $budgets = New Budgets();
        $incomeExpenses = $budgets->getMonthlyIncomeExpense();
        $budgets = null;

        $values  = array(
            "Transactions this Month" => $this->getMonthTransactions(),
            "Income this Month"       => money_format('%n', $incomeExpenses['income']),
            "Expenses this Month"     => money_format('%n', $incomeExpenses['expenses']),
            "Total Accounts"          => $this->getTotalAccounts()
        );

        // Iterate through each stat and append html
        foreach($values as $label => $value) {
            $html .= "<div class=\"col-lg-3 col-md-3 col-sm-6 col-xs-6 statCard text-center\">
                         <div class=\"col-xs-12 statLabel \">$label</div>
                         <div class=\"col-xs-12 statValue\">$value</div>
                      </div>";
        }

        return $html;
    }


    // Returns accounts and balances HTML for panel next to balance line chart
    // $account->listAccountsWithTypes (returns array of acoounts) passed as parameter
    public static function renderAccountsWithTypes($accountsArr) {
        if(!is_array($accountsArr))
            Throw new Exception("Invalid parameter for rendering account types - array expected");

        // Account data next to line chart
        $accountsHTML = ""; // html for accounts & balances
        foreach($accountsArr as $type => $accountsOfType) {
            $accountsHTML .= "<div class=\"col-xs-12 account-type\">$type</div>"; // Account type, e.g. Checking Accounts

            if(!empty($accountsOfType)) {
                $accountsHTML .= "<div class=\"col-xs-12\">
                                     <table class=\"table table-condensed\">
                                        <tbody>";
                foreach($accountsOfType as $aot) {
                    $accountsHTML .= "  <tr>
                                           <td>".$aot['accountName']."</td><td>".money_format('%n',$aot['accountBalance'])."</td>
                                        </tr>";
                }
                $accountsHTML .= "      </tbody>
                                     </table>
                                  </div>";
            } else {
                $accountsHTML .= "<div class=\"col-xs-12\">None</div>"; // If no accounts exists on account type
            }
        }
        return $accountsHTML;
    }


    // Returns total category balances for the current month
    public function categoryStats() {
        $accountIDs    = $this->accountIDs; // all encrypted account ID's
        $categoryStats = array();

        if(!empty($accountIDs)) {
            // Iterate through each account
            foreach($accountIDs as $encID => $accName) {
                $f = New Forecast($this->conn, Crypt::decrypt($encID));
                $f->getCategoryStats();

                $categories  = $f->categories; // fetches categories with names
                $catBalances = $f->categoryBalances; // fetches end of month account balances (inc - exp) for each category

                if(!empty($catBalances)) {
                    foreach($catBalances as $catID => $balances) {
                        $catName = $categories[$catID];
                        if(array_key_exists($catName, $categoryStats))
                            $categoryStats[$catName] += Text::getNthArrayElement($balances); // get first array element (current month balance)
                        else
                            $categoryStats[$catName] = Text::getNthArrayElement($balances);
                    }
                }
                $f = null;
            }

            arsort($categoryStats); // sort categories by category name ASC
            return $categoryStats;
        } else {
            AuditLog::add("get categoryStats failed - no accounts found.");
            return "";
        }
    }


    public function renderRevenueTable($values) {
        $tableHTML = "";

        if(!empty($values)) {
            foreach($values as $label => $balance) {
                $balance = money_format('%n',$balance);
                $balance = ($balance > money_format('%n',0) ? "+".$balance : $balance); // 0 has to be formatted for comparison
                $tableHTML .= "<tr>
                                 <td>$label</td>
                                 <td>$balance</td>
                              </tr>";
            }
        }
        return $tableHTML;
    }


    public function accountRevenue() {
        $accountRev = array();

        try {
            $stmt = $this->query("SELECT 
                                    SUM(transactionAmount) as revenue
                                  FROM transactions 
                                  WHERE MONTH(transactionDate) = MONTH(CURRENT_DATE()) 
                                    AND YEAR(transactionDate) = YEAR(CURRENT_DATE())
                                    AND accountID=:accountID
                                    AND userID=:userID");

            if(!empty($this->accountIDs)) {
                foreach($this->accountIDs as $encID => $accName) {
                    $stmt->execute(array(':accountID'=>Crypt::decrypt($encID), ':userID'=>$this->userID));
                    $revenue = $stmt->fetch()[0];

                    if($revenue == null || empty($revenue))
                        $revenue = 0.00;

                    $accountRev[$accName] = $revenue;
                }
            } else {
                return "";
            }

            $stmt = null;
            arsort($accountRev);
            return $accountRev;

        } catch(PDOException $err) {
            AuditLog::add("stats account revenue failed - ".$err->getMessage());
            return "";
        }
    }


}