<?php
namespace YAIH\Model;

class Table extends Model
{
    public function __construct($parent)
    {
        $this->parent	= $parent;
        $this->db	= $this->parent->db;

        $this->tables = array(
      "login_attempts" => "CREATE TABLE `login_attempts` (
        `ip_address` varchar(16) DEFAULT NULL,
        `uid` int(11) DEFAULT NULL,
        `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )",
      "pages" => "CREATE TABLE `pages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `pagename` varchar(64) NOT NULL,
        `user_level` int(11) NOT NULL,
        PRIMARY KEY (`id`)
      )",
      "post" => "CREATE TABLE `post` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `uid` int(11),
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `title` varchar(128) NOT NULL,
        `url` varchar(16) NOT NULL,
        `mimetype` varchar(32) NOT NULL,
        PRIMARY KEY (`id`)
      )",
      "reports" => "CREATE TABLE `reports` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `uid` int(11),
        `pid` int(11),
        `reason` varchar(64) NOT NULL,
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
      )",
      "saved" => "CREATE TABLE `saved` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `pid` int(11) NOT NULL,
        `uid` int(11) NOT NULL,
        PRIMARY KEY (`id`)
      )",
      "history" => "CREATE TABLE `history` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `pid` int(11) NOT NULL,
        `viewed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `uid` int(11) NOT NULL,
        PRIMARY KEY (`id`)
      )",
      "sessions" => "CREATE TABLE `sessions` (
        `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `uid` int(11) NOT NULL,
        `sid` varchar(64) NOT NULL,
        `tid` varchar(64) NOT NULL,
        `ip` varchar(16) NOT NULL
      )",
      "user" => "CREATE TABLE `user` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(24) DEFAULT NULL,
        `email` varchar(254) DEFAULT NULL,
        `password` varchar(64) DEFAULT NULL,
        `description` varchar(128) DEFAULT NULL,
        `user_type` int(11) DEFAULT NULL,
        `enabled` int(11) NOT NULL DEFAULT '1',
        PRIMARY KEY (`id`)
      )",
      "forgot_tokens" => "CREATE TABLE `forgot_tokens` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `uid` int(11) NOT NULL,
        `token` varchar(64) NOT NULL,
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
      )",
      "user_settings" => "CREATE TABLE `user_settings` (
        `uid` int(11) NOT NULL,
        `setting` varchar(32) DEFAULT NULL,
        `value` varchar(64) DEFAULT NULL 
      )",
      "site_settings" => "CREATE TABLE `site_settings` (
        `setting` varchar(32) DEFAULT NULL,
        `value` varchar(64) DEFAULT NULL 
      )",
    );

        $this->tableRows = array(
      "login_attempts" => array(),
      "pages" => array("INSERT INTO pages VALUES(NULL, 'index', 10);",
        "INSERT INTO pages VALUES(NULL, 'image', 10);",
        "INSERT INTO pages VALUES(NULL, 'recent', 10);",
        "INSERT INTO pages VALUES(NULL, 'user', 10);",
        "INSERT INTO pages VALUES(NULL, 'admin', 1);",
        "INSERT INTO pages VALUES(NULL, 'admin-images', 1);",
        "INSERT INTO pages VALUES(NULL, 'admin-edituser', 1);",
        "INSERT INTO pages VALUES(NULL, 'admin-users', 1);",
        "INSERT INTO pages VALUES(NULL, 'admin-settings', 1);",
        "INSERT INTO pages VALUES(NULL, 'admin-reports', 1);",
        "INSERT INTO pages VALUES(NULL, 'account', 5);",
        "INSERT INTO pages VALUES(NULL, 'account-history', 5);",
        "INSERT INTO pages VALUES(NULL, 'account-saved', 5);",
        "INSERT INTO pages VALUES(NULL, 'account-settings', 5);",
        "INSERT INTO pages VALUES(NULL, 'login', 10);",
        "INSERT INTO pages VALUES(NULL, 'forgotpassword', 10);",
        "INSERT INTO pages VALUES(NULL, 'forgotpassword-reset', 10);",
        "INSERT INTO pages VALUES(NULL, 'resetpassword', 5);",
        "INSERT INTO pages VALUES(NULL, 'logout', 10);",
        "INSERT INTO pages VALUES(NULL, '404', 10);",
        "INSERT INTO pages VALUES(NULL, 'abuse', 10);",
        "INSERT INTO pages VALUES(NULL, 'customer', 5);",
        "INSERT INTO pages VALUES(NULL, 'faq', 10);",
        "INSERT INTO pages VALUES(NULL, 'termsofservice', 10);",
        "INSERT INTO pages VALUES(NULL, 'privacypolicy', 10);",
      ),
      "post" => array(),
      "reports" => array(),
      "saved" => array(),
      "history" => array(),
      "sessions" => array(),
      "user" => array("INSERT INTO user VALUES (0, 'anonymous', '', '', '', 10, 0);",
        "INSERT INTO user VALUES (NULL, 'admin', 'admin@example.com', '$2y$10\$dnE.zKJt9Wr1RkAm1/WPM.ZCTSDhEokM.6pSyyYw9NYenoCinxtKy', 'Default admin account', 0, 1);"
      ),
      "forgot_tokens" => array(),
      "site_settings" => array("INSERT INTO site_settings VALUES('maintenance_mode', 0);"),
      "user_settings" => array(""),
    );
    }

    /**
     * Check if the base tables in our database exists
     * If not, create them
     *
     */
    public function checkTablesExist()
    {
        //$baseTables = array("login_attempts", "pages", "sessions", "user", "post", "saved", "history");
        //foreach($baseTables as $table) {
        foreach ($this->tables as $tableName => $tableSQL) {
            try {
                // Can't bindParam a table name, so gotta do it this way
                $stmt = $this->db->prepare("SELECT 1 FROM $tableName");
                $stmt->execute();
            } catch (\PDOException $e) {
                $this->createTable($tableName);
                $this->insertDefaultRows($tableName);
            }
        }
    }

    public function createTable($table)
    {
        $this->db->exec($this->tables[$table]);
    }

    public function insertDefaultRows($table)
    {
        foreach ($this->tableRows[$table] as $row) {
            $this->db->exec($row);
        }
    }
}
