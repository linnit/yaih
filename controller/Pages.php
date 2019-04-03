<?php
namespace YAIH\Controller;

/**
 * Class: Pages
 *
 * @see Controller
 */
class Pages extends Controller
{
    /**
     * [__construct description]
     * @param obj $model Main model object
     * @param obj $view  View controller
     */
    public function __construct($model, $view, $admin, $account, $upload, $image)
    {
        $this->model = $model;
        $this->view = $view;

        $this->admin = $admin;
        $this->account = $account;
        $this->upload = $upload;
        $this->image = $image;
    }

    /**
     * directimage - display an image directly
     *
     * @param mixed $vars
     */
    public function directimage($vars = null)
    {
        $url = preg_replace('/\.[^.\s]+$/', '', $vars["url"]);
        $img = $this->model->image->getImage($url);

        if (empty($img)) {
            $contents =  file_get_contents("{$this->model->imageDir}/error.png");
        } else {
            $splitted = str_split($img["url"]);
            $fullpath = "{$this->model->imageDir}/{$splitted[0]}/{$splitted[1]}/{$img["url"]}";
            $contents =  file_get_contents($fullpath);
        }
        $this->serveImage($contents);
    }

    /**
     * directthumb - display a thumbnail image directly
     *
     * @param mixed $vars
     */
    public function directthumb($vars = null)
    {
        $url = preg_replace('/\.[^.\s]+$/', '', $vars["url"]);
        $img = $this->model->image->getImage($url);

        if (empty($img)) {
            $contents =  file_get_contents("{$this->model->imageDir}/error.png");
        } else {
            $splitted = str_split($img["url"]);
            $fullpath = "{$this->model->thumbDir}/{$splitted[0]}/{$splitted[1]}/{$img["url"]}";

            if (file_exists($fullpath)) {
                $contents =  file_get_contents($fullpath);
            } else {
                $contents =  file_get_contents("{$this->model->imageDir}/error.png");
            }
        }
        $this->serveImage($contents);
    }

    /**
     * serveImage
     *
     * @param mixed $contents
     */
    public function serveImage($contents)
    {
        $expires = 14 * 60*60*24;

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($contents);

        header("Content-Type: {$mimeType}");
        header("Content-Length: " . strlen($contents));
        header("Cache-Control: public", true);
        header("Pragma: public", true);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT', true);
        echo $contents;
    }

    /**
     * pageimage - image page e.g /img/[img_code]
     *
     * @param mixed $vars
     */
    public function pageimage($vars = null)
    {
        $img = $this->model->image->getImage($vars["url"]);
        if (!$img) {
            $this->view->render("404", $vars);
        } else {

      // Add to history
            if ($this->model->user->userLoggedin) {
                $uid = $this->model->user->uid;
                $this->model->image->addToHistory($img["id"], $uid);
            }

            $this->view->render("image", array("image" => $img,
                "siteurl" => $this->model->siteURL));
        }
    }

    /**
     * If / is requested, render the index page
     *
     */
    public function index($vars = null)
    {
        // Handle file uploads
        if ($this->view->csrf_validate()) {
            $this->upload->handlePostRequest($vars);
            return 0;
        }

        if ($this->model->user->userLoggedin) {
            $uid = $this->model->user->uid;
        } else {
            $uid = null;
        }

        $uploadname = "upload_" . mt_rand(0, mt_getrandmax());
        $uploadtoken = $this->view->csrf_generate_token($uploadname);

        $this->view->render("index", array("CSRFUploadName" => $uploadname,
            "CSRFUploadToken" => $uploadtoken));
    }

    /**
     * user - display a user's profile page
     *
     * @param mixed $vars
     */
    public function user($vars = null)
    {
        if ($this->view->csrf_validate()) {
            $this->image->handlePostRequest();
            return 0;
        }

        if (isset($vars["pageno"])) {
            $page = $vars["pageno"];
        } else {
            $page = 1;
        }

        $uid = $this->model->user->getUid($vars["username"]);

        $pages = $this->model->image->getUserPageCount($uid);
        $posts = $this->model->image->getRecentImages($uid, $page);
        //$posts = $this->model->image->getUserImages($uid, $page);

        $csrfName = "user_" . mt_rand(0, mt_getrandmax());
        $csrfToken = $this->view->csrf_generate_token($csrfName);

        $this->view->render("user", array("username" => $vars["username"],
            "CSRFName" => $csrfName,
            "CSRFToken" => $csrfToken,
            "images" => $posts,
            "pageCount" => $pages,
            "currentPageNo" => $page,
            "currentPage" => "/user/{$vars['username']}"));
    }

    /**
     * recent - display recently uploaded images
     *
     * @param mixed $vars
     */
    public function recent($vars = null)
    {
        if ($this->view->csrf_validate()) {
            $this->image->handlePostRequest();
            return 0;
        }

        $this->image->imageCards($vars, "recent", "/recent");
    }

    /**
     * If /admin is requested, render the admin page
     *
     * @param array $vars Possible actions that can be performed on the admin page
     *
     */
    public function admin($vars)
    {
        $userLevel = $this->model->user->getUserLevel();
        $username = $this->model->user->getUsername();
        if ($userLevel > 1) {
            $this->model->setAlert("danger", "You do not have permission to access that page");
            header('Location: /');
            return true;
        }

        if ($this->view->csrf_validate()) {
            $this->admin->handlePostRequest($vars);
        } else {
            $this->admin->handleGetRequest($vars);
        }
    }

