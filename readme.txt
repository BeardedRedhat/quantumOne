QuantumOne

Developed by Aaron McFarland
Final year project
--------------------------------------------------------

QuantumOne is a simplified personal budgeting tool 
designed for beginners - mainly aimed toward the student 
demographic - with an implemented cash flow forecast.

Technology Stack
  * PHP 7.0
  * MySQL 5.7 database
  * Chart.js v2.6
  * jQuery v3.1
  * Bootstrap v3.7
  * PHPMailer v5.2.21
  * Google reCAPTCHA v2 API

--------------------- Installation ---------------------

		     ---- LIVE ----

To view the live version of the website hosted on AWS 
cloud servers, visit:

              *** http://34.246.117.48 ***

Admin Credentials
Email:     farly869@gmail.com
Password:  QuantumOne1

		     ---- LOCAL ----

If you want to set up a local server environment on your
computer, you will first need a number of packages:

  * A text editor/IDE like Notepad++ or PHPStorm to view 
    the source code;

  * A MySQL database client/tool like MySQLWorkBench,
    Sequel Pro (MacOS only) or PHPMyAdmin;

  * A local server environment tool like MAMP or XAMPP.
    MAMP Pro was used through development.

  * A web browser - preferably Google Chrome/Safari


The first step is setting up the localhost database. 
Open up the database client and there should be an
option to 'import' database using a .sql file. The
database file is named:

	            quantumOne.sql

Once imported, open the project in the text editor.
Navigate to "class_lib" directory, and open 
"database.php" class. If the database was setup as 
localhost, then the database details can stay the same.
If not, change to the correct host, username & 
password.

Next, we need to setup the local server environment.
Open MAMP/XAMPP and create a new host and set the 
document root as the "public" folder. This means it will
automatically point to "index.php" once run.

To ensure that PHPMailer operates under the local server
Environment, a number of small changes need to be made.
Open the following pages in your text editor:

  * register.php            line 65
  * forgottenPassword.php   line 41
  * /manage/myProfile.php   line 69

The named lines should contain the query string used in
verification emails. Currently, the domain is
"http://finalyrproj:8888/....", which is the host name
given on MAMP. Change this to your host name on each file.

Run the system on your local server environment.
Remember to start Apache, MySQL & PHP servers in XAMPP 
before running.

Developer account credentials for localhost
Email:      aaron.mcf96@gmail.com
Password:   password


------------------------ Notes ------------------------





