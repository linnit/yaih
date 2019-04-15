<?php
namespace YAIH\Model;

/**
 * Class: User
 *
 * @see Model
 */
class User extends Model
{
    public $user;

    public function __construct($parent)
    {
        $this->parent	= $parent;
        $this->db	= $this->parent->db;

        $this->userLoggedin = false;
        $this->isLogged();

        if (!isset($_SESSION)) {
            session_start();
        }
    }

    /**
     * Find given user using email/username
     *
     * @param str $login Username or email
     *
     * @return arr $user id, username, email of given user
     */
    public function findUser($login)
    {
        $stmt = $this->db->prepare("SELECT id, username, email FROM user WHERE email = :email OR username = :user");

        $stmt->bindParam(":email", $login);
        $stmt->bindParam(":user", $login);
        $stmt->execute();

        $user = $stmt->fetch();

        return $user;
        //if (!empty($user)) {
        //    return $user;
        //}
        //return false;
    }

    /**
     * Create a forgot password token and insert into database
     *
     * @param int id User ID
     *
     * @return str $token Forgot password token
     */
    public function createForgotToken($id)
    {
        $token = $this->random_str(16);

        // Check if the token is already in use
        while ($this->checkForgotToken($token)) {
            $token = $this->random_str(16);
        }

        $stmt = $this->db->prepare("INSERT INTO forgot_tokens VALUES(NULL, :uid, :token, NULL);");

        $stmt->bindParam(":uid", $id);
        $stmt->bindParam(":token", $token);
        $stmt->execute();

        return $token;
    }

    /**
     * Check a given token to see if it exists in the database
     *
     * @todo Tokens should only be valid for 2/3 days?
     *
     * @param str $token
     *
     * @return bool status of token's existense
     */
    public function checkForgotToken($token)
    {
        $stmt = $this->db->prepare("SELECT uid, created FROM forgot_tokens WHERE token = :token;");

        $stmt->bindParam(":token", $token);
        $stmt->execute();

        $token = $stmt->fetch();

        return (!empty($token));
    }

    /**
     * Try log the user in with given email/password
     *
     * @return boolean
     */
    public function login()
    {
        if (!isset($_POST["login"])) {
            // If we're logging in from the register from
            $login = $_POST["username"];
        } else {
            // If we're logging in from the login form
            $login = $_POST["login"];
        }
        $password = $_POST["password"];

        $stmt = $this->db->prepare("SELECT id, password, enabled FROM user WHERE email = :email OR username = :user");

        $stmt->bindParam(":email", $login);
        $stmt->bindParam(":user", $login);
        $stmt->execute();

        $user = $stmt->fetch();

        if (!isset($user["id"])) {
            $this->bruteforceLoginCheck(0);
        } else {
            $this->bruteforceLoginCheck($user["id"]);
        }

        if (!$user) {
            $this->setAlert("danger", "Incorrect Login (Bad e-mail)");

            return false;
        }

        $hash = $user["password"];

        if (password_verify($password, $hash)) {
            if ($user["enabled"] == 0) {
                $this->setAlert("danger", "Account is locked");
                return false;
            }

            $this->email = $login;
            $this->uid = $user["id"];
            $this->getIDs();

            $this->setAlert("success", "Login Success");

            $this->removeLoginAttempt($this->uid);

            $this->userLoggedin = true;

            return true;
        } else {
            $this->setAlert("danger", "Incorrect Login (Bad password)");

            return false;
        }
    }

    /**
     * Check login form for bruteforce attempts
     *
     * @param  int $uid User ID to check against bruteforce
     *
     */
    public function bruteforceLoginCheck($uid)
    {
        $stmt = $this->db->prepare("SELECT count(ip_address) AS attempts FROM login_attempts WHERE ip_address = :ipaddr AND DATE(`timestamp`) = CURDATE()");
        $stmt->bindValue(":ipaddr", $_SERVER["REMOTE_ADDR"]);
        $stmt->execute();

        // Remove old attempts
        $removeOld = $this->db->prepare("DELETE FROM login_attempts WHERE DATE(`timestamp`) < CURDATE()");
        $removeOld->execute();

        $result = $stmt->fetch();
        $attempts = (int) $result["attempts"];

        // Try and stop bruteforce attempts by slowing them down?
        // Maybe revise this?
        // [TODO] maybe after 10 incorrect, block ip..
        if ($attempts <= 3) {
            $sleep = 0;
        } elseif ($attempts <= 5) {
            $sleep = 5;
        } else {
            $sleep = $attempts * 5;
        }

        sleep($sleep);

        $newAttempt = $this->db->prepare("INSERT INTO login_attempts VALUES(:ipaddr, :uid, NULL)");
        $newAttempt->bindValue(":ipaddr", $_SERVER["REMOTE_ADDR"]);
        $newAttempt->bindValue(":uid", $uid);
        $newAttempt->execute();
    }

