<?php
namespace YAIH\Controller;

/**
 * Class: Account
 *
 * @see Controller
 */
class Account extends Controller
{
    /**
     * Construct Account object
     *
     * @param obj $model Load the model object
     * @param obj $view  Load the view object
     * @param obj $parent  Load the parent object
     */
    public function __construct($model, $view, $parent)
    {
        $this->model = $model;
        $this->view = $view;
        $this->parent = $parent;
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
        if (isset($_POST['image'])) {
            if (!$this->parent->image->hasImagePermission($_POST['image'])) {
                $this->model->setAlert("warning", "You do not have permission to edit this item");
                header('Location: /');
                return true;
            }
        }

        switch ($_POST["action"]) {
        case null:
            $this->view->render("account");
            break;
        case 'save':
            $this->save($_POST['image']);
            break;
        case 'unsave':
            $this->unsave($_POST['image']);
            break;
        case 'delete':
            $this->model->image->deleteImage($_POST["image"]);
            break;
        default:
            return false;
        }
        return true;
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
        if (!$this->model->user->userLoggedin) {
            $this->model->setAlert("warning", "You need to be logged in to view this page");
            header("Location: /login");
            return false;
        }

        $uid = $this->model->user->uid;

        switch ($vars["action"]) {
            case null:
                $this->parent->image->imageCards(
                    $vars,
                    "account",
                    "/account/images",
                    $this->model->user->uid
                );
                break;
            case 'images':
                $this->parent->image->imageCards(
                    $vars,
                    "account",
                    "/account/images",
                    $this->model->user->uid
                );
                break;
            case 'saved':
                $this->savedGetRequest($vars);
                break;
            case 'history':
                $pageNumber = $this->getPageNumber($vars);
                $posts = $this->model->image->getHistory($uid, $pageNumber);
                $pages = $this->model->image->getHistoryPageCount($uid);

                $this->view->render("account-history", array("posts" => $posts,
                        "currentPage" => "/account/history",
                        "currentPageNo" => $pageNumber,
                        "pageCount" => $pages));
                break;
            case 'submitted':
                $this->view->render("account-submitted");
                break;
            case 'settings':
                $this->view->render("account-settings");
                break;
            default:
                $this->view->render("404");
                break;
        }
    }

    /*
     * savedGetRequest - get requests to /account/saved
     *
     * @param arr $vars URL parameters e.g page number
     *
     */
    public function savedGetRequest($vars)
    {
        $uid = $this->model->user->uid;

        $pageNumber = $this->getPageNumber($vars);

        $pages = $this->model->image->getSavedPageCount($uid);

        // todo - maybe if $pageNumber > $pages .. ?
        if ($pageNumber > 1 && $pages == 1) {
            header("Location: /account/saved");
            exit;
        }

        $posts = $this->model->image->getSaved($uid, $pageNumber);

        $csrfName = "saved_" . mt_rand(0, mt_getrandmax());
        $csrfToken = $this->view->csrf_generate_token($csrfName);

        $this->view->render("account-saved", array("posts" => $posts,
                            "CSRFName" => $csrfName,
                            "CSRFToken" => $csrfToken,
                            "currentPage" => "/account/saved",
                            "currentPageNo" => $pageNumber,
                            "pageCount" => $pages));
    }

    /*
     *
     * @param int $id Image id to save
     */
    public function save($id)
    {
        if (!$this->model->user->userLoggedin) {
            $this->model->setAlert("warning", "You need to be logged in to save items.");
            header("Location: /login");
            return false;
        }

        $uid = $this->model->user->uid;

        $this->model->image->saveItem($id, $uid);

        if (!isset($_SERVER['HTTP_REFERER'])) {
            header('Location: /');
        } else {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        }

        return true;
    }

    /**
     *
     * @param int $id Image id to remove from saved
     */
    public function unsave($id)
    {
        if (!$this->model->user->userLoggedin) {
            $this->model->setAlert("warning", "You need to be logged in to unsave items.");
            header("Location: /login");
            return false;
        }

        $uid = $this->model->user->uid;

        $this->model->image->deleteSavedItem($id, $uid);

        if (!isset($_SERVER['HTTP_REFERER'])) {
            header('Location: /');
        } else {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        }

        return true;
    }
}