    /**
     * Handles all requests to /forgotpassword
     *
     * @return bool
     */
    public function forgotpassword()
    {
        if ($this->model->user->userLoggedin) {
            // If the user is already logged in, they don't need to see the login page ever
            if ($this->model->user->getUserLevel() != 0) {
                // If their user level isn't on an admin level, send them to /customer
                header('Location: /account');
            } else {
                // If they're an admin, send them to /admin
                header('Location: /admin');
            }
            return true;
        }
        $forgotname = "forgot" . mt_rand(0, mt_getrandmax());
        $forgottoken = $this->view->csrf_generate_token($forgotname);

        $this->view->render("forgotpassword", array("CSRFForgotName" => $forgotname,
      "CSRFForgotToken" => $forgottoken));
    }

    /**
     * register
     *
     */
    public function register()
    {
        if ($this->model->user->userLoggedin) {
            // If the user is already logged in, they don't need to see the register page
            if ($this->model->user->getUserLevel() != 0) {
                // If their user level isn't on an admin level, send them to /account
                header('Location: /account');
            } else {
                // If they're an admin, send them to /admin
                header('Location: /admin');
            }
            return true;
        }
        if (!$this->view->csrf_validate()) {
            header("Location: /login");
            return false;
        } elseif (isset($_POST["username"])) {
            if ($this->model->user->register()) {
                header('Location: /account');
            } else {
                header('Location: /login');
            }
            return true;
        }
    }

    /**
     * Handles all requests to /login
     *
     * @return bool
     */
    public function login($vars)
    {
        if ($this->model->user->userLoggedin) {
            // If the user is already logged in, they don't need to see the login page ever
            if ($this->model->user->getUserLevel() != 0) {
                // If their user level isn't on an admin level, send them to /customer
                header('Location: /account');
            } else {
                // If they're an admin, send them to /admin
                header('Location: /admin');
            }
            return true;
        }

        if (!$this->view->csrf_validate()) {
            $loginname = "login_" . mt_rand(0, mt_getrandmax());
            $logintoken = $this->view->csrf_generate_token($loginname);

            $registername = "register_" . mt_rand(0, mt_getrandmax());
            $registertoken = $this->view->csrf_generate_token($registername);

            $this->view->render("login", array("CSRFLoginName" => $loginname,
            "CSRFLoginToken" => $logintoken,
            "CSRFRegisterName" => $registername,
            "CSRFRegisterToken" => $registertoken));
        } elseif (isset($_POST["login"])) {
            if (!$this->model->user->login()) {
                // Login failed, send back to login page
                header('Location: /login');

                return true;
            }

            if (is_null($vars["redirect1"])) {
                if ($this->model->user->getUserLevel() != 0) {
                    header('Location: /account');
                } else {
                    header('Location: /admin');
                }

                return true;
            } else {
                $uri_redirect = "";
                $uri_redirect .= $vars["redirect1"];
                if (!is_null($vars["redirect2"])) {
                    $uri_redirect .= $vars["redirect2"];
                }

                if (!isset($_SESSION["login_redirect"])) {
                    header('Location: /login');
                    return true;
                }

                if ($uri_redirect == str_replace('/', '', $_SESSION["login_redirect"])) {
                    header('Location: ' . $_SESSION["login_redirect"]);
                    unset($_SESSION["login_redirect"]);
                    return true;
                }
            }
        }
    }

    /**
     * Handle requests to /logout
     *
     * @return bool
     */
    public function logout()
    {
        $this->model->user->logout();

        header('Location: /');
        return true;
    }

    /**
     * If /account is requested render the customer pages
     * The view controller checks if the user is logged in
     * and if not re-direct them to /login
     *
     */
    public function account($vars)
    {
        $userLevel = $this->model->user->getUserLevel();
        $username = $this->model->user->getUsername();
        if ($userLevel > 5) {
            $this->model->setAlert("danger", "You do not have permission to access that page");
            header('Location: /');
            return true;
        }

        if ($this->view->csrf_validate()) {
            $this->account->handlePostRequest($vars);
        } else {
            $this->account->handleGetRequest($vars);
        }
    }

    /**
     * [resetPassword description]
     */
    public function resetPassword()
    {
        if (!$this->view->csrf_validate()) {
            $name = "reset_" . mt_rand(0, mt_getrandmax());
            $token = $this->view->csrf_generate_token($name);

            $this->view->render("resetpassword", array("CSRFName" => $name, "CSRFToken" => $token));
        } elseif (isset($_POST["password1"])) {
            if ($_POST["password1"] === $_POST["password2"]) {
                $this->model->user->resetPassword($this->model->user->uid, $_POST["password1"]);
                $this->model->setAlert("success", "Successfully changed password");
                header('Location: /account'); // [TODO] Will we always redirect to /account?
                return true;
            } else {
                $this->model->setAlert("warning", "Passwords do not match");
                header('Location: /resetpassword');
                return true;
            }
        }
    }

    public function abuse()
    {
        $this->view->render("abuse");
    }

    public function faq()
    {
        $this->view->render("faq");
    }

    public function termsOfService()
    {
        $this->view->render("termsofservice");
    }

    public function privacyPolicy()
    {
        $this->view->render("privacypolicy");
    }

    public function fourOhFour($vars)
    {
        $this->view->render("404", $vars);
    }
}
