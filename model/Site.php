<?php
namespace YAIH\Model;

/**
 * Class: Site
 *
 * @see Model
 */
class Site extends Model
{
    /**
     * __construct
     *
     * @param obj $parent Model object
     */
    public function __construct($parent)
    {
        $this->parent	= $parent;
        $this->db	= $this->parent->db;
    }

    /**
     * isInMaintenance - check if the site is in maintenance mode
     *
     * @return bool returns true if the site is in maintenance mode
     */
    public function isInMaintenance()
    {
        $stmt = $this->db->prepare("SELECT value FROM site_settings WHERE setting='maintenance_mode'");
        $stmt->execute();

        $val = $stmt->fetch();

        return $val["value"];
    }

    /**
     * getAllReports
     *
     * @param int $page Page number
     *
     * @return arr reports
     */
    public function getAllReports($page = null)
    {
        if (is_null($page)) {
            $page = 0;
        } else {
            $page = ($page-1) * 20;
        }

        $stmt = $this->db->prepare("SELECT * FROM reports ORDER BY id ASC LIMIT :page,20");
        $stmt->bindValue(":page", $page);
        $stmt->execute();

        $reports = $stmt->fetchAll();

        // Get usernames
        foreach ($reports as $i => $report) {
            $reports[$i]["username"] = $this->parent->user->getUsername($report["uid"]);
            $image = $this->parent->image->getImage(null, $report["pid"]);
            $reports[$i]["thumb_url"] = $image["thumb_url"];
        }

        return $reports;
    }

    /**
     * getReportsPageCount
     *
     */
    public function getReportsPageCount()
    {
        $stmt = $this->db->prepare("SELECT count(*) FROM reports");

        $stmt->execute();
        $count = $stmt->fetch();

        $totalPages = ceil($count[0] / 20);

        return $totalPages;
    }

    /**
     * updateMaintenanceMode Update the maintenance setting value in the database
     *
     * @param int $status Status to set the site
     */
    public function updateMaintenanceMode($status)
    {
        $stmt = $this->db->prepare("UPDATE site_settings SET value = :status WHERE setting='maintenance_mode'");
        $stmt->bindValue(":status", $status);

        $stmt->execute();
    }
}
