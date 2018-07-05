<?php

require_once('Database.php');
require_once('Calculate.php');
require_once('Crypt.php');
require_once('Forecast.php');
require_once('Table.php');
require_once('Text.php');

class accounts
{
    public $conn;
    private $userID;
    private $currencyLabel;

    // Array of all account ID's
    public $allAccountIDs;

    public function __construct()
    {
        $db = New Database();
        $conn = $db->openConnection();

        $this->userID = $_SESSION['userID'];
        $this->conn = $conn;
        $this->currencyLabel = $_SESSION['currencyLabel'];

        $this->getAccounts(); // get all account ID's
    }

    // Query function
    private function query($sql) {
        $stmt = $this->conn->prepare($sql);
        return $stmt;
    }


    // Populates $allAccountID's property
    private function getAccounts() {
        try {
            $stmt = $this->query("SELECT accountID, accountName FROM accounts WHERE userID=:userID");
            $stmt->execute(array(':userID'=>$this->userID));
            if($stmt->rowCount() > 0){
                while($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
                    $this->allAccountIDs[Crypt::encrypt($row['accountID'])] = $row['accountName'];
                }
                return true;
            }
            return false;
        } catch(PDOException $err) {
            AuditLog::add("getAccounts function failed - ".$err->getMessage());
            return false;
        }
    }


    // Dropdown list containing
    public function accountTypesDdl() {
        try {
            // Fetch account type dropdown select
            $stmt = $this->query("SELECT accountTypeID, accountType FROM accountType");
            $stmt->execute();
            $ddl_accountType = "";
            while($row = $stmt -> fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                $ddl_accountType .= "<option value='" . Crypt::encrypt($row['accountTypeID']) . "'>" . $row['accountType'] . "</option>";
            }
            $stmt = null;
            return $ddl_accountType;
        } catch(PDOException $err) {
            AuditLog::add("Account types DDL function failed - ".$err->getMessage());
            return false;
        }
    }


