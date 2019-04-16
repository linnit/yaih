<?php
namespace YAIH\Model;

require 'model/User.php';
require 'model/Table.php';
require 'model/Image.php';
require 'model/Video.php';
require 'model/Site.php';

/**
 * Class: Model
 *
 */
class Model
{
    public $endAlerts;

    public function __construct()
    {
        try {
            $dotenv = new \Dotenv\Dotenv(dirname(dirname(__DIR__)));
            $dotenv->load();
        } catch (Exception $e) {
            echo 'Error: ' .$e->getMessage();
            throw new Exception("Error opening .env file");
        }

        $this->siteURL = $this->getSiteUrl();
        $this->siteDomain = $this->getDomain();
        $this->siteName = getenv('SITENAME');
        $this->imageDir = getenv('IMAGEDIR');
        $this->thumbDir = getenv('THUMBDIR');

        $dbhost = getenv('DB_HOST');
        $dbname = getenv('DB_NAME');
        $dbuser = getenv('DB_USER');
        $dbpass = getenv('DB_PASS');


        $this->mail = new \PHPMailer;
        if (filter_var(getenv('SMTPDEBUG'), FILTER_VALIDATE_BOOLEAN)) {
            $this->mail->SMTPDebug = 3;
        }
        $this->mail->isSMTP();
        $this->mail->Host =		getenv('SMTPHOST');
        if (getenv('SMTPAUTH') == "false") {
            $this->mail->SMTPAuth = false;
        } elseif (getenv('SMTPAUTH') == "true") {
            $this->mail->SMTPAuth = true;
        }
        $this->mail->Username =		getenv('SMTPUSER');
        $this->mail->Password =		getenv('SMTPPASS');
        $this->mail->SMTPSecure =	filter_var(getenv('SMTPSECURE'), FILTER_VALIDATE_BOOLEAN);
        $this->mail->Port =		getenv('SMTPPORT');

        try {
            $this->db = new \PDO("mysql:host={$dbhost};dbname={$dbname}", $dbuser, $dbpass);
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // https://stackoverflow.com/questions/10113562/pdo-mysql-use-pdoattr-emulate-prepares-or-not
            $this->db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        } catch (\PDOException $e) {
            echo 'Error: ' .$e->getMessage();
            throw new \PDOException("Error connecting to database.");
        }

        $this->user = new \YAIH\Model\User($this);
        $this->image = new \YAIH\Model\Image($this);
        $this->table = new \YAIH\Model\Table($this);
        $this->site = new \YAIH\Model\Site($this);

        $this->table->checkTablesExist();

        // danger, warning, success
        $this->endAlerts = array();
    }

    public function setAlert($type, $alert)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (!isset($_SESSION['alerts'])) {
            $_SESSION['alerts'] = array();
        }
        array_push($_SESSION['alerts'], array($type, $alert));
    }

    public function getSiteUrl()
    {
        return preg_replace('/\/$/', '', getenv('SITEURL'));
    }

    public function getDomain()
    {
        return str_replace(array('http://', 'https://'), '', $this->siteURL);
    }
}
