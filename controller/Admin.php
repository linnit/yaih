<?php
namespace YAIH\Controller;

class Admin extends Controller
{
    /**
     * Construct Admin object
     *
     * @param obj $model Load the model object
     * @param obj $view  Load the view object
     */
    public function __construct($model, $view, $account)
    {
        $this->model = $model;
        $this->view = $view;
        $this->account = $account;
    }

    /**
     * Handle POST requests to /addproduct page
     *
     * @return bool Status of action
     */
    public function postAddProduct()
    {
        $productId = $this->model->product->addNewProduct(
            $_POST["product"],
            $_POST["description"],
            $_POST["price"],
            $_POST["stripe_sub_plan"]
        );

        for ($i = 0; $i <= $_POST["no_features"]; $i++) {
            if (isset($_POST["feature".$i])) {
                if ($_POST["feature".$i] == "") {
                    continue;
                }
                $this->model->product->addProductFeature($productId, $_POST["feature".$i]);
            }
        }
        $this->model->setAlert("success", "Added new product!");
        header('Location: /admin/addproduct');
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
                // no break
            default:
              echo "Default case for users";
              break;
        }
    }

    /**
     * Handle POST requests to /manageproducts
     *
     * @return bool Status of action
     */
    public function postManageProducts()
    {
        switch ($_POST["action"]) {
            case 'deleteproduct':
                if ($this->model->product->deleteProduct($_POST["product"])) {
                    $this->model->setAlert("success", "Deleted Product");
                    header('Location: /admin/manageproducts');
                    return true;
                } else {
                    $this->model->setAlert("warning", "Problem deleting product");
                    header('Location: /admin/manageproducts');
                    return false;
                }
            break;
            case 'editproduct':
                if ($this->model->product->productIdExists($_POST["product"])) {
                    header("Location: /admin/editproduct/".$_POST["product"]);
                    return true;
                } else {
                    $this->model->setAlert("warning", "Product does not exist");
                    header("Location: /admin/manageproducts");
                    return false;
                }
                break;
            default:
                echo "Default case for manageproducts";
                break;
        }
    }

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
                case null:
                    $this->postManageAdmin();
                    break;
                default:
                    break;
        }
    }

    /**
     * viewImages
     *
     * @param mixed $vars
     */
    public function viewImages($vars)
    {
        $page = $this->getPageNumber($vars);

        $pages = $this->model->image->getPageCount();
        $posts = $this->model->image->getRecentImages(null, $page);

        $csrfName = "admin_" . mt_rand(0, mt_getrandmax());
        $csrfToken = $this->view->csrf_generate_token($csrfName);

        $this->view->render("admin", array("images" => $posts,
            "CSRFName" => $csrfName,
            "CSRFToken" => $csrfToken,
            "pageCount" => $pages,
            "currentPageNo" => $page,
            "currentPage" => "/admin/images"));
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
            $this->viewImages($vars);
            break;
        case 'images':
            $this->viewImages($vars);
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
            $name = "reports_" . mt_rand(0, mt_getrandmax());
            $token = $this->view->csrf_generate_token($name);

            $page = $this->getPageNumber($vars);
            $reports = $this->model->site->getAllReports($page);

            $this->view->render("admin-reports", array("CSRFName" => $name,
                "CSRFToken" => $token, "reports" => $reports));
            break;
        default:
            echo "Hit default case";
        }
    }
}
