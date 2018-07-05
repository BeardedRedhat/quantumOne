<?php

require_once("Database.php");
require_once("Session.php");
require_once("Crypt.php");
require_once("Table.php");

/**
 * Created by PhpStorm.
 * User: AaronMcf
 * Date: 29/03/2018
 * Time: 22:29
 */
class Admin
{
    private $userID;
    private $conn;

    public function __construct()
    {
        $db = New Database();
        $this->conn = $db->openConnection();
        $this->userID = $_SESSION['userID'];
        Session::adminCheck();
    }


    private function query($sql) {
        $stmt = $this->conn->prepare($sql);
        return $stmt;
    }


    // Returns stats for transactions, accounts opened and users for today, month and all time
    // e.g. "transactions"=>array(20,456,2165), "users"=>array(0,4,164)
    // Used for renderSystemStats() function
    private function stats() {
        // Final array holding all values in chronological order, i.e. today's first and total last
        $stats = array("transactions"=>array(), "accounts"=>array(), "users"=>array());
        // Array holding the table name, date and ID fields from transactions, accounts and users
        $sqlValues = array('transactions' => array('transactionDate', 'transactionID'),
                           'accounts'     => array('openingDate',     'accountID'),
                           'users'        => array('registerTime',    'userID'));
        try {
            // Iterate through each table and execute query to fetch stats - gets values for today, for month and all time
            foreach($sqlValues as $tableName => $values) {
                $stmt = $this->query("SELECT 
                                        COUNT(CASE WHEN DATE($values[0]) = CURRENT_DATE() THEN $values[1] ELSE NULL END) as today,
                                        COUNT(CASE WHEN MONTH($values[0]) = MONTH(CURRENT_DATE) AND YEAR($values[0]) = YEAR(CURRENT_DATE) THEN $values[1] ELSE NULL END) as `month`,
                                        COUNT($values[1]) as total
                                      FROM $tableName");
                $stmt->bindColumn('today', $today);
                $stmt->bindColumn('month', $month);
                $stmt->bindColumn('total', $total);
                $stmt->execute();
                $stmt->fetch();
                $stats[$tableName] = array($today,$month,$total); // update array
                $stmt = null;
            }
            return $stats;
        } catch(PDOException $err) {
            AuditLog::add("Admin stats function failed - ".$err->getMessage());
            return 0;
        }
    }


    // Generates HTML and values for admin system statistics at the top of the admin home page
    public function renderSystemStats() {
        $stats = $this->stats();
        $html = "";
        foreach($stats as $table => $values) {
            if($table == "accounts") { $table = "Accounts Opened";  } // Change the titles of accounts and uses
            if($table == "users")    { $table = "Users Registered"; }

            $html  .=  "<div class=\"col-lg-4 col-md-4 col-sm-4 col-xs-12\">
                            <div class=\"panel panel-default stat-panel\">
                                <div class=\"panel-body\">
                                    <div class=\"col-lg-6 col-md-6 col-sm-12 col-xs-12 current-stat\" align=\"center\">
                                        <span style=\"font-size:12px;\"><b>".ucfirst($table)." Today</b></span><br />
                                        <span style=\"font-size:40px; color: #263745\">".number_format($values[0])."</span>
                                    </div>
                                    <div class=\"col-lg-6 col-md-6 col-sm-12 col-xs-12 overall-stat\" align=\"center\">
                                        <div class=\"col-xs-12\"><b>This Month</b></div>
                                        <div class=\"col-xs-12\">".number_format($values[1])."</div>
                                        <div class=\"col-xs-12\"><b>All Time</b></div>
                                        <div class=\"col-xs-12\">".number_format($values[2])."</div>
                                    </div>
                                </div>
                            </div>
                        </div>";
        }
        return $html;
    }


    // Add new currency form
    public function addNewCurrency($name,$code,$label,$locale) {
        $audit = "Add new currency failed - ";

        if(!empty($name)) {
            if(!ctype_alpha(str_replace(' ','',$name))) {
                AuditLog::add($audit."non alphabetic characters detected in currency name.");
                return "Only alphabetic characters allowed in currency name.";
            }
            if(strlen($name)>40) {
                AuditLog::add($audit."name character length exceeded. Max: 40.");
                return "Name character limit exceeded. Max: 40.";
            }
        } else {
            AuditLog::add($audit."empty currency name.");
            return "Empty currency name.";
        }

        if(!empty($code)) {
            if(!ctype_alpha($code)) {
                AuditLog::add($audit."no special characters allowed in code.");
                return "Only alphabetic characters allowed in currency code.";
            }
            if(strlen($code)>3) {
                AuditLog::add($audit."code character length exceeded. Max: 3.");
                return "Code character limit exceeded. Max: 3.";
            }
        } else {
            AuditLog::add($audit."empty currency code.");
            return "Empty currency code.";
        }

        $code = strtoupper($code); // convert code to uppercase

        if(!empty($label)) {
            if(strlen($label)>3) {
                AuditLog::add($audit."label character length exceeded. Max: 3.");
                return "Label character limit exceeded. Max: 3.";
            }
        } else {
            AuditLog::add($audit."empty currency label.");
            return "Empty currency label.";
        }

        if(!empty($locale)) {
            if(strlen($locale)>10) {
                AuditLog::add($audit."locale character length exceeded. Max: 10.");
                return "Locale character limit exceeded. Max: 10.";
            }
        } else {
            AuditLog::add($audit."empty currency name.");
            return "Empty currency locale.";
        }

        try {
            $stmt = $this->query("INSERT INTO currency(currencyType,currencyCode,currencyLabel,locale) VALUES(:curName,:code,:label,:locale)");
            $stmt->execute(array(':curName'=>$name, ':code'=>$code, ':label'=>$label, ':locale'=>$locale));
            $stmt = null;
            return true;
        } catch(PDOException $err) {
            AuditLog::add($audit.$err->getMessage());
            return "Server problem :(";
        }
    }


    // Showing all user enquiries submitted via the contact page in the panel
    // Has a mailto: link on the user email to allow instant reply
    public function showEnquiries($limit=20) {
        $messages = "";

        try {
            $stmt = $this->query("SELECT contactID, DATE_FORMAT(`date`, '%d/%m/%Y %H:%i:%s') as `date`, subject, message, users.email, CONCAT_WS(' ',users.firstName,users.lastname) as fullName
                                  FROM contact LEFT JOIN users ON contact.userID = users.userID
                                  WHERE contact.seen = 0 ORDER BY contact.date DESC LIMIT $limit");
            $stmt->execute();
            if($stmt->rowCount() > 0) {
                while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                    $encID = Crypt::encrypt($row['contactID']);
                    $messages .= "  <div class=\"col-xs-12 div-enq\">
                                        <div class=\"title\"><span class=\"fa fa-clock-o\" aria-hidden=\"true\"></span> ".$row['date']."
                                            <span class='pull-right'><a href='/admin/index.php?id=".$encID."'><span class='fa fa-times' aria-hidden='true' style='color:red'></span></a></span>
                                        </div>
                                        <span>
                                            <span class=\"fa fa-user-o\" aria-hidden=\"true\"></span> ".$row['fullName']."&nbsp;&nbsp;
                                            <span class=\"fa fa-envelope-o\" aria-hidden=\"true\"></span> <a href=\"mailto:".Crypt::decryptWithKey($row['email'],"packmyboxwithfivedozenliquorjugs")."\" style=\"text-decoration:underline;\">Reply</a>
                                        </span>
                                        <span style=\"display:block; margin-top:5px\"><b>".$row['subject']."</b></span>
                                        <div class=\"col-xs-12\">".$row['message']."</div>
                                    </div>";
                }
                return $messages;
            } else {
                AuditLog::add("No user enquiries generated.");
                return "No information to show.";
            }
        } catch(PDOException $err) {
            AuditLog::add("Show admin enquiries failed - ".$err->getMessage());
            return "Server problem :(";
        }
    }


    // If admin user clicks the 'x' on an enquiry (meaning they've read it), set db value to 1
    public function markAsRead($enqID) {
        $enqID = Crypt::decrypt($enqID);
        try {
            $stmt = $this->query("UPDATE contact SET seen=1 WHERE contactID=:contactID");
            $stmt->execute(array(':contactID'=>$enqID));
            $stmt = null;
            return true;
        } catch(PDOException $err) {
            AuditLog::add("markAsRead enquiry failed ($enqID) - ".$err->getMessage());
            return "Server problem :(";
        }
    }


    // Generates a table list of all currencies, along with an edit button
    public function showCurrencies() {
        $table = "";
        try {
            $stmt = $this->query("SELECT currencyID, currencyType, currencyCode, currencyLabel, locale FROM currency ORDER BY currencyType ASC");
            $stmt->execute();
            while($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
                $table .= " <tr>
                                <td><a href='index.php?id=".Crypt::encrypt($row['currencyID'])."'><span class=\"fa fa-pencil\" aria-hidden=\"true\"></i></a></td>
                                <td>".$row['currencyType']."</td>
                                <td>".$row['currencyCode']."</td>
                                <td>".$row['currencyLabel']."</td>
                                <td>".$row['locale']."</td>
                            </tr>";
            }
            $stmt = null;
            return $table;
        } catch(PDOException $err) {
            AuditLog::add("Show currency failed - ".$err->getMessage());
            return "Server problem :(";
        }
    }


    // Generates paginated table with all users - clicking on a user will redirect to edit user page
    public function renderUsersTable() {
        // users table
        $query = "SELECT 
                    users.userID,
                    CONCAT_WS(' ',firstName,lastName) as FullName,
                    CASE WHEN verified = 'user_ver' THEN 'Yes' ELSE 'No' END as Verified,
                    CASE WHEN adminCheck = 'YWUyc3VFK0t5QjRhNXVQOUZBdWdSUT09' THEN 'Yes' ELSE 'No' END as Admin,
                    CONCAT(currency.currencyCode,' (',currency.currencyLabel,')') as Currency,
                    DATE_FORMAT(registerTime, '%d/%m/%Y %H:%i') as RegisterTime,
                    DATE_FORMAT(lastSignIn, '%d/%m/%Y %H:%i') as lastSignIn
                  FROM users
                    LEFT JOIN currency ON currency.currencyID = users.currencyID
                  ORDER BY firstName ASC, lastName ASC";

        $settings = '{
            "tableName" : "tblUsers",
            "showHeader" : true,
            "paging" : true,
            "pageSize" : 15,
            "sorting" : true,
            "rowURL" : "userEdit.php",
            "rowURLQS" : { "id" : "userID" },
            "columns" : 
            [
                "FullName",
                "Currency",
                "Verified",
                "Admin",
                "RegisterTime",
                "lastSignIn"
            ]
	    }';

        return Table::render($query,$settings);
    }


    // Returns the user account information for userEdit.php
    public function userInfo($ID) {
        try {
            $stmt = $this->query("SELECT 
                          users.userID,
                          firstName,
                          lastName,
                          email,
                          CASE WHEN verified = 'user_ver' THEN 'yes' ELSE 'no' END as verified,
                          adminCheck,
                          uuCheck,
                          notes,
                          currency.currencyCode as curCode,
                          currency.currencyType as curType,
                          DATE_FORMAT(registerTime, '%d/%m/%Y %H:%i') as registerTime,
                          DATE_FORMAT(lastSignIn, '%d/%m/%Y %H:%i') as lastSignIn
                        FROM users
                          LEFT JOIN currency ON currency.currencyID = users.currencyID
                        WHERE userID=:userID
                        ORDER BY firstName ASC, lastName ASC");
            $stmt->execute(array(':userID'=>$ID));
            $details = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = null;
            if(!empty($details)) {
                return $details;
            } else {
                AuditLog::add("Admin fetch user details failed - no user found with ID");
                return "Server problem :(";
            }
        } catch(PDOException $err) {
            AuditLog::add("Admin fetch user details failed - ".$err->getMessage());
            return "Server problem :(";
        }
    }


}