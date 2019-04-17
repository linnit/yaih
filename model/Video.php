<?php

namespace YAIH\Model;

/*
$sanitised_filename = sanitise_filename($filename);
$time = get_timestamp($sanitised_filename);
make_thumbnail($sanitised_filename, $time, $thumbnail);
*/

class Video extends Model
{

    //$this->filename = "./big_buck_bunny_240p_30mb.mp4";
    //$this->thumbnail = "./test";

    public function __construct()
    {
        $this->ffprobe_bin = "/bin/ffprobe";
        $this->ffmpeg_bin = "/bin/ffmpeg";

        if (!$this->ffmpeg_exists()) {
            throw new \Exception("Problem with ffmpeg package");
            return false;
        }
    }

    /**
     * Check if ffmpeg packages are installed
     *
     * @return int status
     */
    public function ffmpeg_exists()
    {
        if (!file_exists($this->ffprobe_bin)) {
            throw new \Exception("$ffprobe_bin not found");
            return false;
        }

        if (!file_exists($this->ffmpeg_bin)) {
            throw new \Exception("$ffmpeg_bin not found");
            return false;
        }

        return true;
    }

    /**
     * Sanitise the filename to stop any malicious input
     * Returned string will only contain alphanumerical and _-./ characters
     *
     * @param str $filename The filename to clean
     *
     * @return str $sanitised_filename Cleaned filename
     */
    public function sanitise_filename($filename)
    {
        // We've generated the filename, but just to be sure
        $sanitised_filename = preg_replace("/[^a-zA-Z0-9_\-\.\/]/", "", $filename);

        if (!file_exists($sanitised_filename)) {
            throw new \Exception("$sanitised_filename not found");
            return false;
        }

        return $sanitised_filename;
    }


    /**
     * Find the center of a video
     *
     * @param str $filename Video filename to find center of
     *
     * @return int $time Timestamp of the center of given video
     */
    public function get_timestamp($filename)
    {
        exec("{$this->ffprobe_bin} -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $filename | awk '{print ($1)/2}'", $time, $return_val);

        if ($return_val != 0) {
            throw new \Exception("Error running ffprobe: $time");
            return false;
        }

        $time = $time[0];
        // Remove newline
        $time = trim($time);

        return $time;
    }

    /**
     * Make jpg thumbnail of given video
     *
     * @param str $filename Filename of video to make thumbnail of
     * @param int $time Time of the middle of the video
     * @param str $thumbnail Thumbnail filename
     */
    public function make_thumbnail($filename, $time, $thumbnail)
    {
        exec(
            "{$this->ffmpeg_bin} -ss $time -i $filename -vframes 1 -q:v 2 -f image2 $thumbnail",
            $ffmpeg_output,
            $return_val
        );

        if ($return_val != 0) {
            throw new \Exception("Error running ffmpeg: $ffmpeg_output");
            return false;
        }
    }
}
