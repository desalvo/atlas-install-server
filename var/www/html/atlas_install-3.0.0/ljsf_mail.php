<?php

require_once('Mail.php');
include('Mail/smtp.php');

class MailHandler{
    var $params = null;
    var $mail_object = null;
    var $recipients = null;
    var $headers = null;
    var $body = "";

    function MailHandler($host, $port, $auth, $username, $password, $persist) {
        $this->params["host"] = $host;
        $this->params["port"] = $port;
        $this->params["auth"] = $auth;
        $this->params["username"] = $username;
        $this->params["password"] = $password;
        $this->params["persist"] = $persist;
        $this->mail_object = new Mail_smtp($this->params);
    }

    function createFrom($email){
        $this->headers['From'] = $email;
    }

    function createTo($email){
        $this->headers['To'] = $email;
        $this->recipients = array($email);
    }

    function createCC($email){
        $this->headers['Cc'] = $email;
    }

    function createBCC($email){
        $this->headers['Bcc'] = $email;
    }

    function createSubject($sub){
        $this->headers['Subject'] = $sub;
    }

    function createBody($body){
        $this->body=$body;
    }

    function sendMail(){
        if ($this->mail_object->send($this->recipients, $this->headers, $this->body)) {
            return true;
        } else {
            return false;
        }
    }
}


$smtp_host = "localhost"; // The server to connect. Default is localhost
$smtp_Port = 25; // The port to connect. Default is 25
$smtp_auth = FALSE; // Whether or not to use SMTP authentication. Default is FALSE
$smtp_username = ""; // The username to use for SMTP authentication.
$smtp_password = ""; // The password to use for SMTP authentication.
$smtp_persist = FALSE; // Indicates whether or not the SMTP connection should persist over multiple calls to the send() method.
$ljsfmail = new MailHandler($smtp_host, $smtp_Port, $smtp_auth, $smtp_username, $smtp_password, $smtp_persist);

$ljsfmail->createTo("Alessandro.DeSalvo@roma1.infn.it");
$ljsfmail->createFrom("My test user");
$ljsfmail->createSubject("TEST MAIL");
$ljsfmail->createBody("Test body");

if($ljsfmail->sendMail()){
    echo "Mail sent.\n";
} else {
    echo "Error sending mail!\n";
}

?>
