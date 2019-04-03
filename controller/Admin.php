<?php
namespace YAIH\Controller;

class Admin extends Controller
{
    /**
     * Construct Admin object
     *
     * @param obj $model Load the model object
     * @param obj $view  Load the view object
     * @param obj $account  Load the account object
     * @param obj $image  Load the image object
     */
    public function __construct($model, $view, $account, $image)
    {
        $this->model = $model;
        $this->view = $view;
        $this->account = $account;
        $this->image = $image;
    }

    /**
     * Handle POST requests to /admin/settings page
     *
     */
    public function postManageSettings()
    {
        // Save the given settings.
        if (isset($_POST["maintenance"])) {
            $this->model->site->updateMaintenanceMode(1);
        } else {
            $this->model->site->updateMaintenanceMode(0);
        }
        header('Location: /admin/settings');
    }

    /**
     * Handle POST requests to /admin/users page
     *
     * @return bool Status of action
     */
    public function postManageUsers()
    {
        switch ($_POST["action"]) {
            case 'lockuser':
                if ($this->model->user->lockUser($_POST["user"])) {
                    $this->model->setAlert("success", "Locked User");
                    header('Location: /admin/users');
                    return true;
                } else {
                    $this->model->setAlert("warning", "Problem locking user");
                    header('Location: /admin/users');
                    return false;
                }
            break;
            case 'unlockuser':
                if ($this->model->user->unlockUser($_POST["user"])) {
                    $this->model->setAlert("success", "Unlocked User");
                    header('Location: /admin/users');
                    return true;
                } else {
                    $this->model->setAlert("warning", "Problem unlocking user");
                    header('Location: /admin/users');
                    return false;
                }
            break;
            case 'edituser':
                if ($this->model->user->userIdExists($_POST["user"])) {
                    header("Location: /admin/edituser/".$_POST["user"]);
                    return true;
                } else {
                    $this->model->setAlert("warning", "User does not exist");
                    header("Location: /admin/users");
                    return false;
                }
              break;
            case 'deleteuser':
                if ($this->model->user->deleteUser($_POST["user"])) {
                    $this->model->setAlert("success", "Deleted User");
                    header('Location: /admin/users');
                    return true;
                } else {
                    $this->model->setAlert("warning", "Problem deleting user");
                    header('Location: /admin/users');
                    return false;
                }
                break;
            default:
                echo "Default case for users";
                break;
        }
    }

    /**
     * Handle POST requests to /admin & /admin/images
     *
     */
    public function postManageAdmin()
    {
        switch ($_POST["action"]) {
            case 'delete':
                $this->model->image->deleteImage($_POST["image"]);
                break;
            case 'save':
                $this->account->save($_POST['image']);
                break;
            case 'unsave':
                $this->account->unsave($_POST['image']);
                break;
        }
    }

    /**
     * Handle POST requests to /admin/reports
     *
     */
    public function postManageReports()
    {
        switch ($_POST["action"]) {
            case 'delete':
                $this->model->image->deleteImage($_POST["image"]);
                // After the image is delete,
                // we might as well remove the report
                $this->model->image->deleteReport($_POST["report"]);
                break;
            case 'delete_report':
                $this->model->image->deleteReport($_POST["report"]);
                break;
        }
    }

    /**
     * Direct all POST requests to relevant function
     *
     * @param  arr $vars Array of URL values
     *
     * @return bool Return false if hit the default case
     */
    public function handlePostRequest($vars)
    {
        switch ($vars["action"]) {
                case 'users':
                    $this->postManageUsers();
                    break;
                case 'settings':
                    $this->postManageSettings();
                    break;
                case 'reports':
                    $this->postManageReports();
                    break;
                case null:
                    $this->postManageAdmin();
                    break;
                default:
                    break;
        }
    }

    /**
     * Direct all GET requests to relevant function
     *
     * @param  arr $vars Array of URL values
     *
     * @return bool Status of action
     */
    public function handleGetRequest($vars)
    {
        switch ($vars["action"]) {
        case null:
        case 'images':
            $this->image->imageCards($vars, "admin", "/admin/images");
            break;
        case 'edituser':
            if (!isset($vars["variable"])) {
                $this->model->setAlert("warning", "No user ID!");
                header("Location: /admin/users");
                return false;
            }

            if (!$this->model->user->userIdExists($vars["variable"])) {
                $this->model->setAlert("warning", "User does not exist");
                header("Location: /admin/users");
                return false;
            }

            $name = "admin-edituser_" . mt_rand(0, mt_getrandmax());
            $token = $this->view->csrf_generate_token($name);

            $user = $this->model->user->getUser($vars["variable"]);

            $this->view->render("admin-edituser", array("CSRFName" => $name,
                "CSRFToken" => $token, "user" => $user));
            break;
        case 'users':
            $name = "admin-users_" . mt_rand(0, mt_getrandmax());
            $token = $this->view->csrf_generate_token($name);

            $users = $this->model->user->getAllUsers();

            $this->view->render("admin-users", array("CSRFName" => $name,
                "CSRFToken" => $token, "users" => $users));
            break;
        case 'settings':
            $name = "form_" . mt_rand(0, mt_getrandmax());
            $token = $this->view->csrf_generate_token($name);

            $maintenance = $this->model->site->isInMaintenance();

            $this->view->render("admin-settings", array("CSRFName" => $name,
                "CSRFToken" => $token, "maintenance" => $maintenance));
            break;
        case 'reports':
            $this->reportsGetRequest($vars);
            break;
        default:
            echo "Hit default case";
        }
    }

    /**
     * Handle GET requests to /admin/reports
     *
     * @param arr $vars
     */
    public function reportsGetRequest($vars)
    {
        $name = "reports_" . mt_rand(0, mt_getrandmax());
        $token = $this->view->csrf_generate_token($name);

        $page = $this->getPageNumber($vars);
        $reports = $this->model->site->getAllReports($page);
        $pages = $this->model->site->getReportsPageCount();

        $this->view->render("admin-reports", array("CSRFName" => $name,
            "CSRFToken" => $token,
            "reports" => $reports,
            "pageCount" => $pages,
            "currentPageNo" => $page,
            "currentPage" => "/admin/reports"));
    }
}
