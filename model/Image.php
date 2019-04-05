<?php
namespace YAIH\Model;

define('TIMEBEFORE_NOW', 'Just now');
define('TIMEBEFORE_MINUTE', '{num} minute ago');
define('TIMEBEFORE_MINUTES', '{num} minutes ago');
define('TIMEBEFORE_HOUR', '{num} hour ago');
define('TIMEBEFORE_HOURS', '{num} hours ago');
define('TIMEBEFORE_YESTERDAY', 'Yesterday');
define('TIMEBEFORE_FORMAT', '%e %b');
define('TIMEBEFORE_FORMAT_YEAR', '%e %b, %Y');

/**
 * Class: Image
 *
 * @see Model
 */
class Image extends Model
{
    public function __construct($parent)
    {
        $this->parent	= $parent;
        $this->db	= $this->parent->db;

        $this->itemPerPage = 3;
    }

    /**
     * getRecentImages
     *
     * @param int $uid Optional - User ID
     * @param int $page
     */
    public function getRecentImages($uid = null, $page = null)
    {
        if (empty($page)) {
            $page = 0;
        } else {
            $page = ($page-1) * $this->itemPerPage;
        }
    
        if (is_null($uid)) {
            $stmt = $this->db->prepare("SELECT post.id, uid, title, url, created, username FROM post INNER JOIN user ON post.uid = user.id ORDER BY created DESC LIMIT :page,:ipp");
        } else {
            $stmt = $this->db->prepare("SELECT post.id, uid, title, url, created, username FROM post INNER JOIN user ON post.uid = user.id WHERE uid = :uid ORDER BY created DESC LIMIT :page,:ipp");
            $stmt->bindValue(":uid", $uid, \PDO::PARAM_INT);
        }

        $stmt->bindValue(":page", $page, \PDO::PARAM_INT);
        $stmt->bindValue(":ipp", $this->itemPerPage, \PDO::PARAM_INT);
        $stmt->execute();

        $posts = $stmt->fetchAll();

        foreach ($posts as $i => $post) {
            $posts[$i]["fullurl"] = "{$this->parent->siteURL}/i/{$post["url"]}";
            $posts[$i]["thumburl"] = "{$this->parent->siteURL}/t/{$post["url"]}";

            $created = strtotime($post["created"]);
            $posts[$i]["created"] = $this->timeAgo($created);
        }

        // Find saved posts
        if ($this->parent->user->userLoggedin && !empty($posts)) {
            $logged_uid = $this->parent->user->uid;
            $pids = array();
            foreach ($posts as $post) {
                array_push($pids, $post["id"]);
            }
            $inPids = implode(',', array_fill(0, count($pids), '?'));
            $stmt = $this->db->prepare("SELECT pid FROM saved WHERE uid = ? AND pid IN (" . $inPids . ")");

            $i = 0;
            $stmt->bindValue(($i+=1), $logged_uid);
            foreach ($pids as $pid) {
                $stmt->bindValue(($i+=1), $pid);
            }

            $stmt->execute();

            $saved = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // Add 'saved' flag to each saved post
            foreach ($posts as $i => $post) {
                if (in_array($post["id"], $saved)) {
                    $posts[$i]["saved"] = "true";
                }
            }
        }

        return $posts;
    }


    /**
     * timeAgo Calculate a human friendly date e.g 'Just now' '1 hour ago'
     *
     * @param int $time UNIX timestamp
     */
    public function timeAgo($time)
    {
        $out    = ''; // what we will print out
        $now    = time(); // current time
        $diff   = $now - $time; // difference between the current and the provided dates

        if ($diff < 60) { // it happened now
            return TIMEBEFORE_NOW;
        } elseif ($diff < 3600) { // it happened X minutes ago
            return str_replace('{num}', ($out = round($diff / 60)), $out == 1 ? TIMEBEFORE_MINUTE : TIMEBEFORE_MINUTES);
        } elseif ($diff < 3600 * 24) { // it happened X hours ago
            return str_replace('{num}', ($out = round($diff / 3600)), $out == 1 ? TIMEBEFORE_HOUR : TIMEBEFORE_HOURS);
        } elseif ($diff < 3600 * 24 * 2) { // it happened yesterday
            return TIMEBEFORE_YESTERDAY;
        } else { // falling back on a usual date format as it happened later than yesterday
            return strftime(date('Y', $time) == date('Y') ? TIMEBEFORE_FORMAT : TIMEBEFORE_FORMAT_YEAR, $time);
        }
    }

