<?php
  /** Directory with pictures. */
  $conf['dir'] = 'pictures';
  /** Directory for caching thumbnails (must be writeable!).*/
  $conf['cache'] = 'cache';
  /** URL to default album and picture icon. May be absolute or relative. */
  $conf['defaultIcon'] = '?static=defico.svg';
  /** Name of file with definition of title image. */
  $conf['icotitlefname'] = '000.nfo';
  /** Name of file with defined usernames/passwords for locked/private albums. */
  $conf['lockfname'] = '000.lock';
  /** Width of thumbnail. */
  $conf['thumb_x'] = 240;
  /** Height of thumbnail. */
  $conf['thumb_y'] = 180;
  /** Width of middle size picture - the view size. */
  $conf['middle_x'] = 800;
  /** Quality of output cached jpeg images. */
  $conf['cache_image_quality'] = 80;
  /** Number of characters of shortened image title. */
  $conf['imgTitleLen'] = 16;
  /** Date format for image/thumbnail display. */
  $conf['date_format'] = 'Y-m-d';
  /** Title of whole gallery. */
  $conf['galTitle'] = 'SiGal gallery';
  /** String shown in bottom of each page. Designed to some words about legal use of photos. */
  $conf['legal_notice'] = 'No photos can be distributted without written permission of their author (<a href="http://gimli2.gipix.net">Gimli2</a>).';
  /** Flag to enable function of mass download. */
  $conf['enable_mass_download'] = false;
  /** Flag to show EXIF info in image details. */
  $conf['show_exif_tab'] = true;
  /** Flag to show GPS info in image details. */
  $conf['show_gps_tab'] = true;
  /*==========================================================================*/
  /** You can provide own callback function redefine mapping directory name to album name. Function takes a string as 1st argument and returns final string name. */
  $conf['func_albumname'] = '';
  /** You can provide own callback function to define your own grouping of albums. */
  $conf['func_groupname'] = '';
  /** Callback function for scanning directory for images. You can implement own filters tanks to this function. */
  $conf['func_scandir'] = '';
  /** You can provide own callback function to sorting of albums. Function takes an array as 1st argument and returns sorted array. */
  $conf['func_sortalbums'] = '';
  /** You can provide own callback function to sorting of images. Function takes an array as 1st argument and returns sorted array. */
  $conf['func_sortimages'] = '';
  /** You can provide a callback function to get album/directories for given media extensions */
  $conf['func_getalbums'] = '';
  /** Callback function to get image from video (for thumbnail or middle image) */
  $conf['func_videoimage'] = '';
  /** Callback function to play video indirectly, perhaps with convert/transcode */
  $conf['func_avfileplay'] = '';

  /** Example implemantation of getting album name/title from name of directory. */
  function myalbumname($basename) {
    $patterns = array('~(19|20)(\d{2})-(\d{1,2})-(\d{1,2})_(.*)~si',
                      '~(19|20)(\d{2})-(\d{1,2})-(\d{1,2})-(\d{1,2})_(.*)~si');
    $replacements = array('\5 (\4. \3. \1\2)',
                          '\6 (\4-\5. \3. \1\2)');
    $basename = preg_replace($patterns, $replacements , $basename);
    $elipse = (strlen($basename) > 15) ? '&hellip;':'';
    $title = substr($basename, 0, 15).$elipse;
    return $title;
  }
  /** Example implementation of . */
  function mygroupname($bn) {
    // default grouping is by chars before "-"
    $cutpos = strpos($bn, '-');
    if ($cutpos === FALSE) $cutpos = strlen($bn);
    $group = substr($bn, 0, $cutpos);
    return $group;
  }
  /** Example implementation of album grouping function to use with NO TABS - everything will be in group with empty string in name */
  function onegroup($basename) {
    return '';
  }
  /** Example implementation of getting pictures from directory. Usefull eg. when you want to skip some of them. */
  function myscandir($dir) {
    $files = glob($dir.'/*.tiff');
    return $files;
  }
  /** Example implementation of album sorting. */
  function mysortalbums($array) {
    arsort($array);
    return  $array;
  }
  /** Example implementation of images sorting. */
  function mysortimages($array) {
    asort($array);
    return  $array;
  }
  /* recursive directory iterator handle exceptions. required below.
     From: http://php.net/manual/en/class.recursivedirectoryiterator.php */
  class IgnorantRecursiveDirectoryIterator extends RecursiveDirectoryIterator {
    function getChildren() {
      try {
        return new IgnorantRecursiveDirectoryIterator($this->getPathname());
      } catch(UnexpectedValueException $e) {
        return new RecursiveArrayIterator(array());
      }
    }
  }
  /** Example implementation of getalbums. This one recursively finds image/media */
  function mygetalbums($dir, $exts) {
    $it = new RecursiveIteratorIterator(new IgnorantRecursiveDirectoryIterator($dir));
    $it = new RegexIterator($it, '/^.+\.(?:' . join('|',$exts) . ')$/i', RecursiveRegexIterator::GET_MATCH);

    $dirs = array_keys(iterator_to_array($it));
    $dirs = array_unique(array_map('dirname', $dirs));

    return $dirs;
  }

  function get_videoimage($video, $image) {
    exec("avconv -y -v quiet -itsoffset -4 -i $video -vcodec mjpeg -vframes 1 -an -f rawvideo $image");
  }

  include_once('range_download.php');

  function avfile_play($file0) {
    $file = "/dev/shm/avfile-".MD5($file0).".mp4";

    foreach (glob("/dev/shm/avfile-*") as $f) {
      if (($f !== $file) && filemtime($f) < time() - 360) {
        unlink($f);
      }
    }

    if(!file_exists($file)) {
      exec("avconv -y -i $file0 -c:v copy -c:a aac -strict experimental $file");
    }

    header("Content-Type: video/mp4");

    if (isset($_SERVER['HTTP_RANGE'])) {
      touch($file);
      rangeDownload($file);
      touch($file);
    } else {
      header("Content-Length: ".filesize($file));
      touch($file);
      readfile($file);
      touch($file);
    }
    exit;
  }
?>
