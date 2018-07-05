<?php
require_once("Database.php");
require_once("Crypt.php");

class Budgets
{
    private $userID;
    public $conn;

    // 2D array containing the total budget, and an array with ID => budget
    // e.g. "total"=>5000, "budgets"=>array(3=>400.00, 4=>350.00, 5=>125.50)
    public $accountBudgets;
    public $categoryBudgets;

    public $incomeExpenses;

    public function __construct(){
        $db = New Database();
        $conn = $db->openConnection();

        $this->conn = $conn;
        $this->userID = $_SESSION['userID'];

        $this->accountBudgets  = $this->getBudgets("SELECT accountID as ID,accountBudget as budget FROM accounts WHERE userID=:userID AND accountBudget IS NOT NULL AND accountBudget <> 0");
        $this->categoryBudgets = $this->getBudgets("SELECT categoryID as ID, catBudget as budget FROM categories WHERE userID=:userID AND catBudget IS NOT NULL AND catBudget <> 0");
    }


    private function query($sql) {
        $stmt = $this->conn->prepare($sql);
        return $stmt;
    }


    // Overall budget variance used in navbar statistic (based on account budgets)
    // Returns variance, budget and expenses
    public function variance(bool $account=true) {
        if($account === true)
            $query = "SELECT SUM(accountBudget) FROM accounts WHERE userID=:userID AND accountBudget IS NOT NULL"; // Account budget variance
        else
            $query = "SELECT SUM(catBudget) FROM categories WHERE userID=:userID AND catBudget IS NOT NULL"; // Category budget variance
        try {
            $stmt = $this->query($query);
            $stmt->execute(array(':userID'=>$this->userID));
            $bud = $stmt->fetch()[0];
            $stmt = null;
            $incExp = $this->getMonthlyIncomeExpense();
            if(is_array($incExp)) {
                if($bud == null || $bud == 0 || empty($bud)) {
                    AuditLog::add("Budget variance calculation failed - no budgets set");
                    return 0;
                } else {
                    $variance = $bud - (0-$incExp['expenses']);
                    return array("variance"=>$variance, "bud"=>$bud, "exp"=>$incExp['expenses']);
                }
            } else {
                AuditLog::add("Budget variance calculation failed");
                return 0;
            }
        } catch(PDOException $err) {
            AuditLog::add("Budget variance calculation failed - ".$err->getMessage());
            return 0;
        }
    }


    // Returns the total income and expenses for the current month
    public function getMonthlyIncomeExpense() {
        try {
            $stmt = $this->query("SELECT SUM(CASE WHEN transactionType = 'income' THEN transactionAmount ELSE null END) as income,
	                                     SUM(CASE WHEN transactionType = 'expense' THEN transactionAmount ELSE null END) as expenses
                                  FROM transactions 
                                  WHERE userID=:userID 
                                  AND MONTH(transactionDate) = MONTH(CURRENT_DATE) AND YEAR(transactionDate) = YEAR(CURRENT_DATE)");
            $stmt->bindColumn('income', $income, PDO::PARAM_STR);
            $stmt->bindColumn('expenses', $expenses, PDO::PARAM_STR);
            $stmt->execute(array(':userID'=>$this->userID));
            $stmt->fetch();
            if($stmt->rowCount()) {
                return array("income"=>round($income,2), "expenses"=>round($expenses,2));
            } else {
                AuditLog::add("Get monthly income and expenses failed - no transactions made in current month.");
                return false;
            }
        } catch(PDOException $err) {
            AuditLog::add("Budget obj monthly income & expenses failed - ".$err->getMessage());
            return "Looks like an internal server problem, please contact administrator.";
        }
    }


    // Returns array with total budget and an array with associated budgets for each category/account (ID => budget)
    // Requires an SQL query that selects the ID and budget using alias' 'ID' and 'budget'
    public function getBudgets($query) {
        $arr = array();
        $total = 0;
        try {
            $stmt = $this->query($query);
            $stmt->execute(array(':userID'=>$this->userID));
            if($stmt->rowCount()) {
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    $total += $row['budget'];
                    $arr[$row['ID']] = $row['budget'];
                }
                return array("total"=>round($total,2), "budgets"=>$arr);
            } else {
                return false;
            }
        } catch(PDOException $err) {
            AuditLog::add("Budget obj monthly budgets failed - ".$err->getMessage());
            return "Looks like an internal server problem, please contact administrator.";
        }
    }


    // returns array only including values with table columns, i.e. begins with <td>
    // Used for 'generateVarianceTable' function
    private function filterAr($arr) {
        return array_filter($arr, function($x) { return substr($x,0,4) == "<td>"; });
    }


    // Adds an additional table column if there is an odd number - evens it out for front end
    // Used for 'generateVarianceTable' function
    private function finish($arr) {
        if(count($this->filterAr($arr)) % 2 !== 0)
            return "<td>&nbsp;</td><td>&nbsp;</td>";
    }