    /**
     * getImage Get image information from the database
     *
     * @param str $url
     * @param int $id Alternative way to retrieve image information using ID
     *     instead of using the url
     *
     * @return arr $image
     */
    public function getImage($url, $id = null)
    {
        if (is_null($id)) {
            $stmt = $this->db->prepare("SELECT * FROM post WHERE url = :url");
            $stmt->bindValue(":url", $url);
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare("SELECT * FROM post WHERE id = :id");
            $stmt->bindValue(":id", $id);
            $stmt->execute();
        }


        $image = $stmt->fetch();

        if (empty($image)) {
            return false;
        }

        $image["full_url"] = "{$this->parent->siteURL}/i/{$image['url']}.";
        $image["thumb_url"] = "{$this->parent->siteURL}/t/{$image["url"]}.";

        switch ($image["mimetype"]) {
        case 'image/jpeg':
            $image["full_url"] .= 'jpg';
            $image["thumb_url"] .= 'jpg';
            break;
        case 'image/png':
            $image["full_url"] .= 'png';
            $image["thumb_url"] .= 'png';
            break;
        case 'image/gif':
            $image["full_url"] .= 'gif';
            $image["thumb_url"] .= 'gif';
            break;
        case 'video/mp4':
            $image["full_url"] .= 'mp4';
            $image["thumb_url"] .= 'jpg';
            break;
        }

        return $image;
    }

    /**
     * Find the image's owner using the image id
     *
     * @param int $imageId
     *
     * @return int User ID
     */
    public function getImageOwner($imageId)
    {
        $stmt = $this->db->prepare("SELECT uid FROM post WHERE id = :id");
        $stmt->bindValue(":id", $imageId);
        $stmt->execute();

        $image = $stmt->fetch();

        if (empty($image)) {
            return false;
        }

        return $image["uid"];
    }

    /**
     * getImageFromId Get image information from the database using the ID
     *
     * @param int $id
     *
     * @return arr $image
     */
    public function getImageFromId($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM post WHERE id = :id");
        $stmt->bindValue(":id", $id);
        $stmt->execute();

        $image = $stmt->fetch();

        if (empty($image)) {
            return false;
        }

        return $image;
    }

    /**
     * Check if given post exists
     *
     * @param  int $id Post ID
     *
     * @return bool     Status of existence
     */
    public function postIdExists($id)
    {
        $stmt = $this->db->prepare("SELECT id FROM post WHERE id = :id");

        $stmt->bindParam(":id", $id);
        $stmt->execute();

        return count($stmt->fetchAll()) == 1;
    }

    /**
     * Add a new post
     *
     * @param str $title       Title of the image
     * @param str $url  image url
     * @param str $mimetype  Image mimetype (png/jpg)
     *
     * @return int ID of newly inserted product
     */
    public function addNewPost($title, $url, $mimetype)
    {
        if (!$this->parent->user->userLoggedin) {
            $uid = 0;
        } else {
            $uid = $this->parent->user->uid;
        }
        $stmt = $this->db->prepare("INSERT INTO post VALUES(NULL, :uid, NULL, :title, :url, :mimetype)");
        $stmt->bindValue(":uid", $uid);
        $stmt->bindValue(":title", $title);
        $stmt->bindValue(":url", $url);
        $stmt->bindValue(":mimetype", $mimetype);

        $stmt->execute();

        return $this->db->lastInsertId();
    }

    /**
     * Delete given image from the database
     *
     * @param  int $id ID of image to delete
     *
     */
    public function deleteImage($id)
    {
        $image = $this->getImageFromId($id);
        // [todo] test below and remove `getImageFromId` method
        //$image = $this->getImage(null, $id);

        // todo
        // check the user is admin, or user owns the image
        // if logged in
        // if image-uid = user-uid or userlevel < 1

        $splitted = str_split($image["url"]);
        $fullpath = "{$this->parent->imageDir}/{$splitted[0]}/{$splitted[1]}/{$image["url"]}";
        $thumbpath = "{$this->parent->thumbDir}/{$splitted[0]}/{$splitted[1]}/{$image["url"]}";
        unlink($fullpath);
        unlink($thumbpath);

        $stmt = $this->db->prepare("DELETE FROM post WHERE id = :id");
        $stmt->bindValue(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount()) {
            $this->parent->setAlert("success", "Deleted image");
        } else {
            $this->parent->setAlert("warning", "Deleting image failed");
        }

        if (!isset($_SERVER['HTTP_REFERER'])) {
            header('Location: /account');
        } else {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        }
    }