    // Returns a 2D array with account types as key, and accounts as values
    // Used for side navigation and stats page
    public function listAccountsWithTypes($sideNav=true) {
        $accounts = array();
        try {
            // get account types
            $stmt = $this->query("SELECT accountType FROM accountType ORDER BY accountTypeID ASC");
            $stmt->execute();
            while($row = $stmt -> fetch(PDO::FETCH_ASSOC)) // Save account types as key
                $accounts[$row['accountType']] = [];
            $stmt = null;

            if($sideNav === true)
                $orderBy = "ORDER BY accountName ASC";
            else
                $orderBy = "ORDER BY accountBalance DESC";

            // append accounts onto relevant account types
            $stmt = $this->query("SELECT accountName,accountType,accountBalance,accountID
                                  FROM accounts
                                    LEFT JOIN accountType ON accountType.accountTypeID = accounts.AccountTypeID
                                  WHERE userID = :userID
                          $orderBy");
            $stmt->execute(array(':userID'=>$this->userID));
            while($row = $stmt -> fetch(PDO::FETCH_ASSOC))
                $accounts[$row['accountType']][] = $row;
            $stmt = null;

            return $accounts;

        } catch(PDOException $err) {
            AuditLog::add("List accounts with types failed - ".$err->getMessage());
            return "Looks like a server problem. If problem persists, please contact administrator.";
        }
    }


    //Add a new account function
    public function add($accountName,$accountType,$accountBalance,$includeNet) {
        $audit = "Add new account failed - ";

        // account name validation
        if(!empty($accountName)) {
            $stmt = $this->query("SELECT * FROM accounts WHERE accountName=:accountName");
            $stmt->execute(array(':accountName'=>$accountName));
            if($stmt->rowCount() == 1) {
                AuditLog::add($audit."duplicate account name.");
                $error = "Account name already exists.";
            }
            if(strlen($accountName) > 20) {
                AuditLog::add($audit."account name character length exceeded.");
                $error = "Account name length exceeded. Max: <b>20</b> characters.";
            }
            if(!ctype_alnum(str_replace(' ','',$accountName))) {
                AuditLog::add($audit."non alpha-numeric characters detected.");
                $error = "Only alpha-numeric characters allowed.";
            }
        } else {
            AuditLog::add($audit."account name is empty.");
            $error = "Account name is empty";
        }

        // balance validation
        if(empty($accountBalance)) {
            $accountBalance = 0;
        } else {
            if(!$num = filter_var($accountBalance, FILTER_VALIDATE_FLOAT)) {
                AuditLog::add($audit."non-numerical characters detected.");
                $error = "Opening balance must be a <b>number</b>.";
            }
            if(strlen($accountBalance) > 11) {
                AuditLog::add($audit."balance character length exceeded.");
                $error = "Balance character limit exceeded.";
            }
        }

        if(!isset($error)) {
            try {
                $stmt = $this->query("
                        INSERT INTO accounts (userID, accountName, accountTypeID, openingDate, accountBalance, incNet)
                        VALUES (:userID, :accountName, :accountTypeID, NOW(), :accountBalance, :incNet)");
                $stmt -> bindParam(':userID', $this->userID);
                $stmt -> bindParam(':accountName', $accountName);
                $stmt -> bindParam(':accountTypeID', $accountType, PDO::PARAM_INT);
                $stmt -> bindParam(':accountBalance', $accountBalance);
                $stmt -> bindParam(':incNet', $includeNet, PDO::PARAM_INT);
                $stmt -> execute();
                $stmt = null;

                $db = null;
                $conn = null;
                AuditLog::add("New account created");
                return true;
            } catch(PDOException $err) {
                AuditLog::add("Add account failed - ".$err->getMessage());
                return "Looks like an internal server problem. If problem persists, contact administrator..";
            }
        } else {
            return $error;
        }
    }


    // Update account - manage accounts page
    public function update($accountID,$name,$type,$budget,$balance,$incNet) {
        $auditMsg = "Update account failed - ";

        // Account name validation
        if(!empty($name)) {
            if(strlen($name) > 20) {
                AuditLog::add($auditMsg."character length exceeded.");
                $error = "Account name character length exceeded. Max: 20.";
            }
            if(!ctype_alnum(str_replace(' ','',$name))) {
                AuditLog::add($auditMsg."non alpha-numeric characters detected.");
                $error = "Only alpha-numeric characters allowed.";
            }
        } else {
            AuditLog::add($auditMsg."empty account name");
            $error = "Account name is empty. Please give it a name before submitting.";
        }

        // Account budget validation
        if(!empty($budget)) {
            if (($num = filter_var($budget, FILTER_VALIDATE_FLOAT)) == true) {
                if(strlen($budget) > 11) {
                    AuditLog::add($auditMsg."budget character limit exceeded.");
                    $error = "Account budget character limit exceeded. Max: 11.";
                } else {
                    if($budget < 0) {
                        AuditLog::add($auditMsg."budget is negative.");
                        $error = "Account Budget cannot be negative.";
                    }
                    // Rounds amount if there is more than 2 decimal places
                    if(Calculate::decimalPlaces($budget) > 2) {
                        $budget = round($budget,2);
                    }
                }
            } else {
                AuditLog::add($auditMsg."invalid input on budget");
                $error = "Account budget must only contain numbers.";
            }
        } else {
            $budget = 0.00;
        }

        // Account balance validation
        if(!empty($balance)) {
            if (($num = filter_var($balance, FILTER_VALIDATE_FLOAT)) == true) {
                if(strlen($balance) > 11) {
                    AuditLog::add($auditMsg."balance character limit exceeded.");
                    $error = "Account balance character limit exceeded. Max: 11.";
                } else {
                    // Rounds amount if there is more than 2 decimal places
                    if(Calculate::decimalPlaces($balance) > 2) {
                        $balance = round($balance,2);
                    }
                }
            } else {
                AuditLog::add($auditMsg."invalid input on balance");
                $error = "Account balance must only contain numbers.";
            }
        } else {
            AuditLog::add($auditMsg."balance is empty");
            $error = "Balance cannot be empty. If you would like to set it back to 0, type 0.";
        }


        // execute update query if no error is set
        if(!isset($error)) {
            try {
                $updateArr = array(':accountName'=>$name, ':typeID'=>$type, ':budget'=>$budget, ':balance'=>$balance, ':incNet'=>$incNet, ':userID'=>$this->userID, ':accountID'=>$accountID);
                $stmt = $this->query("UPDATE accounts 
                                      SET accountName=:accountName, accountTypeID=:typeID, accountBudget=:budget, accountBalance=:balance, incNet=:incNet 
                                      WHERE userID=:userID AND accountID=:accountID");
                $stmt->execute($updateArr);
                $stmt = null;
                return true;
            } catch(PDOException $err) {
                AuditLog::add($auditMsg.$err->getMessage());
                return "Looks like an internal server problem. If problem persists, contact administrator..";
            }
        } else {
            return $error;
        }
    }


    // Delete's an account, from manage accounts page
    public function delete($id) {
        try {
            $stmt = $this->query("DELETE FROM accounts WHERE accountID=:accountID AND userID=:userID");
            $stmt->execute(array(':accountID'=>$id, ':userID'=>$this->userID));
            $stmt = null;

            $stmt = $this->query("DELETE FROM transactions WHERE accountID=:accountID AND userID=:userID");
            $stmt->execute(array(':accountID'=>$id, ':userID'=>$this->userID));
            $stmt = null;

            $stmt = $this->query("DELETE FROM recurTransactions WHERE accountID=:accountID AND userID=:userID");
            $stmt->execute(array(':accountID'=>$id, ':userID'=>$this->userID));
            $stmt = null;
            return true;
        } catch(PDOException $err) {
            AuditLog::add("Delete account failed - ".$err->getMessage());
            return "Looks like an internal server problem. If problem persists, contact administrator..";
        }
    }


    // Fetches all account data for manage accounts page
    public function info() {
        $accounts = array(); // Current account type ID as keys (1,2,3 => Checking, Savings and credits)
        try {
            $stmt = $this->query("SELECT accountID,accountName,accountType.accountTypeID,DATE_FORMAT(openingDate, '%d/%m/%Y') as openingDate,accountBudget,accountBalance,incNet 
                                  FROM accounts LEFT JOIN accountType ON accountType.accountTypeID = accounts.accountTypeID
                                  WHERE userID=:userID ORDER BY accounts.accountTypeID, accountName ASC");
            $stmt->execute(array(':userID'=>$this->userID));
            if($stmt->rowCount() > 0) {
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    // Insert account details into array with ID as key
                    $accounts[$row['accountID']] = array(
                        "type"     => $row['accountTypeID'],
                        "name"     => $row['accountName'],
                        "openDate" => $row['openingDate'],
                        "budget"   => $row['accountBudget'],
                        "balance"  => $row['accountBalance'],
                        "incNet"   => $row['incNet']);
                }
            }
            $stmt = null;
            return $accounts;

        } catch(PDOException $err) {
            AuditLog::add("Fetching account info failed - ".$err->getMessage());
            return "Looks like an internal server problem. If problem persists, contact administrator..";
        }
    }


    // Gets data for account variance line chart on dashboard - gets monthly total income & expenses for the current year
    // Returns array with labels (months) and income & expenses for each month
    public function activity($forecast=false) {
        $accountIDs = $this->allAccountIDs;
        // array to hold the month as key, and total income/expenditures for each
        // e.g. "2018-03" => array("inc"=>257.54, "exp"=>124.54)
        $activity = array("labels"=>array(), "inc"=>array(), "exp"=>array());
        $temp = array();

        if(!empty($accountIDs)) {
            // Loop all account ID's created by user
            foreach($accountIDs as $encID => $accountName) {
                $fcst = New Forecast($this->conn, Crypt::decrypt($encID)); // Instantiate forecast object to access transactions property
                $fcst->getAccountTransactionStats(); // Execute function to build transactions associative array, sorted by month DESC
                $transactions = $fcst->transactions;

                // If function is used for forecast calculation, i.e. for a single account,  set new array to save individual account details
                if($forecast !== false) {
                    if($forecast == Crypt::decrypt($encID)) {
                        $forecastArr = array();
                        // Sorts transactions and excludes any recurring - recur transactions are used for separate calculation
                        $transactions = $fcst->sortNonRecurTransactions();
                    }
                }

                if(!empty($transactions)) {
                    // Loop though each month in array (first key)
                    foreach($transactions as $month => $transOfMonth) {
                        $mthIncome   = 0;
                        $mthExpenses = 0;

                        // loop through each transaction of the current month
                        foreach($transOfMonth as $transID => $info) {

                            if($info['amount'] > 0) {
                                $mthIncome   += $info['amount'];
                            } else {
                                $mthExpenses += 0-$info['amount'];
                            }
                        }

                        // If function is used for forecast calculation, i.e. for a single account, save income and expenses for each month in new array
                        if($forecast !== false) {
                            if($forecast == Crypt::decrypt($encID)) { // If forecast account ID == loop account ID
                                if(array_key_exists($month, $forecastArr)) {
                                    $forecastArr[$month]['inc'] += round($mthIncome,2); // round had to be inserted
                                    $forecastArr[$month]['exp'] += round($mthExpenses,2);
                                } else {
                                    $forecastArr[$month]['inc'] = round($mthIncome,2);
                                    $forecastArr[$month]['exp'] = round($mthExpenses,2);
                                }
                            }
                        } else { // if calculation is for dashboard line chart
                            // If month already exist in array, add total instead of declaring it
                            if(array_key_exists($month, $temp)) {
                                $temp[$month]['inc'] += round($mthIncome,2);
                                $temp[$month]['exp'] += round($mthExpenses,2);
                            } else {
                                $temp[$month]['inc'] = round($mthIncome,2);
                                $temp[$month]['exp'] = round($mthExpenses,2);
                            }
                        }
                    }
                }
                $fcst = null; // Reset forecast object
            } // Accounts foreach

            // If function is used for forecast, return array with account monthly income/expenses
            if(isset($forecastArr)) {
                return $forecastArr;
            }

            // Slice the array so only up to a year is shown, e.g. from Mar 2017 to Mar 2018
            if(count($temp) > 12) {
                $temp = array_slice($temp,0,13);
            }
            // Making the most recent month appear last on the chart, i.e. on the right rather than the left
            $temp = array_reverse($temp);

            foreach($temp as $month => $values) {
                $label = date_format(date_create($month), "M Y"); // Formats the date from "2018-01" to Jan 2018
                array_push($activity['labels'], $label);
                array_push($activity['inc'], $values['inc']);
                array_push($activity['exp'], $values['exp']);
            }

            return $activity;
        } else {
            AuditLog::add("Get account activity function failed - no accounts created");
            return "";
        }
    }


    // Returns activity for a specified account for manage accounts page
    // All recent transactions
    public function accountActivity($ID, $recurOnly=false) {
        if(!is_numeric($ID)) {
            Throw new Exception("Invalid account ID parameter on accountActivity function.");
        }

        $where = "WHERE transactions.userID = $this->userID AND accountID = $ID"; // SQL where clause
        $orderBy = "ORDER BY transactionDate DESC, transactionID DESC"; // SQL order by clause
        $tblName = "tblTransactions";

        if($recurOnly === true) {
            $where  .= " AND recurID IS NOT NULL"; // If $recurOnly is set to true, add string onto where clause
            $orderBy = "ORDER BY transactionDate DESC";
            $tblName = "tblRecurTransactions";
        }

        try {
            $query = "SELECT 
                    transactions.transactionID,
                    categories.catName as Category,
                    transactions.transactionDate as `Date`,
                    transactions.transactionType as `Type`,
                    CONCAT('$this->currencyLabel',transactions.transactionAmount) as Amount,
                    CONCAT('$this->currencyLabel',transactions.balance) as Balance
                  FROM transactions
                  LEFT JOIN categories ON categories.categoryID = transactions.categoryID
                  $where 
                  $orderBy";

            $settings = '{
                "tableName" : "'.$tblName.'",
                "showHeader" : true,
                "paging" : true,
                "pageSize" : 10,
                "sorting" : true,
                "rowURL" : "edit.php",
                "rowURLQS" : { "id" : "transactionID" },
                "columns" : 
                [
                    "Date",
                    "Category", 
                    "Type",
                    "Amount",
                    "Balance"
                ]
            }';

            return Table::render($query, $settings); // return paginated table

        } catch(PDOException $err) {
            AuditLog::add("Get account activity for table failed for manage accounts - ".$err->getMessage());
            return "Oops... something went wrong. Please contact Administrator.";
        }
    }


    public function __destruct()
    {
        $this->conn = null;
        $this->userID = null;
        $this->allAccountIDs = null;
    }

}