    // Function to generate table of over and under budgeted accounts or categories, with their calculated budget variances,
    // worked out by subtracting 0-transaction total from the budget
    // $query1 will work out the transaction total for the given account/category, and $query2 will fetch the name of it.
    public function generateVarianceTable($query1, $query2, $budgetArr) {
        $budgetVariances = array();
        try {
            $stmt = $this->query($query1);
            $stmt->bindColumn('total',$transactionTtl);

            foreach($budgetArr as $ID => $bgt) {
                $stmt->execute(array(':userID'=>$this->userID, ':ID'=>$ID));
                $stmt->fetch();
                $budgetVariances[$ID] = $bgt-(0-$transactionTtl);
            }
            $stmt = null;

            try {
                $stmt  = $this->query($query2);
                $over  = array("<tr>");
                $under = array("<tr>");

                foreach($budgetVariances as $id => $variance) {
                    $stmt->execute(array(':userID'=>$this->userID, ':ID'=>$id));
                    $name = $stmt->fetch()[0];
                    // Db record - 2 per table row
                    $tblCol = "<td><i>$name: &nbsp;</i></td><td>".money_format('%n',$variance)."</td>";

                    if($variance < 0) {
                        // if there are two columns, add a row ending
                        if(count($this->filterAr($over)) % 2 == 0)
                            $over[] = "</tr><tr>";
                        $over[] = $tblCol;
                    } else {
                        if(count($this->filterAr($under)) % 2 == 0)
                            $under[] = "</tr><tr>";
                        $under[] = $tblCol;
                    }
                }
                $stmt = null;

                // Add a blank row if it lands on an odd number
                $over[]  = $this->finish($over);
                $under[] = $this->finish($under);
                $over[]  = $under[] = "</tr>";

                return array("over"  => implode($over),
                             "under" => implode($under));

            } catch(PDOException $err) {
                AuditLog::add("2nd query in generateVarianceTable budgets obj - ".$err->getMessage());
                return "Looks like an internal server problem. If problem persists, contact administrator..";
            }
        } catch(PDOException $err) {
            AuditLog::add("Budget generate variance table failed - ".$err->getMessage());
            return "Looks like an internal server problem. If problem persists, contact administrator..";
        }
    }


    // Used for budgets page to generate form inputs for accounts and categories
    // Returns array with all fields, budgeted & non-budgeted
    public function generateForm($query, $inputName) {
        $stmt = $this->query($query);
        $stmt->bindParam(':userID',$this->userID);
        $stmt->execute();

        $allFieldsArr   = array();
        $budgeted       = "";
        $nonbudgeted    = "";
        $budgetedCount  = 0;
        $nonBudgetCount = 0;

        if($stmt->rowCount() > 0) {
            while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {

                $ID = Crypt::encrypt($row['ID']);
                $allFieldsArr[$ID] = $row['BUDGET']; // Save account ID => account name in array for POST to save changes

                // Non-budgeted Accounts & Budgeted Accounts (else)
                if($row['BUDGET'] == null || $row['BUDGET'] == 0) {
                    $nonBudgetCount++;
                    $nonbudgeted .= "<div class=\"row-fluid\">";
                    $nonbudgeted .=    "<div class=\"accountName col-xs-8\">".$row['NAME']."</div>";
                    $nonbudgeted .=    "<div class=\"accountBudget col-xs-4\">";
                    $nonbudgeted .=        "<input type=\"text\" class=\"form-control\" name=\"$inputName-".$ID."\" id=\"txtAccBudget-".$ID."\" title=\"Budget Amount\" placeholder=\"0.00\" />";
                    $nonbudgeted .=    "</div>";
                    $nonbudgeted .= "</div>";
                    $nonbudgeted .= "<div class=\"clearfix seperator\"></div>";
                } else {
                    $budgetedCount++;
                    $budgeted .= "<div class=\"row-fluid\">";
                    $budgeted .=    "<div class=\"accountName col-xs-8\">".$row['NAME']."</div>";
                    $budgeted .=    "<div class=\"accountBudget col-xs-4\">";
                    $budgeted .=        "<input type=\"text\" class=\"form-control\" name=\"$inputName-".$ID."\" id=\"txtAccBudget-".$ID."\" title=\"Budget Amount\" value=\"".txtSafe($row['BUDGET'])."\" />";
                    $budgeted .=    "</div>";
                    $budgeted .= "</div>";
                    $budgeted .= "<div class=\"clearfix seperator\"></div>";
                }
            }
        } else {
            return array("response"    => false,
                         "allFields"   => $allFieldsArr,
                         "budgeted"    => array("No fields found", 0),
                         "nonBudgeted" => array("No fields found", 0));
        }
        $stmt = null;

        // Returns array with all fields as second value (encrypted ID with Budget as value), all budgeted & non-budgeted inputs with their respective counters
        return array("response"    => true,
                     "allFields"   => $allFieldsArr,
                     "budgeted"    => array($budgeted,$budgetedCount),
                     "nonBudgeted" => array($nonbudgeted,$nonBudgetCount));
    }


