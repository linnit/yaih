<?php
namespace YAIH\Controller;

/**
 * Class: Upload
 *
 * @see Controller
 */
class Upload extends Controller
{
    /**
     * Construct Upload object
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
     * Direct all POST requests to relevant function
     *
     * @param  arr $vars Array of URL values
     *
     * @return bool Return false if hit the default case
     */
    public function handlePostRequest($vars)
    {
        if (empty($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
            $this->model->setAlert("warning", "An image is required");
            header('Location: /');
            exit;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $fileContents = file_get_contents($_FILES['file']['tmp_name']);
        $mimeType = $finfo->buffer($fileContents);

        $filepath = false;
        while (!$filepath) {
            $filepath = $this->create_image_filename();
        }

        $filename = basename($filepath);

        if ($mimeType != 'image/jpeg'
            && $mimeType != 'image/png'
            && $mimeType != 'video/mp4'
            && $mimeType != 'image/gif') {
            $this->model->setAlert("warning", "Only png/jpg/gif/mp4 images can be uploaded");
            header('Location: /');
            exit;
        }
    
        if ($mimeType == 'image/jpeg') {
            if (!$this->model->image->removeImageEXIFData($_FILES["file"]["tmp_name"])) {
                die("Error removing EXIF data");
            }
        }

        if (!move_uploaded_file($_FILES["file"]["tmp_name"], $filepath)) {
            die("Error uploading file.");
        }

        if ($mimeType == 'video/mp4') {
            $video = new \YAIH\Model\Video();

            $splitted = str_split($filename);                                            
            $thumbnail_path = "{$this->model->thumbDir}/{$splitted[0]}/{$splitted[1]}/$filename";

            //$sanitised_filename = $video->sanitise_filename($filename);

            //if (!$sanitised_filename) {
            //    $this->model->setAlert("danger", "An error occurred");
            //    return false;
            //}
            
            $time = $video->get_timestamp($filepath);

            $video->make_thumbnail($filepath, $time, $thumbnail_path);
        } else {
            // Make thumbnail
            $this->model->image->makeThumbnail($fileContents, $filename);
        }

        $this->model->image->addNewPost("Test Image", $filename, $mimeType);
        header("Location: {$this->model->siteURL}/img/{$filename}");
        exit;
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
        $uid = $this->model->user->uid;
        echo "Get request on upload controller, uid: $uid";
    }

    private function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;

        if ($max < 1) {
            throw new \Exception('$keyspace must be at least two characters long');
        }
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }

        return $str;
    }

    public function create_image_filename()
    {
        // 5 character name with azAZ09 = 916132832 possible images
        $filename = $this->random_str(5);
        $splitted = str_split($filename);

        $fullpath = "{$this->model->imageDir}/{$splitted[0]}/{$splitted[1]}/$filename";

        if (file_exists($fullpath)) {
            return false;
        } else {
            return $fullpath;
        }
    }
}
