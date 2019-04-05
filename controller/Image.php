<?php
namespace YAIH\Controller;

/**
 * Class: Image
 *
 * @see Controller
 */
class Image extends Controller
{
    /**
     * Construct Image object
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

    public function handlePostRequest()
    {
        switch ($_POST['action']) {
            case 'save':
                $this->account->save($_POST['image']);
                break;
            case 'unsave':
                $this->account->unsave($_POST['image']);
                break;
            case 'delete':
                if (!$this->hasImagePermission($_POST['image'])) {
                    $this->model->setAlert("warning", "You do not have permission to edit this item");
                    $this->redirectBack();
                    return true;
                }

                $this->model->image->deleteImage($_POST["image"]);
                break;
            case 'report':
                $this->model->image->reportImage($_POST["image"], $_POST["reason"]);
                break;
            default:
                break;
            }
    }

    /**
     * imageCards
     *
     * @param arr $vars
     * @param str $pageName
     * @param str $url
     */
    public function imageCards($vars, $pageName, $url, $uid = null)
    {
        $page = $this->getPageNumber($vars);

        if (is_null($uid)) {
            $pages = $this->model->image->getPageCount();
        } else {
            $pages = $this->model->image->getUserPageCount();
        }

        $posts = $this->model->image->getRecentImages($uid, $page);

        $csrfName = $pageName . "_" . mt_rand(0, mt_getrandmax());
        $csrfToken = $this->view->csrf_generate_token($csrfName);

        $this->view->render($pageName, array("images" => $posts,
            "CSRFName" => $csrfName,
            "CSRFToken" => $csrfToken,
            "pageCount" => $pages,
            "currentPageNo" => $page,
            "currentPage" => $url));
    }

    /**
     * hasImagePermission
     *
     * @param int $imageId
     *
     * @return bool whether the user has permission to edit the image
     */
    public function hasImagePermission($imageId)
    {
        // If user is not logged in
        if (!$this->model->user->userLoggedin) {
            return false;
        }
        $uid = $this->model->user->uid;

        $imageOwner = $this->model->image->getImageOwner($imageId);

        return ($uid == $imageOwner);
    }
}
