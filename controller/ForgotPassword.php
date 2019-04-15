<?php
namespace YAIH\Controller;

/**
 * Class: ForgotPassword
 *
 * @see Controller
 */
class ForgotPassword extends Controller
{
    /**
     *
     * @param obj $model Load the model object
     * @param obj $view  Load the view object
     */
    public function __construct($model, $view)
    {
        $this->model = $model;
        $this->view = $view;
    }

    /**
     * Direct all /forgotpassword requests
     *
     * @param arr $vars
     */
    public function entry($vars)
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

        if ($this->view->csrf_validate()) {
            $this->handlePostRequest();
            exit;
        }

        if (!empty($vars["token"])) {
            $this->handleResetPage($vars);
            exit;
        }

        $forgotname = "forgot_" . mt_rand(0, mt_getrandmax());
        $forgottoken = $this->view->csrf_generate_token($forgotname);

        $this->view->render("forgotpassword", array("CSRFForgotName" => $forgotname,
            "CSRFForgotToken" => $forgottoken));
    }

    /**
     * handleResetPage
     *
     */
    public function handleResetPage($vars)
    {
        $tokenStatus = $this->model->user->checkForgotToken($vars["token"]);
        if ($tokenStatus) {
            $forgotname = "forgot_" . mt_rand(0, mt_getrandmax());
            $forgottoken = $this->view->csrf_generate_token($forgotname);

            $this->view->render("forgotpassword-reset", array("CSRFForgotName" => $forgotname,
  "CSRFForgotToken" => $forgottoken));
            exit;
        } else {
            $this->model->setAlert("warning", "Token does not exist or expired");
            header('Location: /forgotpassword');
            exit;
        }
    }

    public function handlePostRequest()
    {
        switch ($_POST['action']) {
            case 'reset':
                var_dump($_POST);
                exit;
                break;
            case 'email':
                $user = $this->model->user->findUser($_POST["login"]);

                // $_POST["login"] = "" will return users with no email, or 'anonymous' user
                if (empty($user) || empty($_POST["login"])) {
                    $this->model->setAlert("warning", "No user found!");
                    header('Location: /forgotpassword');
                    exit;
                }

                $token = $this->model->user->createForgotToken($user['id']);

                echo "Emailing - " . $user["email"];
                $this->model->user->emailUser($user["email"], "You have forgot password, token here: $token");
                break;
            default:
                break;
            }
    }
}
