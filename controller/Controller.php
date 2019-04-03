<?php
namespace YAIH\Controller;

require 'model/Model.php';
require 'controller/View.php';
require 'controller/Pages.php';
require 'controller/Admin.php';
require 'controller/Account.php';
require 'controller/Upload.php';
require 'controller/Image.php';

class Controller
{
    public function __construct()
    {
        $this->model = new \YAIH\Model\Model();
        $this->view = new \YAIH\Controller\View($this->model);
        $this->account = new \YAIH\Controller\Account($this->model, $this->view, $this);
        $this->upload = new \YAIH\Controller\Upload($this->model, $this->view);
        $this->image = new \YAIH\Controller\Image($this->model, $this->view, $this->account);
        $this->admin = new \YAIH\Controller\Admin($this->model,
            $this->view,
            $this->account,
            $this->image);
        $this->pages = new \YAIH\Controller\Pages(
            $this->model,
            $this->view,
            $this->admin,
            $this->account,
            $this->upload,
            $this->image
        );

        // [todo] what is this for
        $this->actioned		= false;
    }

    /**
     * invoke - All possible URI requests reside in this function
     *
     */
    public function invoke()
    {
        /**
         *
         *	$this->request("/search/{required}/?{optional}", "");
         *
         */

        // Index
        $this->request("/", "pages@index");
        $this->request("/recent/?{pageno}", "pages@recent");

        // View image
        $this->request("/i/{url}", "pages@directimage");
        $this->request("/t/{url}", "pages@directthumb");
        $this->request("/img/{url}", "pages@pageimage");

        // Login page
        $this->request("/login/?{redirect1}/?{redirect2}", "pages@login");
        $this->request("/forgotpassword", "pages@forgotpassword");
        // Logout
        $this->request("/logout", "pages@logout");

        // Register
        $this->request("/register", "pages@register");

        $this->request("/account/?{action}/?{variable}", "pages@account");

        $this->request("/user/{username}/?{pageno}", "pages@user");

        $this->request("/resetpassword", "pages@resetPassword"); // [todo]
        $this->request("/privacy-policy", "pages@privacyPolicy");

        $this->request("/admin/?{action}/?{variable}", "pages@admin");

        $this->request("/{404}", "pages@fourOhFour");
    }

    /**
     * Parse request and run desired function
     *
     * [TODO] Maybe allow query strings in the URI?
     *
     * @param  str $request Possible URI request
     * @param  str $action  Controller and function to run if request matches
     *
     * @return bool
     */
    public function request($request, $action)
    {
        $definedURI = explode('/', $request);
        $requestURI = explode('/', $_SERVER["REQUEST_URI"]);

        // Remove the empty element
        array_shift($definedURI);
        array_shift($requestURI);

        // Make sure the request meets the minimum number of stuff
        $notRequired = substr_count($request, '?');
        $required = count($definedURI) - $notRequired;
        if (count($requestURI) < $required) {
            return false;
        }

        // Check if the URI matches
        list($actions, $vars) = $this->uriMatch($definedURI, $requestURI);

        if (!$actions && is_null($vars)) {
            //echo "no actions";
            return false;
        }

        $split_action = explode('@', $action);

        $obj = $split_action[0];
        $func = $split_action[1];

        if (count($vars) >= 1) {
            $this->$obj->$func($vars);
            exit;
        } else {
            $this->$obj->$func();
            exit;
        }
    }

    /**
     * uriMatch - check if a requested uri matches a defined one
     *
     * @param mixed $definedURI
     * @param mixed $requestURI
     */
    public function uriMatch($definedURI, $requestURI)
    {
        $actions = array();
        $vars = array();

        for ($i = 0; $i < count($definedURI); $i++) {
            // Check if a variable

            if (preg_match('/(\?)?{(.*)}/', $definedURI[$i], $match)) {

                // Is the arg. required
                if ($match[1] == "?") {
                    if (isset($requestURI[$i])) {
                        $vars[$match[2]] = $requestURI[$i];
                    } else {
                        $vars[$match[2]] = null;
                    }
                } else {
                    // arg. is required, return false if isn't set
                    if (!isset($requestURI[$i])) {
                        //echo "arg. is required, return false if isn't set";
                        return false;
                    }

                    $vars[$match[2]] = $requestURI[$i];
                }
            } elseif ($definedURI[$i] != $requestURI[$i]) {
                return false;
            } else {
                // Not a variable, add to actions
                array_push($actions, $definedURI[$i]);
            }
        }

        return array($actions, $vars);
    }

    /**
     * Using URL variables, determine page number
     *
     * @param arr $vars
     *
     * @return int User's current page number
     */
    public function getPageNumber($vars)
    {
        if (isset($vars["variable"])) {
            $page = $vars["variable"];
        } elseif (isset($vars["pageno"])) {
            $page = $vars["pageno"];
        } else {
            $page = 1;
        }

        return $page;
    }
}
