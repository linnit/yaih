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
        if (isset($_POST['image'])) {
            if (!$this->hasImagePermission($_POST['image'])) {
                $this->model->setAlert("warning", "You do not have permission to edit this item");
                header('Location: /');
                return true;
            }
        }

        switch ($_POST['action']) {
            case 'save':
                $this->account->save($_POST['image']);
                break;
            case 'unsave':
                $this->account->unsave($_POST['image']);
                break;
            case 'delete':
                $this->model->image->deleteImage($_POST["image"]);
                break;
            case 'report':
                $this->model->image->reportImage($_POST["image"], $_POST["reason"]);
                break;
            default:
                echo 'Default case on recent';
                break;
            }
    }

    /**
     * hasImagePermission
     *
     * @param int $imageId
     *
     * @return bool whether the user has permission to edit the image
     */
    function hasImagePermission($imageId) {
        // If user is not logged in
        if (!$this->model->user->userLoggedin) {
            return false;
        }
        $uid = $this->model->user->uid;  

        $imageOwner = $this->model->image->getImageOwner($imageId);

        return ($uid == $imageOwner);
    }


}
