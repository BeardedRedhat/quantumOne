<?php
require_once('Database.php');
require_once('Crypt.php');
require_once('Session.php');
require_once('Calculate.php');
require_once('AuditLog.php');


class transactions
{
    private $conn;
    private $userID;

    public $accountID;
    public $categoryID;
    public $transType;
    public $amount;
    public $description;
    public $receiptNo;
    public $balance;
    public $recurID = null;


    // Constructor
    public function __construct($conn) {
        if(!$conn instanceof PDO) {
            AuditLog::add("Transactions __construct failed - invalid connection parameter");
            die("Database connection failed");
        }
        $db = New Database();
        $this->conn = $db->openConnection();
        $this->userID = $_SESSION['userID'];
    }


    // Query function
    private function query($sql) {
        $stmt = $this->conn->prepare($sql);
        return $stmt;
    }


    // Checks the format of date inputs, returns in mysql format: YYYY-MM-DD
    public function isValidDate($date) {
        if(strtotime($date) === false) {
            return false;
        }
        list($year, $month, $day) = explode('-', $date);
        return checkdate($month, $day, $year);
    }


    // Generate accounts dropdown list options
    public function generateAccountsDdl() {
        AuditLog::add("Function called to generate accounts dropdown options");
        try {
            $stmt = $this->query("SELECT accountName,accountID FROM accounts WHERE userID = :userID AND accountTypeID < 4 ORDER BY accountTypeID ASC");
            $stmt->execute(array(':userID' => $this->userID));

            if($stmt->rowCount() > 0) {
                $sel_accounts = "";
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    $sel_accounts .= "<option value=\"". Crypt::encrypt($row['accountID']) ."\">" . $row['accountName'] . "</option>";
                }
                $stmt = null;
                AuditLog::add("Accounts ddl options generated");
                return $sel_accounts;

            } else {
                AuditLog::add("Account ddl generated with no options");
                return "<option></option>";
            }
        } catch(PDOException $err) {
            AuditLog::add("Accounts Ddl failed - ".$err->getMessage());
            return "An unexpected error has occurred. If problem persists, contact administrator.";
        }
    }


    // Generate categories dropdown list options
    public function generateCategoriesDdl() {
        AuditLog::add("Function called to generate accounts dropdown options");
        try {
            $stmt = $this->query("SELECT categoryID,catName,catUses FROM categories WHERE userID = :userID ORDER BY catName ASC");
            $stmt->execute(array(':userID' => $this->userID));

            if($stmt->rowCount() > 0) {
                $sel_categories = "";
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    $sel_categories .= "<option value=\"". Crypt::encrypt($row['categoryID']) ."\">" . $row['catName'] . "</option>";
                }
                $stmt = null;
                return $sel_categories;

            } else {
                return "<option></option>";
            }
        } catch(PDOException $err) {
            AuditLog::add("Failed to generate categories Ddl - ".$err->getMessage());
            return "An unexpected error has occurred. If problem persists, contact Administrator.";
        }
    }


    // Return most recent transactions with the quantity specified in parameter
    // Returns array with the transaction ID as key and details as an array value
    public function getMostRecent($quantity) {
        if(filter_var($quantity, FILTER_VALIDATE_INT) === false) {
            AuditLog::add("Failed to generate most recent transactions - quantity is non-integer type.");
            return "Unable to generate most recent transactions. Please contact administrator.";
        }
        if($quantity > 15) {
            trigger_error("Quantity limit exceeded on most recent transactions function. Limit 15", E_USER_WARNING);
            return "Unable to generate most recent transactions. Please contact administrator.";
        }

        $transactions = array();

        try {
            $stmt = $this->query("SELECT 
                                    transactionID,
                                    DATE_FORMAT(transactionDate, '%d/%m/%Y') AS transDate,
                                    transactionType,
                                    transactionAmount,
                                    balance,
                                    categories.catName,
                                    accounts.accountName AS accName
                                  FROM  transactions 
                                    LEFT JOIN categories ON categories.categoryID = transactions.categoryID
                                    LEFT JOIN accounts ON accounts.accountID = transactions.accountID
                                  WHERE transactions.userID = :userID
                                  ORDER BY transactionDate DESC, transactionID DESC LIMIT $quantity");
            $stmt->execute(array(':userID'=>$this->userID));
            if($stmt->rowCount() > 0) {
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    $transactions[$row['transactionID']] = array(
                        "date"     => $row['transDate'],
                        "type"     => $row['transactionType'],
                        "amount"   => $row['transactionAmount'],
                        "category" => $row['catName'],
                        "account"  => $row['accName'],
                        "balance"  => $row['balance']
                    );
                }
                $stmt = null;
                return $transactions;

            } else {
                AuditLog::add("Failed to generate most recent transactions - none added yet.");
                return "";
            }
        } catch(PDOException $err) {
            AuditLog::add("Failed to generate most recent transactions - ".$err->getMessage());
            return "Looks like an internal server problem. If problem persists, contact administrator..";
        }
    }


    // Fetches any upcoming payments
    public function getUpcomingRecur($limit=0) {
        $transactions = array();
        if($limit === 0) {
            $limit = 9999999;
        }
        try {
            $stmt = $this->query("SELECT 
                                    recurTransactions.ID,
                                    accounts.accountName, 
                                    categories.catName, 
                                    recurTransactions.type, 
                                    recurTransactions.amount, 
                                    DATE_FORMAT(recurTransactions.startDate, '%d/%m/%Y') as startDate, 
                                    DATE_FORMAT(recurTransactions.endDate, '%d/%m/%Y') as endDate, 
                                    recurTransactions.frequency, 
                                    recurTransactions.indefinite
                                  FROM recurTransactions 
                                    LEFT JOIN accounts ON accounts.accountID = recurTransactions.accountID
                                    LEFT JOIN categories ON categories.categoryID = recurTransactions.categoryID
                                  WHERE recurTransactions.userID=:userID 
                                    AND recurTransactions.inactive = 0 
                                    LIMIT $limit");
            $stmt->execute(array(':userID'=>$this->userID));
            if($stmt->rowCount() > 0) {
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    $transactions[$row['ID']] = array(
                        "account"    => $row['accountName'],
                        "category"   => $row['catName'],
                        "type"       => $row['type'],
                        "amount"     => $row['amount'],
                        "startDate"  => $row['startDate'],
                        "endDate"    => $row['endDate'],
                        "frequency"  => $row['frequency'],
                        "indefinite" => $row['indefinite']
                    );
                }
                return $transactions;
            } else {
                AuditLog::add("No upcoming payments found.");
                return "No upcoming transactions.";
            }
        } catch(PDOException $err) {
            AuditLog::add("Get upcoming recurring transactions failed - ".$err->getMessage());
            return "Looks like an internal server problem. If problem persists, contact administrator..";
        }
    }


    // Add a new category
    public function addCategory($name,$budget=0) {
        $name   = strip_tags($name);
        $budget = strip_tags($budget);

        $audit = "Add new category failed - ";

        if(!empty($name)) {
            if(!ctype_alnum(str_replace(' ','',$name))) {
                AuditLog::add($audit."non alpha-numeric characters detected.");
                $error = "Only alpha-numeric characters allowed.";
            }
            if(strlen($name) > 30) {
                AuditLog::add($audit."character length exceeded.");
                $error = "Category name character length exceeded. Max: <b>30 characters</b>.";
            }
        } else {
            AuditLog::add($audit."empty category name.");
            $error = "No category name given. Please give it a <b>name</b> before submitting.";
        }


        if(empty($budget)) {
            $budget = 0;
        } else {
            if((!$num = filter_var($budget, FILTER_VALIDATE_FLOAT))) {
                AuditLog::add($audit."non numeric characters detected in budget.");
                $error = "Category budget must only contain <b>numbers</b>.";
            }
            if($budget < 0) {
                AuditLog::add($audit."budget was below 0.");
                $error = "Budget cannot be below 0.";
            }
        }

        if(!isset($error)) {
            try {
                $stmt = $this->query("INSERT INTO categories(userID,catName,catBudget,dateCreated) VALUES (:userID,:catName,:catBudget,NOW())");
                $stmt->bindParam(':userID', $this->userID);
                $stmt->bindParam(':catName', $name);
                $stmt->bindParam(':catBudget', $budget);
                $stmt->execute();
                $stmt = null;
                AuditLog::add("New category added");
                return true;
            } catch(PDOException $err){
                return "Oops, something went wrong. Please contact Administrator: " . $err->getMessage();
            }
        } else {
            return $error;
        }
    }


    // show all user categories on transactions page
    public function showCategories() {
        $categories = "";
        try {
            $stmt = $this->query("SELECT categoryID, catName, DATE_FORMAT(dateCreated, '%d/%m/%Y') as openDate FROM categories WHERE userID=:userID ORDER BY catName ASC");
            $stmt->execute(array(':userID'=>$this->userID));
            if($stmt->rowCount()>0) {
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    $categories .= "<tr>
                                        <td>".$row['catName']."</td>
                                        <td>".$row['openDate']."</td>
                                        <td style='width:5%'><a href=\"/transactions/index.php?id=".Crypt::encrypt($row['categoryID'])."&action=".Crypt::encrypt('deleteCat')."\"><span class=\"fa fa-trash-o\" aria-hidden=\"true\" style='color:red'></span></a></td>
                                    </tr>";
                }
                return $categories;
            } else {
                AuditLog::add("Show user categories returned no categories.");
                return "No categories created yet.";
            }
        } catch(PDOException $err) {
            AuditLog::add("Show user categories failed - ".$err->getMessage());
            return "Looks like an internal server problem. If problem persists, contact administrator..";
        }
    }


    // Returns total number of transactions - for the navbar
    public function totalTransactions() {
        try {
            $stmt = $this->query("SELECT COUNT(*) AS ttlTransactions FROM transactions WHERE userID = :userID");
            $stmt -> bindColumn('ttlTransactions', $ttlTransactions);
            $stmt -> execute(array(':userID'=>$this->userID));
            $stmt -> fetch();
            $stmt = null;
            return $ttlTransactions;
        } catch(PDOException $err) {
            AuditLog::add("Total transactions query failed - ".$err->getMessage());
            return 0;
        }
    }


    // Checks if user has created any accounts
    // For the addNew() function
    private function checkForAccounts() {
        try {
            $stmt = $this->query("SELECT COUNT(*) AS countAcc FROM accounts WHERE userID = $this->userID");
            $stmt->bindColumn('countAcc', $counter, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->fetch();
            $stmt = null;
            if($counter > 0) {
                return true;
            } else {
                return false;
            }
        } catch(PDOException $err) {
            AuditLog::add("Check for accounts function failed: " . $err->getMessage());
            return false;
        }
    }


    // Checks if the user has created any categories
    // For the addNew() function
    private function checkForCategories() {
        try {
            $stmt = $this->query("SELECT COUNT(*) AS countCat FROM categories WHERE userID = $this->userID");
            $stmt->bindColumn('countCat', $counter);
            $stmt->execute();
            $stmt->fetch();
            $stmt = null;
            if($counter > 0) {
                return true;
            } else {
                return false;
            }
        } catch(PDOException $err) {
            AuditLog::add("Check for categories function failed: " . $err->getMessage());
            return false;
        }
    }


    // Returns the account balance of given account
    // Used for addNew() function to update `balance` field in transactions table
    private function getAccountBalance($accountID = null) {
        if($accountID == null) {
            $accountID = $this->accountID;
        }
        try {
            $stmt = $this->query("SELECT accountBalance FROM accounts WHERE accountID = :accountID");
            $stmt->bindColumn('accountBalance', $balance, PDO::PARAM_STR);
            $stmt->execute(array(':accountID' => $accountID));
            $stmt->fetch();
            $stmt = null;
            return $balance;
        } catch(PDOException $err) {
            AuditLog::add("Transactions object getAccountBalance function failed - ".$err->getMessage());
            return false;
        }
    }


    // Function to update the account balance after each transaction
    private function updateBalance($accountID=null, $balance=null) {
        if($accountID == null)
            $accountID = $this->accountID;
        if($balance == null)
            $balance = $this->balance;
        try {
            // Updating account balance in accounts
            $stmt = $this->query("UPDATE accounts SET accountBalance = :balance WHERE accountID = :accountID");
            $stmt->execute(array(':balance' => $balance, ':accountID' => $accountID));
            $stmt = null;
            return true;
        } catch(PDOException $err) {
            AuditLog::add("Query failure on addNew() function - ".$err->getMessage());
            return "Seems to be a server problem. Contact administrator.";
        }
    }


    // Add new transaction
    public function addNew($type, $accountID, $categoryID, $amount, $receiptNo=null, $description=null,
                           $recur=false, $startDate=null, $endDate=null, $freq=null, $indefinite=null) {

        AuditLog::add("Add new transaction function called.");
        $audit = "Add new transaction failed - ";

        if(empty($accountID) || empty($categoryID) || empty($amount))
            return "Add new transaction failed. Empty fields detected: Account, Category, Amount.";

        if($this->checkForAccounts() == false && $this->checkForCategories() == false)
            return "To add a new transaction, you'll first need to create an <b>Account</b> and a <b>Category</b>.";
        else if($this->checkForAccounts() == false)
            return "To add a Transaction, you'll need to create an <b>Account</b> first.";
        else if($this->checkForCategories() == false)
            return "To add a transaction, you'll first need to create a <b>Category</b>.";
        else {

            $this->accountID  = $accountID;
            $this->transType  = $type;
            $this->categoryID = $categoryID;

            if(!empty($receiptNo)) {
                if (strlen($receiptNo) > 25) {
                    AuditLog::add($audit."receipt character limit exceeded");
                    $error = "Receipt number character limit exceeded. Max: 25.";
                }
                if(!ctype_alnum(str_replace(' ','',$receiptNo))) {
                    AuditLog::add($audit."special characters detected.");
                    $error = "Receipt No. cannot contain any special characters.";
                }
                $this->receiptNo = $receiptNo;
            } else {
                $this->receiptNo = null;
            }

            if(!empty($description)) {
                if (strlen($description) > 1000) {
                    AuditLog::add($audit."description character limit exceeded");
                    $error = "Description character limit exceeded. Max: 1000 characters.";
                }
                $this->description = $description;
            } else {
                $this->description = null;
            }

            // Validates that the entered value is a double
            if ((!$num = filter_var($amount, FILTER_VALIDATE_FLOAT))) {
                AuditLog::add($audit."incorrect input type");
                    $error = "Transaction amount must only contain numbers.";
            } else {
                if($amount < 0) {
                    AuditLog::add($audit."entered amount is negative.");
                    $error = "Amount cannot be negative. If you wish to add an expense, select 'Expense' under type.";
                }
                if(strlen($amount) > 11) {
                    AuditLog::add($audit."amount character limit exceeded.");
                    $error = "Amount character limit exceeded.";
                }
                // Rounds amount if there is more than 2 decimal places
                if(Calculate::decimalPlaces($amount) > 2) {
                    $amount = round($amount,2);
                }
                // If transaction is expense, convert to a negative value for database
                if($type == "expense") {
                    settype($amount, "double");
                    $amount = 0-$amount;
                }
            }

            $this->amount = $amount;

            // Fetching previous account balance and adding transaction amount
            // Used for transaction new balance field in the db
            if($oldBalance = $this->getAccountBalance()) {
                $accountBalance = $oldBalance + $amount;
            } else {
                AuditLog::add("Account not found on transactions object->getAccountBalance.");
                return "Something went wrong. Please contact Administrator.";
            }

            $this->balance = $accountBalance;

            // If the transaction is non-recurring and no errors are set, insert the values into transactions table
            if($recur == false) {
                if(!isset($error)) {  // No form validation errors
                    if($result = $this->addToTransactionsTable() == true)
                        return true;
                    else
                        return $result;
                } else {
                    AuditLog::add('Add transaction failed - '.$error);
                    return $error;
                }
            }

            // If the transaction is recurring, add to seperate table
            else {
                $now = date('Y-m-d'); // Get today's date

                if(!empty($startDate)) {
                    // Check if the date given is vaild format - mainly for Safari date inputs with no datepicker
                    if(!$this->isValidDate($startDate)) {
                        AuditLog::add($audit."invalid start date");
                        return "Recurring start date is invalid. Format: YYYY-MM-DD.";
                    }
                    // Check if start date is before today
                    $startDateChk = date($startDate);
                    if($startDateChk < $now) {
                        AuditLog::add($audit."start date prior today's date.");
                        return "Repeat start date must be at least today's date: ".$now;
                    }
                    $startDate = New DateTime($startDate);
                    $startDate = $startDate->format('Y-m-d'); // Format start date from DateTime to mysql format YYYY-MM-DD
                } else {
                    AuditLog::add($audit."No start date selected.");
                    return "No start date has been set.";
                }

                // If the transaction is not repeated indefinitely, i.e. has an end date
                if($indefinite == 0) {
                    if(!empty($endDate)) {
                        // Validate the date input - mainly for Safari date inputs
                        if(!$this->isValidDate($endDate)) {
                            AuditLog::add($audit."invalid end date");
                            return "Recurring end date is invalid. Format: YYYY-MM-DD";
                        } else {
                            // Check if the end date is before today, if so return error
                            $endDateChk = date($endDate);
                            if($endDateChk < $now) {
                                AuditLog::add($audit."end date prior today's date.");
                                return "Repeat end date must be at least today's date: ".$now;
                            }
                            $endDate = New DateTime($endDate);
                            $endDate = $endDate->format('Y-m-d');  // Format date from DateTime to mysql format YYYY-MM-DD
                        }
                    } else {
                        // If 'Repeat Indefilitely' hasn't been selected and an end date hasn't been specified, return error
                        AuditLog::add($audit."No end date selected.");
                        return "No end date has been specified. If there is no end date, select 'Repeat Indefinitely'.";
                    }
                } else {
                    $endDate = null; // If 'Repeat Indefinitely' is selected, set end date to NULL
                }


                try {
                    $stmt = $this->query("INSERT INTO recurTransactions (
                                            accountID,userID,categoryID,`type`,amount,startDate,endDate,frequency,indefinite) 
                                          VALUES (
                                            :accountID,:userID,:categoryID,:tType,:amount,:startDate,:endDate,:freq,:indefinite)");
                    $stmt->bindParam(':accountID',$accountID, PDO::PARAM_INT);
                    $stmt->bindParam(':userID',$this->userID);
                    $stmt->bindParam(':categoryID',$categoryID, PDO::PARAM_INT);
                    $stmt->bindParam(':tType',$type);
                    $stmt->bindParam(':amount',$amount);
                    $stmt->bindParam(':startDate',$startDate);
                    $stmt->bindParam(':endDate',$endDate);
                    $stmt->bindParam(':freq',$freq);
                    $stmt->bindParam(':indefinite',$indefinite);
                    $stmt->execute();
                    $this->recurID = $this->conn->lastInsertId();
                    $stmt = null;

                    // If the start date is today, add the transaction into transactions table along with the last inserted ID (recurID)
                    if($startDate == $now) {
                        if($result = $this->addToTransactionsTable() !== true) {
                            AuditLog::add($audit."in recur - ".$result);
                            return $result;
                        }
                    }
                    return true;

                } catch(PDOException $err) {
                    AuditLog::add($audit.$err->getMessage());
                    return "Seems to be a server problem. Please contact Administrator.";
                }
            }
        }
    }


    // Adds transaction variables into database table
    // Used within addNew() function
    // Updates account balance and category usage
    private function addToTransactionsTable() {
        try {
            $stmt = $this->query("
            INSERT INTO transactions(
                userID, 
                accountID, 
                categoryID, 
                transactionDate, 
                transactionType, 
                transactionAmount, 
                description, 
                receiptNo,
                balance,
                recurID) 
            VALUES (
                :userID, 
                :accountID, 
                :categoryID, 
                NOW(), 
                :transactionType, 
                :transactionAmount, 
                :description, 
                :receiptNo,
                :balance,
                :recurID)");
            $stmt->bindParam(':userID', $this->userID);
            $stmt->bindParam(':accountID', $this->accountID, PDO::PARAM_INT);
            $stmt->bindParam(':categoryID', $this->categoryID, PDO::PARAM_INT);
            $stmt->bindParam(':transactionType', $this->transType);
            $stmt->bindParam(':transactionAmount', $this->amount, PDO::PARAM_STR);
            $stmt->bindParam(':description', $this->description);
            $stmt->bindParam(':receiptNo', $this->receiptNo);
            $stmt->bindParam(':balance', $this->balance, PDO::PARAM_STR);
            $stmt->bindParam(':recurID', $this->recurID, PDO::PARAM_INT);
            $stmt->execute();
            $stmt = null;

            $result = $this->updateBalance();
            if($result !== true)
                return $result;

            try {
                //Updating category usage
                $stmt = $this->query("UPDATE categories SET catUses = catUses + 1 WHERE categoryID = :categoryID");
                $stmt->bindParam(':categoryID', $categoryID);
                $stmt->execute();
                $stmt = null;
            } catch(PDOException $err) {
                AuditLog::add("Query failure on addNew() function - ".$err->getMessage());
                echo $err->getMessage();
                return "Seems to be a server problem. Please contact administrator.";
            }

            AuditLog::add("New transaction added.");
            return true;

        } catch(PDOException $err) {
            AuditLog::add("Database failure for add transaction - ".$err->getMessage());
            return "Seems to be a server problem. Contact administrator.";
        }
    }


    // Checks the user's recurring transactions and adds any necessary records into the transactions table
    // Called upon user login
    public function checkRecurringTransactions() {
        $insertCount  = 0;
        $insertValues = "";
        $now          = date("Y-m-d");

        try {
            $stmt = $this->query("SELECT ID, accountID, userID, categoryID, `type`, amount, startDate, frequency
                                    FROM recurTransactions 
                                    WHERE userID = :userID AND (endDate >= CURRENT_DATE() OR indefinite = 1) AND inactive = 0");
            $stmt->execute(array(':userID' => $this->userID));
            if($stmt->rowCount() == 0) {
                Session::set("recurCheck","complete");  // Check has been completed for current session - avoids needless checking
                Session::set("recurCount",0);
                return true; // No recurring transactions
            } else {
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    // returns list of dates that need to be added
                    $newTransactionDates = $this->checkExisting($row['ID'], $row['frequency'], $now, $row['startDate']);
                    $accBalance = $this->getAccountBalance($row['accountID']);

                    // If multiple dates have been returned
                    if(is_array($newTransactionDates)) {
                        for($i=0; $i<count($newTransactionDates); $i++) {
                            $insertCount++;
                            if(!empty($insertValues)) {
                                $insertValues .= ", ";
                            }
                            $accBalance += $row['amount']; // Get updated account balance after transaction is applied

                            // Build insert values for query - not using local addNew function as it would taken substantial time if multiple records are to be added
                            // This way the insert values are built and inserted once
                            $insertValues .= "(".$row['accountID'].", ".$row['userID'].", ".$row['categoryID'].", \"".$row['type']."\", 
                                \"".$newTransactionDates[$i]."\", ".$row['amount'].", ".$row['ID'].", ".$accBalance.")";
                        }
                        // Update the account balance in db
                        if($result = $this->updateBalance($row['accountID'],$accBalance) !== true) {
                            return $result;
                        }

                    } else if($newTransactionDates !== false) { // If one date was returned
                        $insertCount++;
                        if(!empty($insertValues)) {
                            $insertValues .= ", ";
                        }
                        $accBalance += $row['amount'];  // Get updated account balance after transaction is applied

                        $insertValues .= "(".$row['accountID'].", ".$row['userID'].", ".$row['categoryID'].", \"".$row['type']."\", 
                            \"".$newTransactionDates."\", ".$row['amount'].", ".$row['ID'].", ".$accBalance.")";

                        // Update the account balance in db
                        if($result = $this->updateBalance($row['accountID'],$accBalance) !== true) {
                            return $result;
                        }
                    }
                }

                if(empty($insertValues)) { // If no new transactions need to be added
                    Session::set("recurCheck","complete");
                    Session::set("recurCount",$insertCount); // used for dashboard screen
                    return true;
                }

                try {
                    $stmt = $this->query("INSERT INTO transactions(accountID,userID,categoryID,transactionType,transactionDate,transactionAmount,recurID,balance) VALUES $insertValues");
                    $stmt->execute();
                    $stmt = null;
                    Session::set("recurCheck","complete");
                    Session::set("recurCount",$insertCount); // used for dashboard screen
                    return true;
                } catch(PDOException $err) {
                    AuditLog::add("Database error on inserting recurring transactions checkRecurringTransactions() - ".$err->getMessage());
                    return "Looks like there's an internal server problem. Please contact administrator.";
                }
            }
        } catch(PDOException $err) {
            AuditLog::add("Check Recurring transactions failed - ".$err->getMessage());
            return "Looks like there's an internal server problem. Please contact Administrator.";
        }
    }


    // Checks if the recurring transaction has already been added, if not, return the dates of the transactions to be added
    // Returns false => No new transactions, date => single transaction to be added, array => multiple transactions to be added
    public function checkExisting($recurID,$freq,$now,$recurDate) {
        // Array holding dates of transactions that need to be inserted
        $datesArray = array();

        // array with frequencies ($freq) as key, and array of values holding the date iteration value, dateDifference format and subtractDate format
        $ctrl = array(
            365 => array(1,  "%a",  "days"),   // Daily
            52  => array(7,  "%a",  "days"),   // Weekly
            12  => array(1,  "%m",  "months"), // Monthly
            1   => array(1,  "%y",  "years")   // Yearly
        );

        try {
            // Gets the last available record of recurring transaction, returns the date it occurred
            $stmt = $this->query("SELECT MAX(date_format(transactionDate, '%Y-%m-%d')) as transDate 
                                  FROM transactions 
                                  WHERE recurID = :recurID 
                                  ORDER BY transDate DESC");
            $stmt->bindColumn('transDate', $transactionDate);
            $stmt->execute(array(':recurID' => $recurID));
            $stmt->fetch();
            $stmt= null;

            // If no previous transactions exists, use the start date instead
            if(empty($transactionDate)) {
                $interval = Calculate::dateDifference($recurDate,$now, $ctrl[$freq][1]);
                if($interval == $ctrl[$freq][0]) {
                    return array($recurDate, Calculate::subtractDate($recurDate, -$ctrl[$freq][0], $ctrl[$freq][2])); // Initial recur start date included as no transaction exists for it
                }
                else if($interval > $ctrl[$freq][0]) {
                    array_push($datesArray,$recurDate);
                    for($i=1; $i<=$interval; $i++) {
                        // check put in for weekly, to insert every 7th iteration. Makes no difference for the other frequencies
                        if($i % $ctrl[$freq][0] == 0) {
                            array_push($datesArray, Calculate::subtractDate($recurDate, -$i, $ctrl[$freq][2]));
                        }
                    }
                    return $datesArray;
                }
                return false;
            }

            // If previous transaction exists, use transaction date for calculation
            $interval = Calculate::dateDifference($transactionDate, $now, $ctrl[$freq][1]);
            if($interval >= 1) {
                if($interval == 1) {
                    return Calculate::subtractDate($transactionDate, -$ctrl[$freq][0], $ctrl[$freq][2]);
                } else {
                    for($i=1; $i<=$interval; $i++) {
                        if($i % $ctrl[$freq][0] == 0) {
                            array_push($datesArray, Calculate::subtractDate($transactionDate, -$i, $ctrl[$freq][2])); // negative $i to add on the date instead of subtract
                        }
                    }
                    return $datesArray;
                }
            }
            return false;

        } catch(PDOException $err) {
            AuditLog::add("checkExisting transactions failed - ".$err->getMessage());
            return false;
        }
    }


    // Displays recent activity table on Dashboard page
    public function renderDashRecentActivity() {
        // Get the first 8 most recent transactions
        $recentTransactions = $this->getMostRecent(10);
        if(is_array($recentTransactions)) {
            $tbl = "";

            foreach($recentTransactions as $id => $row) {
                $tbl .= "<tr>
                    <td>".$row['date']."</td>
                    <td>".$row['account']."</td>
                    <td>".$row['category']."</td>
                    <td style=\"text-align:center\">".($row['type']=='income' ? money_format('%n',$row['amount']) : '')."</td>
                    <td style=\"text-align:center\">".($row['type']=='expense' ? money_format('%n',$row['amount']) : '')."</td>
                    <td style=\"text-align:center\">".money_format('%n',$row['balance'])."</td>
                 </tr>";
            }
            return $tbl;
        } else {
            return "No information to show.";
        }
    }


    // Used in transactions page - returns an array containing a table displaying 5 most recent transactions,
    // total income & expenses for the recent transactions only
    public function renderTransRecentActivity() {
        $recentTrans = $this->getMostRecent(5); // get 5 most recent transactions data
        $recent = array( // return array
            "table"      => "",
            "ttlIncome"  => 0.00,
            "ttlExpense" => 0.00
        );

        if(is_array($recentTrans)) {
            foreach($recentTrans as $transID => $data) {
                //increment income & expenses
                if($data['type'] == "income")
                    $recent['ttlIncome'] += $data['amount'];
                else if($data['type'] == "expense")
                    $recent['ttlExpense'] += $data['amount'];

                // add table row to array
                $recent['table'] .= "<tr>";
                $recent['table'] .=    "<td>".$data['date']."</td>";
                $recent['table'] .=    "<td>".$data['category']."</td>";
                $recent['table'] .=    "<td>".ucfirst($data['type'])."</td>";
                $recent['table'] .=    "<td>".$data['account']."</td>";
                $recent['table'] .=    "<td>".money_format('%n', $data['amount'])."</td>";
                $recent['table'] .=    "<td><a href=\"index.php?id=".Crypt::encrypt($transID)."&action=".Crypt::encrypt('delete')."\"><span class=\"fa fa-trash-o\" aria-hidden=\"true\" style='color:red;'></span></a></td>";
                $recent['table'] .= "</tr>";
            }
        } else {
            // return error message
            $recent['table'] = "<tr><td>".$recentTrans."</td><td></td><td></td><td></td><td></td><td></td></tr>";
        }
        return $recent;
    }


    // Render the recur transactions table on transactions page
    public function renderRecurTrans() {
        $recurTransactions = $this->getUpcomingRecur(5);

        if(is_array($recurTransactions)) {
            $recurTable = "";
            $frequency  = array(365=>"Daily", 52=>"Weekly", 12=>"Monthly", 1=>"Yearly");

            foreach($recurTransactions as $id => $fields) {
                if($fields['type'] == "expense")
                    $fields['amount'] = money_format('%n', $fields['amount']);
                if(empty($fields['endDate']))
                    $fields['endDate'] = "Indefinite";

                $recurTable .= "<tr>";
                $recurTable .= "<td>".$fields['startDate']."</td>";
                $recurTable .= "<td>".$fields['account']."</td>";
                $recurTable .= "<td>".$fields['category']."</td>";
                $recurTable .= "<td>".$fields['amount']."</td>";
                $recurTable .= "<td>".$fields['endDate']."</td>";
                $recurTable .= "<td>".$frequency[$fields['frequency']]."</td>";
                $recurTable .= "</tr>";
            }
        } else {
            $recurTable = "<tr><td>".$recurTransactions."</td><td></td><td></td><td></td><td></td><td></td></tr>";
        }
        return $recurTable;
    }


    // Delete a transaction
    public function deleteTransaction($transID) {
        if(empty($transID)) {
            throw new Exception("Empty transaction ID on delete.");
        }
        try {
            $stmt = $this->query("DELETE FROM transactions WHERE transactionID = :transactionID AND userID=".$this->userID);
            $stmt->bindParam(':transactionID', $transactionID, PDO::PARAM_INT);
            $stmt->execute();
            if($stmt->rowCount() > 0) {
                $stmt = null;
                AuditLog::add("Transaction deleted by user");
                return true;
            } else {
                AuditLog::add("Delete transaction returned no rows.");
                return "Something went wrong. Please contact administrator.";
            }
        } catch(PDOException $err) {
            AuditLog::add("Delete transaction query failed - ".$err->getMessage());
            return "It seems to be a server problem. Please contact administrator.";
        }
    }
}