    // Updates all account or category budgets from form
    // Takes the update query as param 1 with bound variables :budget & :ID
    // Param 2 is an array of the category/account encrypted ID's as key, and budget as value, e.g. "WkVkeVNEMFNQNXJRSm90LzlQalp6QT09" => 250
    // Param 3 is the constant used in the form names along with enc ID, e.g. name="CATEGORY_FORM-WkVkeVNEMFNQNXJRSm90LzlQalp6QT09".
    public function updateBudgets($query, $allInputs, $FORM_CONSTANT) {
        $audit = "Update category budgets failed - ";

        try {
            $stmt = $this->query($query); // prepare update query

            // If any inputs exists
            if(!empty($allInputs)) {
                foreach($allInputs as $ID => $budget) {
                    $input = strip_tags(trim($_POST[$FORM_CONSTANT.'-'.$ID])); // input name consists of constant with hyphenated encrypted ID
                    // Iterate through each category, if set budget doesn't match DB value, validate & run update query
                    if(!empty($input)) {
                        if($budget !== $input) {
                            // If budget is not a number
                            if(!filter_var($input, FILTER_VALIDATE_FLOAT)) {
                                AuditLog::add($audit."non alpha-numeric characters detected.");
                                $error = Form::error_alert("Budgets can only contain numbers.");
                                break;
                            }
                            // if budget is below 0
                            if($input < 0) {
                                AuditLog::add($audit."budget is less than 0.");
                                $error = Form::error_alert("Budgets cannot be below 0.");
                                break;
                            }
                            // If no errors returned, execute update query
                            $stmt->execute(array(':budget'=>$input, ':ID'=>Crypt::decrypt($ID)));
                        }
                    } else {
                        // If left empty, update as NULL (bug fix)
                        $stmt->execute(array(':budget'=>null, ':ID'=>Crypt::decrypt($ID)));
                    }
                }

                $stmt = null;

                if(!isset($error))
                    return Form::success_alert("Budget(s) successfully updated."); // Success message
                else
                    return $error;
            } else {
                return Form::error_alert("Cannot save changes - no fields found.");
            }

        } catch(PDOException $err) {
            AuditLog::add($audit.$err->getMessage());
            return Form::error_alert("Looks like a server problem. If problem persists, contact administrator.");
        }
    }


    // Returns an array containing the individual category/accounts labels, budgets & actual expenditures
    // Used for bar charts in budgets page
    public function monthIndivVariance(bool $accounts=false) {
        $data = array(array(), array(), array()); // Data for stacked bar chart - labels, budget, actual
        $temp = array();

        try {
            if($accounts == false) {
                $stmt = $this->query("SELECT SUM(transactionAmount), catName
                                  FROM transactions LEFT JOIN categories on transactions.categoryID = categories.categoryID
                                  WHERE transactions.userID=:userID AND transactionType = 'expense' AND transactions.categoryID=:ID 
                                  AND MONTH(transactionDate) = MONTH(CURRENT_DATE) AND YEAR(transactionDate) = YEAR(CURRENT_DATE)");
                $stmt1 = $this->query("SELECT catName FROM categories WHERE categoryID=:ID AND userID=:userID");
                $fields = $this->categoryBudgets['budgets'];
            } else {
                $stmt = $this->query("SELECT SUM(transactionAmount)
                                  FROM transactions 
                                  WHERE transactions.userID=:userID AND transactionType = 'expense' AND transactions.accountID=:ID 
                                  AND MONTH(transactionDate) = MONTH(CURRENT_DATE) AND YEAR(transactionDate) = YEAR(CURRENT_DATE)");
                $stmt1 = $this->query("SELECT accountName FROM accounts WHERE accountID=:ID AND userID=:userID");
                $fields = $this->accountBudgets['budgets'];
            }

            if(!empty($fields)) { // If any budgets have been set yet
                // Iterate through each budget and fetch total expenses for the current month, i.e. execute query
                foreach($fields as $ID => $budget) {
                    $stmt->execute(array(':userID'=>$this->userID, ':ID'=>$ID));
                    $actual = 0-($stmt->fetch()[0]);
                    $budget -= $actual; // Subtract actual expenditure from budget

                    if(empty($actual)) { $actual = 0.00; }
                    if($budget<0)      { $budget = 0.00; } // If over budget, set to 0

                    // Execute query to fetch label
                    $stmt1->execute(array(':ID'=>$ID, ':userID'=>$this->userID));
                    $name = $stmt1->fetch()[0];
                    // insert label, budget and actual into array, e.g. 1=>array("General", 400, 359.50)
                    $temp[$ID] = array($name,$budget,$actual);
                }

                // Push each of the labels, budgets and actual into parallel arrays in $data
                // e.g. 0=>array("General", "Groceries"), 1=>array(200, 150), 2=>array(87.55, 95.10)
                foreach($temp as $ID => $values) {
                    for($x=0;$x<3;$x++) {
                        array_push($data[$x],$values[$x]);
                    }
                }

                return array("labels"=>$data[0], "budget"=>$data[1], "actual"=>$data[2]);
            } else {
                // If no budgets have been set
                AuditLog::add("Budgets monthIndivVariance failed - no budgets set.");
                return false;
            }
        } catch(PDOException $err) {
            AuditLog::add("mthCategoryVariance sql failed - ".$err->getMessage());
            return false;
        }
    }



}