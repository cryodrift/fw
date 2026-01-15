<?php

//declare(strict_types=1);

/**
 * Date: 05.09.14
 * Time: 18:53
 */

namespace cryodrift\fw\tool\image;

use Exception;
use cryodrift\fw\Core;


/**
 * Class Thumb
 * @package Image
 */
class Thumb
{
    /**
     * @var null
     */
    private string|null $filename = null;
    /**
     * @var null
     */
    private float|int|null $width = null;
    /**
     * @var null
     */
    private float|int|null $height = null;
    /**
     * @var null
     */
    private string|null $cachefolder = null;

    private int $ofilesize = 0;

    /**
     * @param $filename
     */
    public function __construct(\SplFileInfo $file)
    {
        $this->filename = $file->getPathname();

        $this->ofilesize = $file->getSize();
        if ($this->ofilesize < 10) {
            throw new Exception('File too small ' . $this->filename);
        }
    }

    public function getOriginalFileSize()
    {
        return $this->ofilesize;
    }

    /**
     * @param mixed $cachefolder
     */
    public function setCachefolder($cachefolder)
    {
        Core::dirCreate($cachefolder, false);
        $this->cachefolder = $cachefolder;
    }

    /**
     * @param mixed $height
     */
    public function setHeight($height)
    {
        if (is_numeric($height)) {
            $this->height = $height;
        }
    }

    /**
     * @param mixed $width
     */
    public function setWidth($width)
    {
        if (is_numeric($width)) {
            $this->width = $width;
        }
    }

    /**
     * @param bool $asdownload
     * @throws \Exception
     */
    public function send(bool $asdownload = false, string|null $outfile = null)
    {
        if (!$outfile) {
            $outfile = $this->cachefolder . '/' . $this->getThumbFilename();
        }
        if (!file_exists($outfile)) {
            Core::echo(__METHOD__, 'filename', $this->filename);
            Core::echo(__METHOD__, 'thumbname', $outfile);
            throw new Exception('Thumb not found');
        }

        ###
        // Getting headers sent by the client.
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = array();
        }


        // Checking if the client is validating his cache and if it is current.
        if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($outfile))) {
            $logdata[] = 'notmodified';

            // Client's cache IS current, so we just respond '304 Not Modified'.
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($outfile)) . ' GMT', true, 304);
        } else {
            // Image not cached or cache outdated, we respond '200 OK' and output the image.
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($outfile)) . ' GMT', true, 200);
            header('Content-Length: ' . filesize($outfile));
            header('Content-Type: image/png');

            ### contenttyp finden