    /**
     * On successful login remove login_attempts
     *
     * @param  int $uid User id
     */
    public function removeLoginAttempt($uid)
    {
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE ip_address = :ipaddr AND uid = :uid");
        $stmt->bindValue(":ipaddr", $_SERVER["REMOTE_ADDR"]);
        $stmt->bindValue(":uid", $uid);

        $stmt->execute();
    }

    /**
     * Get user level of logged in user
     * @return int User level
     */
    public function getUserLevel()
    {
        if (!$this->userLoggedin) {
            return 10;
        }

        $stmt = $this->db->prepare("SELECT user_type FROM user WHERE id = :uid");

        $stmt->bindParam(":uid", $this->uid);
        $stmt->execute();

        $user = $stmt->fetch();

        return $user["user_type"];
    }

    /**
     * Logout the current user
     *
     * @return boolean
     */
    public function logout()
    {
        if (!isset($_SESSION["uid"])) {
            $this->setAlert("success", "No Session to destroy");
            return true;
        }

        $stmt = $this->db->prepare("DELETE FROM sessions WHERE sid = :sid");
        $stmt->bindParam(":sid", $_SESSION["sid"]);

        $stmt->execute();

        session_destroy();

        $this->setAlert("success", "You have been logged out");

        return true;
    }

    /**
     * [register description]
     *
     * @return boolean                 [description]
     */
    public function register()
    {
        $username = $_POST["username"];
        $password = $_POST["password"];
        $repeatpassword = $_POST["password2"];

        if (isset($_POST["email"])) {
            $email = $_POST["email"];
        } else {
            $email = null;
        }

        if (empty($username)) {
            $this->setAlert('danger', "Username field empty");
            return false;
        } elseif (empty($password)) {
            $this->setAlert('danger', "Password field empty");
            return false;
        } elseif ($password !== $repeatpassword) {
            $this->setAlert('danger', "Passwords don't match");
            return false;
        }

        // Check if the username, or email is already in use
        if ($this->userEmailExists($username, $email)) {
            $this->setAlert('danger', "Username or email address already has an account");
            return false;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->db->prepare("INSERT INTO user VALUES(NULL, :username, :email, :hash, '', 5, 1)");

        $stmt->bindValue(":username", $username);
        $stmt->bindValue(":email", $email);
        $stmt->bindValue(":hash", $hash);

        $stmt->execute();

        $this->login();

        return true;
    }

    /**
     * deleteUser
     *
     * @param int $uid User id
     *
     * @return bool True if delete was successfull
     */
    public function deleteUser($uid)
    {
        $stmt = $this->db->prepare("DELETE FROM user WHERE id = :id");
        $stmt->bindValue(":id", $uid);
        $stmt->execute();

        return $stmt->rowCount() ? true : false;
    }

    /**
     * Reset password of given userLoggedin
     * @param int $uid User ID of account to reset password
     * @param string/null If a password is not given, randomly generate one
     *
     * @return string new password
     */
    public function resetPassword($uid, $password = null)
    {
        if (is_null($password)) {
            $password = $this->random_str(16);
        }
        $stmt = $this->db->prepare("UPDATE user SET password = :hash");
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt->bindValue(":hash", $hash);

        $stmt->execute();

        return $password;
    }

    /**
     * Send an email
     *
     * @param  str $email    [description]
     * @param  str $message [description]
     * @return [type]           [description]
     */
    public function emailUser($email, $message)
    {
        $this->parent->mail->setFrom("noreply@{$this->parent->siteDomain}", $this->parent->siteName);
        $this->parent->mail->addAddress($email);

        $this->parent->mail->isHTML(true);

        $this->parent->mail->Subject = $this->parent->siteName . ' Registration';
        $this->parent->mail->Body    = 'This is the HTML message body <b>in bold!</b><br>' . $message;
        $this->parent->mail->AltBody = 'This is the body in plain text for non-HTML mail clients\n:' .$message;

        if (!$this->parent->mail->send()) {
            echo 'Message could not be sent.';
            echo 'Mailer Error: ' . $this->parent->mail->ErrorInfo;
        } else {
            echo 'Message has been sent';
        }
    }

    /**
     * Generate a random string, using a cryptographically secure
     * pseudorandom number generator (random_int)
     * Credit: http://stackoverflow.com/questions/6101956/generating-a-random-password-in-php/31284266#31284266
     *
     * For PHP 7, random_int is a PHP core function
     * For PHP 5.x, depends on https://github.com/paragonie/random_compat
     *
     * @param int $length      How many characters do we want?
     * @param string $keyspace A string of all possible characters
     *                         to select from
     * @return string
     */
    public function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;

        if ($max < 1) {
            throw new Exception('$keyspace must be at least two characters long');
        }
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }

        return $str;
    }

    /**
     * Check if a user exists with the $email address given
     *
     * @param  string $username	username to check
     * @param  string $email	Email address of user to check
     *
     * @return boolean
     */
    public function userEmailExists($username, $email = null)
    {
        if (empty($email)) {
            $stmt = $this->db->prepare("SELECT email FROM user WHERE username = :username OR email = :useremail");
        } else {
            $stmt = $this->db->prepare("SELECT email FROM user WHERE username = :username OR email = :email OR email = :useremail");
            $stmt->bindParam(":email", $email);
        }

        $stmt->bindParam(":username", $username);

        // To check if someone is creating a user with the username as an already existing email
        // e.g creating with username 'admin@example.com'
        $stmt->bindParam(":useremail", $username);
        $stmt->execute();

        return count($stmt->fetchAll()) >= 1;
    }

    /**
     * Check if a user exists with the $id given
     *
     * @param  int $id	ID of user
     *
     * @return boolean
     */
    public function userIdExists($id)
    {
        $stmt = $this->db->prepare("SELECT id FROM user WHERE id = :id");

        $stmt->bindParam(":id", $id);
        $stmt->execute();

        return count($stmt->fetchAll()) == 1;
    }

    /**
     * Return username from given uid
     *
     * @parma int $uid user id
     *
     * @return str
     */
    public function getUsername($uid = null)
    {
        if (is_null($uid)) {
            if (!$this->userLoggedin) {
                return 'anonymous';
            }
            $uid = $this->uid;
        }
        $stmt = $this->db->prepare("SELECT username FROM user WHERE id = :uid");

        $stmt->bindParam(":uid", $uid);
        $stmt->execute();

        $result = $stmt->fetch();

        return $result["username"];
    }

    /**
     * Return uid from given username
     *
     * @parma int $username username
     *
     * @return int
     */
    public function getUid($username)
    {
        $stmt = $this->db->prepare("SELECT id FROM user WHERE username = :username");

        $stmt->bindParam(":username", $username);
        $stmt->execute();
        $row = $stmt->fetch();

        if (count($row) >= 1) {
            return $row["id"];
        } else {
            return 0;
        }
    }


    /**
     * Check if the user is logged in by checking session cookies
     *
     * @return boolean
     */
    public function isLogged()
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if ($this->userLoggedin) {
            return true;
        }

        if (!isset($_SESSION["uid"])) {
            return false;
        }

        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE uid = :uid AND sid = :sid ORDER BY timestamp DESC");

        $stmt->bindParam(":uid", $_SESSION["uid"]);
        $stmt->bindParam(":sid", $_SESSION["sid"]);

        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->checkTablesExist();
            return $this->isLogged();
        }


        $row = $stmt->fetch();

        if ($row["uid"] != $_SESSION["uid"]) {
            return false;
        }

        if ($row["sid"] != $_SESSION["sid"]) {
            return false;
        }

        if ($row["tid"] != $_SESSION["tid"]) {
            return false;
        }

        $this->uid = $row["uid"];
        $this->updateIDs();

        $this->userLoggedin = true;

        return true;
    }

    /**
     * Return all users
     *
     * @return array All user results
     */
    public function getAllUsers()
    {
        $stmt = $this->db->prepare("SELECT * FROM user ORDER BY id ASC");// LIMIT 0,20"); [TODO] Some kind of pagination?

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get currently logged in user's email address
     *
     * @param int $uid Optional user ID
     *
     * @return string/false email address of currently logged in user (or given user id) or false if not logged
     */
    public function getUserEmail($uid = null)
    {
        if (!$this->userLoggedin && is_null($uid)) {
            return false;
        }

        if (is_null($uid)) {
            $uid = $this->uid;
        }

        $stmt = $this->db->prepare("SELECT email FROM user WHERE id = :uid");
        $stmt->bindParam(":uid", $uid);

        $stmt->execute();

        $row = $stmt->fetch();

        return $row["email"];
    }

    /**
     * [getIDs description]
     * @return [type] [description]
     */
    public function getIDs()
    {
        $sid = session_id();
        $tid = md5(microtime(true));
        $ip = $_SERVER["REMOTE_ADDR"];

        $stmt = $this->db->prepare("INSERT INTO sessions VALUES(NULL, :uid, :sid, :tid, :ip)");

        $stmt->bindParam(":uid", $this->uid);
        $stmt->bindParam(":sid", $sid);
        $stmt->bindParam(":tid", $tid);
        $stmt->bindParam(":ip", $ip);

        $stmt->execute();

        $_SESSION["uid"] = $this->uid;
        $_SESSION["sid"] = $sid;
        $_SESSION["tid"] = $tid;
    }

    /**
     * updateIDs Update user sessions IDs in the database
     *
     * @return true
     */
    public function updateIDs()
    {
        $sid = $_SESSION["sid"];
        $tid = md5(microtime(true));
        $ip = $_SERVER["REMOTE_ADDR"];

        $stmt = $this->db->prepare("UPDATE sessions SET tid = :tid WHERE sid = :sid");

        $stmt->bindParam(":sid", $sid);
        $stmt->bindParam(":tid", $tid);

        $stmt->execute();

        $_SESSION["tid"] = $tid;

        return true;
    }

    /**
     * getPagePerms Get a user permissions level for a page
     *
     * @param str $page
     * @param int $level
     *
     * @return int Required user level to access given page
     */
    public function getPagePerms($page, $level = 0)
    {
        try {
            $stmt = $this->db->prepare("SELECT user_level FROM pages WHERE pagename = :page");

            $stmt->bindParam(":page", $page);
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->checkTablesExist();
            // Make sure we don't get stuck in a loop
            if ($level == 1) {
                return false;
            }
            return $this->getPagePerms($page, 1);
        }

        $page = $stmt->fetch();

        return $page["user_level"];
    }

    /**
     * Get all information about user from user table
     *
     * @param  int $id ID of user to retrieve
     *
     * @return arr Array of user information
     */
    public function getUser($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM user WHERE id = :id");

        $stmt->bindValue(":id", $id);

        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Lock given user from logging into the system
     *
     * @param  int $id ID of user to lock
     *
     * @return int ID of user that has been locked
     */
    public function lockUser($id)
    {
        $stmt = $this->db->prepare("UPDATE user SET enabled = 0 WHERE id = :id");
        $stmt->bindValue(":id", $id);
        $stmt->execute();

        return $stmt->rowCount() ? true : false;
    }

    /**
     * Unlock given user from logging into the system
     *
     * @param  int $id ID of user to unlock
     *
     * @return int ID of user that has been unlocked
     */
    public function unlockUser($id)
    {
        $stmt = $this->db->prepare("UPDATE user SET enabled = 1 WHERE id = :id");
        $stmt->bindValue(":id", $id);
        $stmt->execute();

        return $stmt->rowCount() ? true : false;
    }

    /**
     * Get user history setting from database
     *
     * @param int $uid
     *
     * @return
     */
    public function getUserHistorySetting($uid)
    {
        $stmt = $this->db->prepare("SELECT value FROM user_settings WHERE setting='save_history' AND uid = :uid");
        $stmt->bindValue(":uid", $uid);
        $stmt->execute();

        $val = $stmt->fetch();

        // No setting exists
        if (!$val) {
            return false;
        }

        return $val["value"];
    }

    /**
     * Update user's history setting
     *
     * @param int $uid User ID
     * @param int $value New value
     */
    public function setUserHistorySetting($uid, $value)
    {
        if ($this->getUserHistorySetting($uid) === false) {
            $stmt = $this->db->prepare("INSERT INTO user_settings VALUES(:uid, 'save_history', :value)");
        } else {
            $stmt = $this->db->prepare("UPDATE user_settings SET value = :value WHERE setting='save_history' AND uid = :uid");
        }

        $stmt->bindValue(":value", $value);
        $stmt->bindValue(":uid", $uid);

        $stmt->execute();
    }


    /**
     * Clear a users viewed history
     *
     * @param int $uid User ID
     */
    public function clearUserHistory($uid)
    {
        $stmt = $this->db->prepare("DELETE FROM history WHERE uid = :uid");
        $stmt->bindValue(":uid", $uid);

        $stmt->execute();
    }
}
