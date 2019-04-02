<?php

class Learn extends Model
{
    public function __construct($parent)
    {
        $this->parent	= $parent;
        $this->db	= $this->parent->db;

        $this->itemPerPage = 9;
    }

    public function getItem($item, $uid)
    {
        $stmt = $this->db->prepare("SELECT post.id,
			post.category_id,
			post.title,
			post.shortdescription,
			post.content,
			post.url, category.name AS category FROM post, category WHERE post.category_id = category.id AND post.url = :url");
        
        $stmt->bindParam(":url", $item);
        $stmt->execute();
        
        $post = $stmt->fetch();

        if ($this->isSaved($post["id"], $uid)) {
            $post["saved"] = '1';
        }

        return $post;
    }

    public function isSaved($pid, $uid)
    {
        $stmt = $this->db->prepare("SELECT id FROM saved WHERE pid = :pid AND uid = :uid");
        $stmt->bindValue(":pid", $pid);
        $stmt->bindValue(":uid", $uid);

        $stmt->execute();

        return !empty($stmt->fetch());
    }

    public function getItemId($item)
    {
        $stmt = $this->db->prepare("SELECT id FROM post WHERE url = :url");
        
        $stmt->bindParam(":url", $item);
        $stmt->execute();
        
        $result = $stmt->fetch();

        return $result["id"];
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

    public function deleteSavedItem($pid, $uid)
    {
        $stmt = $this->db->prepare("DELETE FROM saved WHERE pid = :pid AND uid = :uid");
        $stmt->bindValue(":pid", $pid);
        $stmt->bindValue(":uid", $uid);

        $stmt->execute();
    }

    public function json_search($string)
    {
        $stmt = $this->db->prepare("SELECT post.id AS id,
		post.title AS name,
		CONCAT('/p/', category.name, '/', post.url) AS href,
		category.name AS category
		FROM post, category WHERE post.category_id = category.id AND post.title LIKE :string
		ORDER BY created DESC");

        //$stmt->bindValue(':string', '"%'.$string.'%"');

        $stmt->execute(array(':string' => '%'.$string.'%'));

        $results = $stmt->fetchAll();

        return $results;
    }

    public function getSearchPageCount($string, $category = null)
    {
        if (is_null($category)) {
            $stmt = $this->db->prepare("SELECT count(*) FROM post WHERE post.title LIKE ?");
            $params = array("%$string%");
        } else {
            $categoryID = $this->getCategory($category);
            $stmt = $this->db->prepare("SELECT count(*) FROM post WHERE category_id = ? AND post.title LIKE ?");
            $params = array("%$string%", $categoryID);
        }

        $stmt->execute($params);
        $count = $stmt->fetch();

        $totalPages = ceil($count[0] / $this->itemPerPage);

        return $totalPages;
    }



    public function search($uid, $page, $string, $category = null)
    {
        if (is_null($page)) {
            $page = 0;
        } else {
            $page = ($page-1) * $this->itemPerPage;
        }
    
        if (is_null($category)) {
            $stmt = $this->db->prepare("SELECT post.id,
				post.title,
				post.shortdescription,
				post.content,
				post.url,
				category.name AS category
				FROM post, category WHERE post.category_id = category.id AND post.title LIKE ?
				ORDER BY post.created DESC LIMIT ?,?");
            // [TODO]

            $params = array("%$string%", $page, $this->itemPerPage);
        } else {
            $categoryID = $this->getCategory($category);
            $stmt = $this->db->prepare("SELECT post.id,
				post.title,
				post.shortdescription,
				post.content,
				post.url,
				category.name AS category FROM post, category WHERE post.category_id = category.id AND post.title LIKE ?
				AND post.category_id = ?
				ORDER BY post.created DESC LIMIT ?,?");
            $params = array("%$string%", $categoryID, $page, $this->itemPerPage);
        }

        $stmt->execute($params);

        $posts = $stmt->fetchAll();

        // Find saved posts
        if (!is_null($uid) && !empty($posts)) {
            $pids = array();
            foreach ($posts as $post) {
                array_push($pids, $post["id"]);
            }
            $inPids = implode(',', array_fill(0, count($pids), '?'));
            $stmt = $this->db->prepare("SELECT pid FROM saved WHERE uid = ? AND pid IN (" . $inPids . ")");

            $i = 0;
            $stmt->bindValue(($i+=1), $uid);
            foreach ($pids as $pid) {
                $stmt->bindValue(($i+=1), $pid);
            }

            $stmt->execute();

            $saved = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Add 'saved' flag to each saved post
            foreach ($posts as $i => $post) {
                if (in_array($post["id"], $saved)) {
                    $posts[$i]["saved"] = "true";
                }
            }
        }

        return $posts;
    }

    public function getSavedPageCount($uid, $category = null)
    {
        if (is_null($category)) {
            $stmt = $this->db->prepare("SELECT count(*) FROM post,saved WHERE post.id = saved.pid AND saved.uid = :uid");
            $stmt->bindValue(":uid", $uid, PDO::PARAM_INT);
        } else {
            $categoryID = $this->getCategory($category);
            $stmt = $this->db->prepare("SELECT count(*) FROM post,saved WHERE category_id = :category AND post.id = saved.pid AND saved.uid = :uid");
            $stmt->bindValue(":category", $categoryID, PDO::PARAM_INT);
            $stmt->bindValue(":uid", $uid, PDO::PARAM_INT);
        }

        $stmt->execute();
        $count = $stmt->fetch();

        $totalPages = ceil($count[0] / $this->itemPerPage);

        return $totalPages;
    }

    public function getPageCount($category = null)
    {
        if (is_null($category)) {
            $stmt = $this->db->prepare("SELECT count(*) FROM post");
        } else {
            $categoryID = $this->getCategory($category);
            $stmt = $this->db->prepare("SELECT count(*) FROM post WHERE category_id = :category");
            $stmt->bindValue(":category", $categoryID, PDO::PARAM_INT);
        }

        $stmt->execute();
        $count = $stmt->fetch();

        $totalPages = ceil($count[0] / $this->itemPerPage);

        return $totalPages;
    }

    public function getRecent($uid = null, $page = null, $category = null)
    {
        if (is_null($page)) {
            $page = 0;
        } else {
            $page = ($page-1) * $this->itemPerPage;
        }
    
        if (is_null($category)) {
            $stmt = $this->db->prepare("SELECT post.id,
				post.title,
				post.shortdescription,
				post.content,
				post.url,
				category.name AS category
				FROM post, category WHERE post.category_id = category.id
				ORDER BY post.created DESC LIMIT :page,:ipp");
        } else {
            $categoryID = $this->getCategory($category);
            $stmt = $this->db->prepare("SELECT post.id,
				post.title,
				post.shortdescription,
				post.content,
				post.url,
				category.name AS category FROM post, category WHERE post.category_id = category.id
				AND post.category_id = :category
				ORDER BY post.created DESC LIMIT :page,:ipp");

            $stmt->bindValue(":category", $categoryID, PDO::PARAM_INT);
        }

        $stmt->bindValue(":page", $page, PDO::PARAM_INT);
        $stmt->bindValue(":ipp", $this->itemPerPage, PDO::PARAM_INT);
        $stmt->execute();

        $posts = $stmt->fetchAll();

        // Find saved posts
        if (!is_null($uid) && !empty($posts)) {
            $pids = array();
            foreach ($posts as $post) {
                array_push($pids, $post["id"]);
            }
            $inPids = implode(',', array_fill(0, count($pids), '?'));
            $stmt = $this->db->prepare("SELECT pid FROM saved WHERE uid = ? AND pid IN (" . $inPids . ")");

            $i = 0;
            $stmt->bindValue(($i+=1), $uid);
            foreach ($pids as $pid) {
                $stmt->bindValue(($i+=1), $pid);
            }

            $stmt->execute();

            $saved = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Add 'saved' flag to each saved post
            foreach ($posts as $i => $post) {
                if (in_array($post["id"], $saved)) {
                    $posts[$i]["saved"] = "true";
                }
            }
        }

        return $posts;
    }

    public function getSaved($uid, $page = null, $category = null)
    {
        if (is_null($page)) {
            $page = 0;
        } else {
            $page = ($page-1) * $this->itemPerPage;
        }
    
        if (is_null($category)) {
            $stmt = $this->db->prepare("SELECT post.id,
				post.title,
				post.shortdescription,
				post.content,
				post.url,
				category.name AS category
				FROM post, category, saved WHERE post.category_id = category.id AND post.id = saved.pid AND saved.uid = :uid
				ORDER BY post.created DESC LIMIT :page,:ipp");
        } else {
            $categoryID = $this->getCategory($category);
            $stmt = $this->db->prepare("SELECT post.id,
				post.title,
				post.shortdescription,
				post.content,
				post.url,
				category.name AS category
				FROM post, category, saved WHERE post.category_id = category.id AND post.id = saved.pid AND saved.uid = :uid
				AND post.category_id = :category
				ORDER BY post.created DESC LIMIT :page,:ipp");

            $stmt->bindValue(":category", $categoryID, PDO::PARAM_INT);
        }

        $stmt->bindValue(":uid", $uid, PDO::PARAM_INT);
        $stmt->bindValue(":page", $page, PDO::PARAM_INT);
        $stmt->bindValue(":ipp", $this->itemPerPage, PDO::PARAM_INT);
        $stmt->execute();

        $posts = $stmt->fetchAll();

        return $posts;
    }

    public function getHistory($uid, $category = null)
    {
        $page = 0;
    
        if (is_null($category)) {
            $stmt = $this->db->prepare("SELECT post.id,
				post.title,
				post.shortdescription,
				post.content,
				post.url,
				category.name AS category,
				history.viewed AS viewed
				FROM post, category, history WHERE post.category_id = category.id AND post.id = history.pid AND history.uid = :uid
				ORDER BY history.viewed DESC LIMIT :page,10");

            $stmt->bindValue(":uid", $uid, PDO::PARAM_INT);
            $stmt->bindValue(":page", $page, PDO::PARAM_INT);
        } else {
            $categoryID = $this->getCategory($category);
            $stmt = $this->db->prepare("SELECT post.id,
				post.title,
				post.shortdescription,
				post.content,
				post.url,
				category.name AS category,
				history.viewed AS viewed
				FROM post, category, history WHERE post.category_id = category.id AND post.id = history.pid AND history.uid = :uid
				AND post.category_id = :category
				ORDER BY history.viewed DESC LIMIT :page,10");

            $stmt->bindValue(":uid", $uid, PDO::PARAM_INT);
            $stmt->bindValue(":page", $page, PDO::PARAM_INT);
            $stmt->bindValue(":category", $categoryID, PDO::PARAM_INT);
        }

        $stmt->execute();

        $posts = $stmt->fetchAll();

        return $posts;
    }


    /**
     * Check if given product exists
     *
     * @param  int $id Products ID
     *
     * @return bool     Status of existence
     */
    public function productIdExists($id)
    {
        $stmt = $this->db->prepare("SELECT id FROM product WHERE id = :id");

        $stmt->bindParam(":id", $id);
        $stmt->execute();

        return count($stmt->fetchAll()) == 1;
    }

    /**
     * Add a new product
     *
     * @param str $title       Title of the product
     * @param str $description Desc. of the product
     * @param str $price       Price of the, you guessed it, product
     * @param str $stripePlan  Stripe plan id of the product
     *
     * @return int ID of newly inserted product
     */
    public function addNewProduct($title, $description, $price, $stripePlan = null)
    {
        $stmt = $this->db->prepare("INSERT INTO products VALUES(NULL, :title, :description, :price, :stripe_plan)");
        $stmt->bindValue(":title", $title);
        $stmt->bindValue(":description", $description);
        $stmt->bindValue(":price", $price);
        $stmt->bindValue(":stripe_plan", $stripePlan);

        $stmt->execute();

        return $this->db->lastInsertId();
    }

    /**
     * Delete rows from `product` and `product_features` that have the given ID
     *
     * @param  int $id ID of product to delete
     *
     */
    public function deleteProduct($id)
    {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
        $stmt->bindValue(":id", $id);
        $stmt->execute();

        $stmt = $this->db->prepare("DELETE FROM product_features WHERE pid = :id");
        $stmt->bindValue(":id", $id);
        $stmt->execute();
    }

    public function getCategory($category)
    {
        $stmt = $this->db->prepare("SELECT id FROM category WHERE name = :category");
        $stmt->bindValue(":category", $category);

        $stmt->execute();

        $result = $stmt->fetch();

        return $result["id"];
    }

    public function getAllCategories()
    {
        $stmt = $this->db->prepare("SELECT id,name FROM category");
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
