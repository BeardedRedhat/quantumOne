<?php
require_once('Database.php');
require_once('Crypt.php');
require_once('Session.php');
require_once('Transactions.php');
require_once('Text.php');
require_once('Form.php');


class user
{
    private $conn;
    public  $userID;
    CONST UU_DOMAIN_NAME = "ulster.ac.uk"; // ulster university domain name
    private $uuDomainName = "ulster.ac.uk"; // ulster university domain name

    public function __construct() {
        $database = new Database();
        $db = $database->openConnection();

        $this->conn = $db;
    }

    // query function
    public function query($sql) {
        $stmt = $this->conn->prepare($sql);
        return $stmt;
    }


    // Checks if email address belongs to an Ulster University student, if so returns the local part of email, e.g. 'user@mail.com' => 'user'
    public function uuEmailCheck($email) {
        $splitEmail = Text::splitEmail($email);
        if($this->uuDomainName == $splitEmail[1])
            return array('result' => true, 'userEmail' => $splitEmail[0]);
        else
            return false;
    }


    // Register function that first checks if email is already registered, validates data and encrypts email & hashes password
    public function register($userEmail, $userPass, $userPassConf, $userFirstName, $userLastName, $registerTime, $currency) {
        $audit = "User registration failed - "; // audit logging

        try {
            // Checking if the email has already been registered
            $stmt = $this->query("SELECT email FROM users WHERE email = :email");
            $stmt->execute(array(':email'=>Crypt::encryptWithKey($userEmail, "packmyboxwithfivedozenliquorjugs")));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = null;
            if($row['email'] == Crypt::encryptWithKey($userEmail, "packmyboxwithfivedozenliquorjugs")) {
                AuditLog::add("Email attempted to re-register " . $userEmail);
                return Form::error_msg("The email you entered is already registered.");
            }
        } catch(PDOException $err) {
            AuditLog::add($audit.$err->getMessage());
            return Form::error_msg("Looks like a server problem. If problem persists, contact administrator.");
        }

        // first name validation
        if(!empty($userFirstName)) {
            // str_replace takes spaces out (users who may enter middle name) as it returns false with ctype_alpha
            if(!ctype_alpha(str_replace(' ','',$userFirstName))) {
                AuditLog::add($audit."non alpha-numeric characters detected in first name.");
                $error = "Only alphabetical characters allowed in first name.";
            }
            if(strlen($userFirstName) > 30) {
                AuditLog::add($audit."first name character limit exceeded.");
                $error = "First name cannot be over 30 characters";
            }
        } else {
            AuditLog::add($audit."Empty first name");
            $error = "Please enter your first name.";
        }

        // last name validation
        if(!empty($userLastName)) {
            if(!ctype_alpha(str_replace(' ','',$userLastName))) {
                AuditLog::add($audit."non alpha-numeric characters detected in last name.");
                $error = "Only alphabetical characters allowed in last name.";
            }
            if(strlen($userLastName) > 50) {
                AuditLog::add($audit."last name character limit exceeded.");
                $error = "Last name cannot be over 50 characters";
            }
        } else {
            AuditLog::add($audit."Empty last name");
            $error = "Please enter your last name.";
        }

        // email validation
        if(!empty($userEmail)) {
            if(!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                AuditLog::add($audit."invalid email entered.");
                $error = "Please enter a valid email address";
            }
            if(strlen($userEmail) > 60) {
                AuditLog::add($audit."email character length exceeded.");
                $error = "Email cannot be over 60 characters";
            }
        } else {
            AuditLog::add($audit."empty email address.");
            $error = "Please enter your email address.";
        }

        $uuCheck = null;

        $userEmail = Crypt::encryptWithKey($userEmail, "packmyboxwithfivedozenliquorjugs"); // encrypt user email

        // checks if passwords match and do not fall below 8 characters long
        if(strcmp($userPass, $userPassConf) !== 0) {
            $error = "Passwords do not match";
        } else {
            if(strlen($userPass) < 8 || strlen($userPassConf) < 8) {
                $error = "Password must be at least 8 characters";
            }
        }

        if(!isset($error)) {
            try {
                $newUserPass = password_hash($userPass, PASSWORD_DEFAULT);
                $stmt = $this->query("
                INSERT INTO users (
                    firstName,
                    lastName,
                    email,
                    password,
                    currencyID,
                    registerTime) 
                VALUES (
                    :firstName,
                    :lastName,
                    :email,
                    :password,
                    :currencyID,
                    :registerTime)");
                $stmt->bindParam(':firstName', $userFirstName);
                $stmt->bindParam(':lastName', $userLastName);
                $stmt->bindParam(':email', $userEmail);
                $stmt->bindParam(':password', $newUserPass);
                $stmt->bindParam(':currencyID', $currency);
                $stmt->bindParam(':registerTime', $registerTime);
                $stmt->execute();
                $stmt = null;
                return true;
            }
            catch (PDOException $err) {
                AuditLog::add("User registration failed - ".$err->getMessage());
                return Form::error_msg("Looks like a server problem. If problem persists, contact administrator.");
            }
        } else {
            return Form::error_msg($error);
        }
    }


    // Returns user data in array: full name, email, student check, currencyID and register date
    public function info() {
        if(empty($this->userID))
            Throw new Exception("User ID is not set on info().");

        try {
            $stmt = $this->query("SELECT firstName, lastName, email, verified, uuCheck, currencyID, DATE_FORMAT(registerTime, '%d/%m/%Y %H:%i:%s') as registerDate FROM users WHERE userID=:userID");
            $stmt->execute(array(':userID'=>$this->userID));
            if($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                AuditLog::add("Fetch user info failed - no rows returned");
                return "Looks like a server problem.";
            }
        } catch(PDOException $err) {
            AuditLog::add("Fetch user info failed - ".$err->getMessage());
            return "Looks like a server problem.";
        }
    }


    // Updates user info from either the admin userEdit page or myProfile page
    public function update($userID,$currency,$firstName,$lastName,$email,$notes=null, $admin=true) {

        $audit = "Update user failed - ";

        if(!empty($firstName)) {
            if(strlen($firstName) > 30) {
                AuditLog::add($audit."first name character limit exceeded.");
                return "First name character limit exceeded. Limit 30.";
            }
            if(!ctype_alpha(str_replace(' ','',$firstName))) {
                AuditLog::add($audit."invalid character input on first name.");
                return "First name can only contain alphabetic letters.";
            }
        } else {
            AuditLog::add($audit."first name field empty");
            return "First name is empty.";
        }

        if(!empty($lastName)) {
            if(strlen($lastName) > 50) {
                AuditLog::add($audit."last name character limit exceeded.");
                return "Last name character limit exceeded. Limit 50.";
            }
            if(!ctype_alpha(str_replace(' ','',$lastName))) {
                AuditLog::add($audit."invalid character input on last name.");
                return "Last name can only contain alphabetic letters.";
            }
        } else {
            AuditLog::add($audit."last name field empty");
            return "Last name is empty.";
        }

        if(!empty($email)) {
            if(filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
                AuditLog::add($audit."invalid email.");
                return "Invalid email address entered.";
            }
            if(strlen($email) > 60) {
                AuditLog::add($audit."email character lenght exceeded.");
                return "Email character length exceeded. Max: 60.";
            }
        } else {
            AuditLog::add($audit."email is empty");
            return "Email is empty";
        }

        $email        = Crypt::encryptWithKey($email, "packmyboxwithfivedozenliquorjugs");
        $updateSQL    = "SET currencyID=:currencyID, firstName=:firstName, lastName=:lastName, email=:email"; // SQL for update query
        $updateParams = array( // bind parameters for update query
            ':currencyID' => $currency,
            ':firstName'  => $firstName,
            ':lastName'   => $lastName,
            ':email'      => $email,
            ':userID'     => $userID
        );

        // If the user is being edited via admin/userEdit.php, append notes to query & parameters
        if($admin===true) {
            if(strlen($notes) > 1000) {
                AuditLog::add($audit."notes character limit exceeded.");
                return "User notes character limit exceeded. Limit 1000.";
            }
            $updateSQL .= ", notes=:notes"; // append notes onto query
            $updateParams[':notes'] = $notes; // bind notes parameter
        }

        try {
            $stmt = $this->query("UPDATE users 
                                  $updateSQL
                                  WHERE userID=:userID");
            $stmt->execute($updateParams);
            $stmt = null;
            return true;
        } catch(PDOException $err) {
            AuditLog::add("Update user failed - ".$err->getMessage());
            return "Looks like an internal server error. Please contact administrator.";
        }

    }


    // login function
    public function login($userEmail, $userPass) {
        try {
            $stmt = $this->conn->prepare("
            SELECT 
              users.userID, 
              users.firstName,
              users.email, 
              users.password, 
              users.adminCheck, 
              users.verified, 
              users.uuCheck, 
              users.currencyID,
              DATE_FORMAT(users.lastSignIn, '%b %d %Y') AS lastSignIn,
              DATE_FORMAT(users.registerTime, '%Y/%m/%d') AS registerDate,
              currency.currencyLabel,
              currency.locale
            FROM 
              users 
              LEFT JOIN currency ON currency.currencyID = users.currencyID
            WHERE 
              email = :email");
            $stmt->execute(array(':email' => Crypt::encryptWithKey($userEmail, "packmyboxwithfivedozenliquorjugs")));

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if($stmt->rowCount() == 1) {
                if(password_verify($userPass, $user['password'])) { // verify password
                    // If user isn't yet verified, check if the 30-day limit has expired
                    if($user['verified'] != "user_ver") {
                        $regDate  = date($user['registerDate']); // user registration date
                        $compDate = date(Calculate::subtractDate($regDate, -30, "days")); // registration date +30 days

                        if($compDate < date('Y-m-d')) {
                            AuditLog::add("Login attempt failed ".$user['email']." - unverified and 30-day limit has expired.");
                            return "30-day limit has expired. To extend this, please verify your account using the link sent via email.";
                        }
                    }

                    // Array of all session variables to be set
                    $sessionVals = array(
                        'userID'        => $user['userID'],
                        'userFirstName' => $user['firstName'],
                        'adminCheck'    => $user['adminCheck'],
                        'verified'      => $user['verified'],
                        'currencyID'    => $user['currencyID'],
                        'currencyLabel' => $user['currencyLabel'],
                        'locale'        => $user['locale'],
                        'lastSignIn'    => $user['lastSignIn']
                    );

                    // Set session variables
                    foreach($sessionVals as $key => $value)
                        Session::set($key,$value);

                    // If registered student account, set session 'uuCkeck' as string from db
                    if($user['uuCheck'] !== null || !empty($user['uuCheck'])) {
                        Session::set('uuCheck',$user['uuCheck']);
                    }

                    //updating last sign-in time
                    $stmt = $this->conn->prepare("UPDATE users SET lastSignIn = NOW() WHERE userID = :userID");
                    $stmt->bindParam(':userID', $_SESSION['userID']);
                    $stmt->execute();
                    return true;
                }
                else {
                    $_SESSION['loginCount']++;
                    AuditLog::add("Unsuccessful login - invalid password.");
                    return "Invalid email or password.";
                }
            } else {
                $_SESSION['loginCount']++;
                AuditLog::add("Unsuccessful login - invalid password.");
                return "Invalid email or password.";
            }
        } catch(PDOException $err) {
            AuditLog::add("Unsuccessful login - ".$err->getMessage());
            return "Looks like a server problem. If problem persists, contact administrator.";
        }
    }


    // logged in function
    public function loggedin() {
        if(isset($_SESSION['userID'])) {
            return true;
        }
        return false;
    }


    // update password
    public function changePassword($oldP, $newP, $confP, $reset=false) {
        $audit = "Password reset attempt failed - "; // audit logging

        try {
            // If user is changing password from myProfile page,check old password
            if($reset === false) {
                $stmt = $this->query("SELECT password FROM users WHERE userID=:userID");
                $stmt->execute(array(':userID'=>$this->userID));
                $oldPassword = $stmt->fetch()[0];
                $stmt = null;

                // Verifies old password with db password
                if(!password_verify($oldP, $oldPassword)) {
                    AuditLog::add($audit."Old password doesn't match database password.");
                    return Form::error_alert("Old password does not match our records.");
                }
                // Checks if new passwords match or not
                if(strcmp($newP, $confP) !== 0) {
                    AuditLog::add($audit."passwords don't match");
                    return Form::error_alert("Passwords don't match.");
                }
            }

            // Checks if new passwords match or not
            if(strcmp($newP, $confP) !== 0) {
                AuditLog::add($audit."passwords don't match");
                $msg = Form::error_alert("Passwords don't match.");
            }
            // If new password is below 8 characters
            if(strlen($newP) < 8) {
                AuditLog::add($audit."password length is below 8 characters");
                $msg = Form::error_alert("New password must be at least 8 characters.");
            }
            // If no errors are returned
            if(!isset($msg)) {
                $newPasswordHash = password_hash($newP, PASSWORD_DEFAULT); // hash new password

                // Update database password for user
                $stmt = $this->query("UPDATE users SET password=:newPassword WHERE userID=:userID");
                $stmt->execute(array(':newPassword'=>$newPasswordHash, ':userID'=>$this->userID));
                $stmt = null;

                // remove all password reset requests from database
                $stmt = $this->query("DELETE FROM passwordResetRequests WHERE userID=:userID");
                $stmt->execute(array(':userID'=>$this->userID));
                $stmt = null;

                return true;
            } else {
                return $msg;
            }

        } catch(PDOException $err) {
            AuditLog::add($audit.$err->getMessage());
            return Form::error_alert("Looks like an internal server problem. If problem persists, contact administrator..");
        }
    }


    // redirect function
    public function redirect($url) {
        header("location: $url");
    }


    // Generates currency drop down list with GDP as default selected
    public function getCurrencyDdl($selected=2) {
        //Fetching currencies for dropdown list
        $stmt = $this->query("SELECT currencyID,currencyType,currencyCode,currencyLabel FROM currency ORDER BY currencyType ASC");
        $stmt -> execute();
        $ddl_currency = "";
        while($row = $stmt -> fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
            $ddl_currency .= "<option value=\"" . Crypt::encrypt($row['currencyID']) . "\" " . ($row['currencyID'] == $selected ? "selected=\"selected\"" : "") . ">";
            $ddl_currency .=    $row['currencyCode'] . " - " . $row['currencyType'];
            $ddl_currency .= "</option>";
        }
        $stmt = null;
        return $ddl_currency;
    }


    public function __destruct()
    {

    }

}