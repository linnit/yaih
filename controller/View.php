<?php
namespace YAIH\Controller;

/**
 * Class: View
 *
 * @see Controller
 */
class View extends Controller
{
    public function __construct($model)
    {
        $loader = new \Twig_Loader_Filesystem('view');
        $this->twig = new \Twig_Environment($loader, array(
            'debug' => true,
            //'cache'	=> 'cache'
        ));
        $this->twig->addExtension(new \Twig_Extension_Debug());

        $this->model = $model;
    }

    /**
     * render - render twig template of given page
     *   Check the user's permissions level and handles alerts
     *
     * @param mixed $page - given page to render
     * @param string $stuff - variables used in the twig template
     */
    public function render($page, $stuff = "")
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        // Clear alerts
        if (isset($_SESSION['alerts'])) {
            $this->model->endAlerts = $_SESSION['alerts'];
            $_SESSION['alerts'] = 0;
            unset($_SESSION['alerts']);
        }

        $pagePerm = $this->model->user->getPagePerms($page);

        // Page doesn't exist in DB
        if (is_null($pagePerm)) {
            $this->render('404');
            return false;
        }

        $userLevel = $this->model->user->getUserLevel();
        $username = $this->model->user->getUsername();
        //$uid = $this->model->user->uid;

        if ($userLevel <= $pagePerm) {
            echo $this->twig->render("$page.html", array(
                'siteName' => $this->model->siteName,
                'page' => $page,
                'alerts' => $this->model->endAlerts,
                'stuff' => $stuff,
                'userlevel' => $userLevel,
                'username' => $username
            ));
        } else {
            $this->model->setAlert("danger", "You do not have permission to access that page");
            $_SESSION["login_redirect"] = $_SERVER["REQUEST_URI"];

            header("Location: /login" . $_SERVER["REQUEST_URI"]);
        }
    }

    /**
     * store_in_session - stores a CSRF token in PHP session
     *
     * The view controller handles all CSRF stuff
     * <input type="hidden" name="CSRFName" value="{{ stuff.CSRFName }}">
     * <input type="hidden" name="CSRFToken" value="{{ stuff.CSRFToken }}">
     * [TODO] if you refresh a form multiple times, multiplpe tokens will be stored in sessions
     * 				need to clear them out..
     *
     * @param mixed $key - CSRF key
     * @param mixed $value - CSRF token
     */
    public function store_in_session($key, $value)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $_SESSION[$key] = $value;
    }

    /**
     * unset_session - remove CSRF token from PHP session
     *
     * @param mixed $key
     */
    public function unset_session($key)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        // change contents first
        $this->csrf_generate_token($key);
        // then unset it
        unset($_SESSION[$key]);
    }

    /**
     * get_from_session - retrieve a CSRF token from PHP session
     *
     * @param mixed $key - CSRF key
     */
    public function get_from_session($key)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } else {
            return false;
        }
    }

    /**
     * csrf_validate_token
     *
     * @param mixed $unique_form_name
     * @param mixed $token_value
     */
    public function csrf_validate_token($unique_form_name, $token_value)
    {
        $token = $this->get_from_session($unique_form_name);

        if (!is_string($token_value) || !is_string($token)) {
            return false;
        }
        $result = hash_equals($token, $token_value);
        $this->unset_session($unique_form_name);
        return $result;
    }

    /**
     * csrf_generate_token
     *
     * @param mixed $unique_form_name
     */
    public function csrf_generate_token($unique_form_name)
    {
        if (function_exists('random_bytes')) {
            $token = base64_encode(random_bytes(64)); // PHP 7
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $token = openssl_random_pseudo_bytes(64); // openSSL
        } else {
            $this->model->setAlert("danger", "Cannot generate CSRF token. Requires function random_bytes() or openssl_random_pseudo_bytes()");
            return false;
        }

        $this->store_in_session($unique_form_name, $token);
        return $token;
    }

    /**
     * csrf_validate
     *
     */
    public function csrf_validate()
    {
        if ("POST" == $_SERVER["REQUEST_METHOD"]) {
            // CloudFlare doesn't support HTTP_ORIGIN?
            //if (isset($_SERVER["HTTP_ORIGIN"])) {
            // This website only allows https but we could also get the protocol dynamically
            //$address = "https://".$_SERVER["SERVER_NAME"];
            //if (strpos($address, $_SERVER["HTTP_ORIGIN"]) !== 0) {
            //	//echo "CSRF protection in POST request: detected invalid Origin header: ".$_SERVER["HTTP_ORIGIN"];
            //
            //	$this->model->setAlert('danger', 'Invalid origin header');
            //	return false;
            //}
            //} else {
            //	//echo "No Origin header set.\n";
            //	$this->model->setAlert('danger', 'Invalid origin header');
            //	return false;
            //}

            if ($this->csrf_validate_token($_POST["CSRFName"], $_POST["CSRFToken"])) {
                return true;
            } else {
                $this->model->setAlert('danger', 'CSRF Token invalid');
                return false;
            }
        } else {
            return false;
        }
    }
}