    /*
     * Get the page count of images for a given user
     *
     * @return int $totalPages Total number of pages
     */
    public function getUserPageCount($uid = null)
    {
        if (is_null($uid)) {
            if ($this->parent->user->userLoggedin) {
                $uid = $this->parent->user->uid;
            } else {
                return false;
            }
        }

        $stmt = $this->db->prepare("SELECT count(*) FROM post WHERE uid = :uid");

        $stmt->bindValue(":uid", $uid, \PDO::PARAM_INT);

        $stmt->execute();
        $count = $stmt->fetch();

        $totalPages = ceil($count[0] / $this->itemPerPage);

        return $totalPages;
    }

    /**
     * Get the page count of images given user has saved
     *
     * @param int $uid
     */
    public function getSavedPageCount($uid)
    {
        $stmt = $this->db->prepare("SELECT count(*) FROM post,saved WHERE post.id = saved.pid AND saved.uid = :uid");
        $stmt->bindValue(":uid", $uid, \PDO::PARAM_INT);

        $stmt->execute();
        $count = $stmt->fetch();

        $totalPages = ceil($count[0] / $this->itemPerPage);

        return $totalPages;
    }

    /*
     * Get the total page count of all images
     *
     * @return int $totalPages Total number of pages
     */
    public function getPageCount()
    {
        $stmt = $this->db->prepare("SELECT count(*) FROM post INNER JOIN user ON post.uid = user.id");

        $stmt->execute();
        $count = $stmt->fetch();

        $totalPages = ceil($count[0] / $this->itemPerPage);

        return $totalPages;
    }