//            \Core::echo('','file',$file);
            $contenttyp = \image_type_to_mime_type(\exif_imagetype($outfile));
            ###
            if ($contenttyp) {
                header('Content-type: ' . $contenttyp);
            }
            $hash = md5_file($outfile);

            ### initiate download
            header('ETag: "' . $hash . '"');

            ### sende normale files
            if ($asdownload) {
                $fdata = stat($outfile);
                header('Content-Type: application/x-download');
                header('Content-Length: ' . $fdata['size']);
                header('Content-Disposition: attachment; filename="' . basename($this->filename) . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                ini_set('zlib.output_compression', '0');
            }

            ob_end_clean();
            readfile($outfile);
            exit;
        }
    }

    /**
     * @throws \Exception
     */
    public function generate_once()
    {
        $fullname = $this->cachefolder . '/' . $this->getThumbFilename();
        Core::dirCreate($fullname);
        Core::echo(__METHOD__, 'filename', $this->filename);
        Core::echo(__METHOD__, 'thumbname', $fullname);
        $finfo = new \SplFileInfo($fullname);
        if (!file_exists($fullname) || $finfo->getSize() < 1000) {
            if (!file_exists($this->filename)) {
                throw new \Exception('File not found' . $this->filename);
            }
            try {
                $this->generateThumb($this->filename, $fullname);
            } catch (\Exception $ex) {
                if ($ex->getCode() == 100) {
                    // thumb is bigger then image
                    copy($this->filename, $fullname);
                }
            }
        }
    }

    /**
     * @return string
     */
    private function getThumbFilename(): string
    {
        $inf = new \SplFileInfo($this->filename);
        $split = str_split(md5($this->filename), 2);
        $dir = array_slice($split, 0, 2);
        return $this->width . 'x' . $this->height . '/' . implode('/', $dir) . '/' . implode('', array_slice($split, 2)) . '.' . $inf->getExtension();
    }

    /**
     * @param $filename
     * @param $outfile
     * @throws \Exception
     */
    public function generateThumb(string $filename, string $outfile = ''): string
    {
        $finfo = \getimagesize($filename);
        $width = $height = 0;
        ### set imagesize
        if ($this->width && $this->height) {
            $psize = $this->getProportions($this->width, $this->height, $finfo[0], $finfo[1]);
            $width = $psize[0];
            $height = $psize[1];
        } elseif ($this->width) {
            $psize = $this->getProportions($this->width, 0, $finfo[0], $finfo[1]);
            $width = $psize[0];
            $height = $psize[1];
        } elseif ($this->height) {
            $psize = $this->getProportions(0, $this->height, $finfo[0], $finfo[1]);
            $width = $psize[0];
            $height = $psize[1];
        }
        ### nur wenn das bild kleiner werden soll
        if (($width < $finfo[0]) || ($height < $finfo[1])) {
//            Core::echo(__METHOD__, ' w:' . $width . ' h:' . $height);
            if (!$outfile) {
                ob_start();
            }
            $this->resizeImage($filename, null, $width, $height);
            if (!$outfile) {
                return ob_get_clean();
            }
        } else {
            throw new \Exception('Thumb bigger then image', 100);
        }
        return $outfile;
    }

    /**
     * @param $pathname
     * @param $newpathname
     * @param $width
     * @param $height
     * @return mixed
     */
    protected function resizeImage(string $pathname, string|null $newpathname, int $width, int $height): string
    {
        $source = false;
        $finfo = \getimagesize($pathname);

        ###
        if ($width && $height) {
            ### blank image with new size
            $thumb = \imagecreatetruecolor($width, $height);
            //echo C_B.'imgtyp='.\exif_imagetype($pathname);
            $ityp = \exif_imagetype($pathname);

            ###
            switch ($ityp) {
                case IMAGETYPE_TIFF_II:
                case IMAGETYPE_TIFF_MM:
                case IMAGETYPE_JPEG:
                    $source = \imagecreatefromjpeg($pathname);
                    if ($source === false) {
                        return '';
                    }
                    ### fit original into blank image
                    \imagecopyresized($thumb, $source, 0, 0, 0, 0, $width, $height, $finfo[0], $finfo[1]);
                    ### write new image to cachefile
                    \imagejpeg($thumb, $newpathname, 90);

                    break;
                case IMAGETYPE_BMP:
                case IMAGETYPE_WBMP:
                    $source = \imagecreatefromwbmp($pathname);
                    if ($source === false) {
                        return '';
                    }
                    ### fit original into blank image
                    \imagecopyresized($thumb, $source, 0, 0, 0, 0, $width, $height, $finfo[0], $finfo[1]);
                    ### write new image to cachefile
                    \imagewbmp($thumb, $newpathname);
                    break;
                case IMAGETYPE_PNG:
                    $source = \ImageCreateFromPNG($pathname);
                    if ($source === false) {
                        return '';
                    }
                    \imagealphablending($thumb, false);
                    \imagecopyresampled($thumb, $source, 0, 0, 0, 0, $width, $height, $finfo[0], $finfo[1]);
                    \imagesavealpha($thumb, true);
                    \imagepng($thumb, $newpathname);
                    break;
                case IMAGETYPE_GIF:
                    $source = \imagecreatefromgif($pathname);
                    if ($source === false) {
                        return '';
                    }
                    ### fit original into blank image
                    \imagecopyresized($thumb, $source, 0, 0, 0, 0, $width, $height, $finfo[0], $finfo[1]);
                    ### write new image to cachefile
                    \imagegif($thumb, $newpathname);
                    break;
                case IMAGETYPE_XBM:
                    $source = \imagecreatefromxbm($pathname);
                    if ($source === false) {
                        return '';
                    }
                    ### fit original into blank image
                    \imagecopyresized($thumb, $source, 0, 0, 0, 0, $width, $height, $finfo[0], $finfo[1]);
                    ### write new image to cachefile
                    \imagexbm($thumb, $newpathname);
                    break;
                case IMAGETYPE_XPM:
                    $source = imagecreatefromxpm($pathname);
                    if ($source === false) {
                        return '';
                    }
                    ### fit original into blank image
                    \imagecopyresized($thumb, $source, 0, 0, 0, 0, $width, $height, $finfo[0], $finfo[1]);
                    ### write new image to cachefile
                    \imagexpm($thumb, $newpathname);
                    break;
                case IMAGETYPE_SWF:
                case IMAGETYPE_PSD:
                case IMAGETYPE_JPC:
                case IMAGETYPE_JP2:
                case IMAGETYPE_JPX:
                case IMAGETYPE_JB2:
                case IMAGETYPE_SWC:
                case IMAGETYPE_IFF:
                    Core::echo(__METHOD__, 'upload_resize.unhandled ImageTyp=', $ityp);
            }
            ###
            if ($source) {
                \imagedestroy($source);
                \imagedestroy($thumb);
            }
        }
        ### returns newpathname if resize
        return $pathname;
    }

    ### proportional smaller
    # this is the ultimate resize algorithm for me
    # it finally works and so i put it here to never forget
    /**
     * @param $width
     * @param $height
     * @param $iwidth
     * @param $iheight
     * @param int $max
     * @return array
     */
    protected function getProportions($width, $height, $iwidth, $iheight, $max = 0)
    {
        ### only smaller possible
        if ($width < $iwidth) {
            $pwidth = $width;
        } else {
            $pwidth = $iwidth;
        }
        if ($height < $iheight) {
            $pheight = $height;
        } else {
            $pheight = $iheight;
        }
        ### width
        if (empty($height) && $pwidth != 0) {
            ###
            $v = $iwidth / $pwidth;
            $pheight = round($iheight / $v);
            ### fit in the box
            if ($max && $pheight > $max) {
                $pheight = $max;
                ###
                $v = $iheight / $pheight;
                $pwidth = round($iwidth / $v);
            }
        }
        ### height
        if (empty($width)) {
            ### fit in the box

            ###
            $v = $iheight / $pheight;
            $pwidth = round($iwidth / $v);
            ### fit in the box
            if ($max && $pwidth > $max) {
                $pwidth = $max;
                ###
                $v = $iwidth / $pwidth;
                $pheight = round($iheight / $v);
            }
        }
        return array($pwidth, $pheight);
    }


}