    /**
     *
     * Create a thumbnail image
     *
     * @param str $imgData The image data to resize
     * @param str $filename The filename to save into out thumbnail directory
     *
     */
    public function makeThumbnail($imgData, $filename)
    {
        $newWidth = 300;
        $newHeight = 300;
        $image = ImageCreateFromString($imgData);
        $width = ImageSX($image);
        $height = ImageSY($image);
        $ratio = $newHeight / $height;
        $newWidth = $width * $ratio;
        // create image
        $output = ImageCreateTrueColor($newWidth, $newHeight);
        ImageCopyResampled($output, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        // save image
        $splitted = str_split($filename);
        $fullpath = "{$this->parent->thumbDir}/{$splitted[0]}/{$splitted[1]}/$filename";
        ImageJPEG($output, $fullpath, 100);
    }

    /**
   *
   * Remove EXIF data from given image
   * Require Imagick
   *
     * @param  str $filename Filename of image to remove exif data
     *
     */
    public function removeImageEXIFData($filename)
    {
        $img = new \Imagick(realpath($filename));
        // We want to keep the colour profiles
        $profiles = $img->getImageProfiles("icc", true);
        $img->stripImage();
        if (!empty($profiles)) {
            $img->profileImage("icc", $profiles['icc']);
        }

        return $img->writeImage($filename);
    }


    /**
     * Get all images user has saved in their history
     *
     * @param int $uid User ID to get history of
     *
     * @return arr $posts Images user has viewed
     */
    public function getHistory($uid, $page)
    {
        if (empty($page)) {
            $page = 0;
        } else {
            $page = ($page-1) * $this->itemPerPage;
        }
 
        $stmt = $this->db->prepare("SELECT post.id,
      post.uid,
      post.title,
      post.url,
      post.created,
      user.username,
      history.viewed AS viewed
      FROM post, history, user WHERE post.id = history.pid AND history.uid = :uid AND post.uid = user.id
      ORDER BY history.viewed DESC LIMIT :page,24");
        $stmt->bindValue(":uid", $uid, \PDO::PARAM_INT);
        $stmt->bindValue(":page", $page, \PDO::PARAM_INT);
        $stmt->execute();

        $posts = $stmt->fetchAll();

        return $posts;
    }

    /**
     * Get page count of users history
     *
     * @param int $uid
     *
     * @return int
     */
    public function getHistoryPageCount($uid)
    {
        if (is_null($uid)) {
            if ($this->parent->user->userLoggedin) {
                $uid = $this->parent->user->uid;
            } else {
                return false;
            }
        }
 
        $stmt = $this->db->prepare("SELECT count(id) FROM history WHERE uid = :uid");
        $stmt->bindValue(":uid", $uid, \PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->fetch();

        $totalPages = ceil($count[0] / 24);

        return $totalPages;
    }



    public function getSaved($uid, $page = null)
    {
        if (is_null($page)) {
            $page = 0;
        } else {
            $page = ($page-1) * $this->itemPerPage;
        }
    
        $stmt = $this->db->prepare("SELECT post.id,
      post.uid,
      post.title,
      post.url,
      post.created,
      user.username
      FROM post, saved, user WHERE post.id = saved.pid AND saved.uid = :uid AND post.uid = user.id
      ORDER BY saved.created DESC LIMIT :page,:ipp");
        $stmt->bindValue(":uid", $uid, \PDO::PARAM_INT);
        $stmt->bindValue(":page", $page, \PDO::PARAM_INT);
        $stmt->bindValue(":ipp", $this->itemPerPage, \PDO::PARAM_INT);
        $stmt->execute();

        $posts = $stmt->fetchAll();

        return $posts;
    }



    public function saveItem($pid, $uid)
    {
        // If already saved, we don't want a duplicate row
        if ($this->isSaved($pid, $uid)) {
            return false;
        }

        $stmt = $this->db->prepare("INSERT INTO saved VALUES(NULL, NULL, :pid, :uid)");
        $stmt->bindValue(":pid", $pid);
        $stmt->bindValue(":uid", $uid);

        $stmt->execute();
    }

    public function isSaved($pid, $uid)
    {
        $stmt = $this->db->prepare("SELECT pid FROM saved WHERE pid = :pid AND uid = :uid");
        $stmt->bindValue(":pid", $pid);
        $stmt->bindValue(":uid", $uid);

        $stmt->execute();

        return !empty($stmt->fetch());
    }

    public function deleteSavedItem($pid, $uid)
    {
        $stmt = $this->db->prepare("DELETE FROM saved WHERE pid = :pid AND uid = :uid");
        $stmt->bindValue(":pid", $pid);
        $stmt->bindValue(":uid", $uid);

        $stmt->execute();
    }

    public function addToHistory($pid, $uid)
    {
        $stmt = $this->db->prepare("SELECT id FROM history WHERE pid = :pid AND uid = :uid");
        $stmt->bindValue(":pid", $pid);
        $stmt->bindValue(":uid", $uid);

        $stmt->execute();

        // If already viewed, remove and update the viewed timestamp
        if ($stmt->fetch()) {
            $stmt = $this->db->prepare("DELETE FROM history WHERE pid = :pid AND uid = :uid");
            $stmt->bindValue(":pid", $pid);
            $stmt->bindValue(":uid", $uid);

            $stmt->execute();
        }

        $stmt = $this->db->prepare("INSERT INTO history VALUES(NULL, :pid, NULL, :uid)");
        $stmt->bindValue(":pid", $pid);
        $stmt->bindValue(":uid", $uid);

        $stmt->execute();
    }

    /**
     * Add image report to database table
     *
     * @param int $pid Image ID
     * @param int $reason Reason ID
     */
    public function reportImage($pid, $reason)
    {
        if (!$this->parent->user->userLoggedin) {
            $uid = 0;
        } else {
            $uid = $this->parent->user->uid;
        }
        $stmt = $this->db->prepare("INSERT INTO reports VALUES(NULL, :uid, :pid, :reason, NULL)");
        $stmt->bindValue(":uid", $uid);
        $stmt->bindValue(":pid", $pid);
        $stmt->bindValue(":reason", $reason);

        $stmt->execute();

        if ($this->db->lastInsertId()) {
            $this->parent->setAlert("success", "Reported image");
        }

        if (!isset($_SERVER['HTTP_REFERER'])) {
            header('Location: /account');
        } else {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        }
    }

    /**
     * Delete given report
     *
     * @param int $id Report to delete
     */
    public function deleteReport($id)
    {
        $stmt = $this->db->prepare("DELETE FROM reports WHERE id = :id");
        $stmt->bindValue("id", $id);

        $stmt->execute();

        if ($stmt->rowCount()) {
            $this->parent->setAlert("success", "Deleted report");
        } else {
            $this->parent->setAlert("warning", "Failed to delete report");
        }

        if (!isset($_SERVER['HTTP_REFERER'])) {
            header('Location: /account');
        } else {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        }
    }
}
