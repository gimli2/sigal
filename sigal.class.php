<?php
/**
 * @mainpage
 *  
 * @author    Martin Šlapák [aka: Gimli2]
 * @brief     Simple gallery script provides single-file web gallery.
 * @date      2012-2015
 * @copyright http://www.xfree86.org/3.3.6/COPYRIGHT2.html#5 Modified BSD License
 * @details   SiGal project page: http://gimli2.gipix.net/sigal/
 * @version   1.3
 *   
 */

/**
 * @brief      Simple gallery script provides single-file web gallery.
 */ 
class Sigal {
  public $version = '1.3';

  /** Directory with pictures. */
  public $dir = './pictures/';
  /** Directory for caching thumbnails (must be writeable!).*/
  public $cache = './cache/';
  /** URL to default album and picture icon. May be absolute or relative. */
  public $defaultIcon = '?static=defico';
  /** Name of file with definition of title image. */
  public $icotitlefname = '000.nfo';
  /** Name of file with defined usernames/passwords for locked/private albums. */
  public $lockfname = '000.lock';
  /** Width of thumbnail. Minimal 120px. */
  public $thumb_x = 160;
  /** Height of thumbnail. Minimal 120px. */
  public $thumb_y = 120;
  /** Width of middle size picture - the view size. */
  public $middle_x = 800;
  /** Number of characters of shortened image title. */
  public $imgTitleLen = 16;
  /** Title of whole gallery. */
  public $galTitle = 'SiGal gallery';
  /** String shown in bottom of each page. Designed to some words about legal use of photos. */
  public $legal_notice = 'No photos can be distributted without written permission of their author (<a href="http://gimli2.gipix.net">Gimli2</a>).';
  /** HTML head of each page of gallery. You can use string "{title}" which will be replaced by title of gallery defined above. */
  public $html_head = '<!DOCTYPE html><head><title>{title}</title></head><body>';
  /** HTML tail of each page of galllery. */
  public $html_tail = '</body></html>';
  /*========================================================================*/
  /** Array of file extensions for scanning in directiories. */
  public $exts = array('jpg','jpeg','png','gif','bmp','tif','tiff','svg','swf','flv','mp4', 'mp3');
  /** Array of file extensions for which we are able to generate thumbnail. */
  public $extsIcon = array('jpg','jpeg','png', 'gif', 'bmp');
  /** Array of file extensions with EXIF informations. */
  public $extsExif = array('jpg','jpeg','tif','tiff');
  /** Array of file extensions for videofiles. */
  public $extsVideo = array('swf','flv','mp4');
  /** Array of file extensions for audio files. */
  public $extsAudio = array('mp3');
  /** Default mime type. It is used when automated recognition fails. */
  public $defaultMime = 'text/plain';
  /** Mime types for files. The key represents extension, value is mime type. */
  public $avMime = array(
    'mp3' => 'audio/mpeg',
    'mp4' => 'video/mp4',
    'swf' => 'application/x-shockwave-flash',
    'flv' => 'video/x-flv'
  );
  /** Callback function for sorting images in some album. */
  public $func_sortimages = NULL;
  /** Callback function for sorting albums in gallery. */
  public $func_sortalbums = NULL;
  /** Callback function for scanning directory for images. You can implement own filters tanks to this function. */
  public $func_scandir = NULL;
  /** Callback function for mapping directory name to album name. */
  public $func_albumname = NULL;
  /** Callback function for getting album/directory group name */
  public $func_groupname = NULL;

  /** Flag for browsing in locked albums. */
  private $islocked = false;
  /** Array of usernames which have access to given album. */
  private $validusers = array();
  /*========================================================================*/
  /*========================================================================*/
  /*========================================================================*/
  /**
   * @brief Actually only redefines $this->html_head and $this->html_tail.
   * @returns An instance of SiGal class.
   */
  function __construct() {
    // config of HTML head and tail
    include_once 'html_head.php';
    include_once 'html_tail.php';
    // check whether ownstyle.css exists, if yes - use it
    $ownstyle_replacement = '';
    if (file_exists('./ownstyle.css')) {
      $ownstyle_replacement =  '<link rel="stylesheet" href="./ownstyle.css" type="text/css" />'."\n";
      $this->html_head = str_replace('<!--OWNCSS-->', $ownstyle_replacement, $this->html_head);
    }
    // check whether ga.js exists (google analytics), if yes - use it
    if (file_exists('./ga.js')) {
      $gajs_replacement =  '<script type="text/javascript" src="./ga.js"></script>'."\n";
      $this->html_head = str_replace('<!--GAJS-->', $gajs_replacement, $this->html_head);
    }
    // replace copyright and license
    $this->html_tail = str_replace('<!--LEGALNOTICE-->', $this->legal_notice, $this->html_tail);
  }
  /*========================================================================*/
  /**
   * @brief Adds string $user.':'.$pass to array $_SESSION['givenaccess']. The values are obtained from $_POST['fuser'] and $_POST['fpass'].
   */
  public function addAccess() {
    $user = trim($_POST['fuser']);
    $pass = trim($_POST['fpass']);
    $_SESSION['givenaccess'][] = trim($user.':'.$pass);
    $_SESSION['givenaccess'] = array_unique($_SESSION['givenaccess']);
  }
  /*========================================================================*/
  /**
   * @brief Shows complete gallery - the albums selection.
   */
  public function showGallery() {
    ob_start();
    ob_implicit_flush(true);
    echo str_replace('{title}', $this->galTitle, $this->html_head);
    echo '<div class="header">';
    echo '<h1>'.$this->galTitle.'</h1>';
    echo '</div>';
    $albs = $this->getAlbums();

    // prepare tabs
    $albs_by_group = array();
    // make array of albums by year of access time
    foreach($albs as $a) {
      $bn = basename($a);
      if (isset($this->func_groupname) && $this->func_groupname !== NULL && function_exists($this->func_groupname)) {
        $group = call_user_func($this->func_groupname, $bn);
      } else {
        // default grouping is by chars before "-" or "_"
        $cutpos = strpos($bn, '-');
        if ($cutpos === FALSE) $cutpos = strpos($bn, '_');
        if ($cutpos === FALSE) $cutpos = strlen($bn);
        $group = substr($bn, 0,$cutpos);
      }
      $albs_by_group[$group][] = $a;
    }

    $tabs = 100; // counter for tabs IDs
    $groups = array_keys($albs_by_group);
    // if we have only one group with empty string in name, we will NOT display it as tab
    if(count($albs_by_group) > 1 || ( count($albs_by_group) == 1 && strlen($groups[0]) > 0) ) {
      echo '<ul class="tabs">';
      foreach ($groups as $g) {
        echo '<li><a href="#tab-'.$tabs.'">'.$g.'</a></li>';
        $tabs++;
      }
      echo '</ul>';
    }
    
    $tabs = 100;
    foreach ($albs_by_group as $group => $albs) {
      echo '<div id="tab-'.$tabs.'" class="tab_content">';
      echo '<br class="clall" />';
      echo '<div class="tab_inner_content">';
      echo '<h2 class="subheader">'.$group.'</h2>';

      // albums in given year
      foreach ($albs as $key=>$a) {
        $titlefoto = $this->getAlbumTitleFile($a);
        $thumb = $this->getThumbName($titlefoto);
        $bn = basename($a);
        $content = glob($a.'*');
        $cnt = count($content);
        $date = filemtime($a);

        echo '<div class="album-thumb">';
        // is locked?
        if (array_search($a.$this->lockfname, $content)!==FALSE) {
          echo '<img src="?static=lock" height="32" alt="locked" title="access restricted" class="lock" />';
        }
        echo '<a href="?salb='.urlencode($bn).'" title="'.$bn.'">';
        if ($thumb===$this->defaultIcon || file_exists($thumb)) {
          echo '<img src="'.$thumb.'" height="'.$this->thumb_y.'" alt="'.$bn.'" class="it" />';
        } else {
          echo '<img src="?mkthumb='.urlencode($titlefoto).'"  height="'.$this->thumb_y.'" alt="'.$bn.'" class="it" />';
        }
        echo '</a>';
        echo $this->getAlbumTitle($a);
        echo '<div class="desc">'.date("d. m. Y", $date).' ('.$cnt.' files)</div>';
        echo '</div>'."\n";
        ob_flush();
      }
      
      echo '<br class="clall" />';
      echo '</div>'."\n";
      echo '</div>'."\n";
      $tabs++;
    }
    
    echo $this->html_tail;
  }
  /*========================================================================*/
  /**
   * @brief Shows given album.
   * @param string $alb Full path to album directory with terminating slash.
   */
  public function showAlbum($alb) {
    ob_start();
    ob_implicit_flush(true);
    $alb = $this->sanitizePath(urldecode($alb));
    $aname = basename($alb);
    echo str_replace('{title}', $aname, $this->html_head);
    echo '<div class="header">';
    echo '<h1>'.$this->galTitle.': '.$aname.'</h1>';
    echo '</div>';
    echo '<div class="header">Navigation: ';
    echo '<a href="?#" onclick="history.back();">Back to album selection</a>';
    echo ' | Functions: ';
    echo '<a href="?#" onClick="javascript:dowloadselected(); return false;">Download selected images (<span id="multipledownloadlinkcnt">0</span>)</a>';
    echo ', <a href="?#" onClick="javascript:toggleAllCheckboxes(); return false;">toggle all</a>';
    echo '</div>';

    // this automaticly check if album is locked an load usernames&passwords
    $fotos = $this->getImages($alb);

    // is locked? and not set username&pass
    $this->readLock($alb);
    if ($this->islocked && !$this->isAccessible()) {
      $this->showPassForm();
      echo $this->html_tail;
      die();
    }
    echo '<div class="fotos">';
    foreach($fotos as $f) {
      $bn = basename($f);
      $middle = $this->getMiddleName($f);
      echo '<div class="foto-thumb">';
      if ($middle===$this->defaultIcon || file_exists($middle)) {
        if ($middle===$this->defaultIcon) {
          echo '<a href="'.$f.'" title="'.$bn.'">';
        } else {
          echo '<a href="'.$middle.'" title="'.$bn.'">';
        }
      } else {
        echo '<a href="?mkmid='.urlencode($f).'" title="'.$bn.'">';
      }
      $thumb = $this->getThumbName($f);
      if ($thumb === $this->defaultIcon || file_exists($thumb)) {
        echo '<img src="'.$thumb.'" height="'.$this->thumb_y.'" alt="'.$bn.'" class="it" />';
      } else {
        echo '<img src="?static=1px" data-lazy="?mkthumb='.urlencode($f).'" height="'.$this->thumb_y.'" alt="'.$bn.'" class="it" />';
      }
      echo '</a>';
      echo $this->getImageTitle($f);
      echo '<div class="desc">';
      echo '<div class="infbutton"><a href="?foto='.urlencode($f).'#tab-base"><img src="?static=info" alt="Detailed info" title="Detailed info (EXIF, GPS)" /></a></div>';
      echo '<div class="infbutton"><a href="'.$f.'#t"><img src="?static=download" alt="Download" title="Download full size" /></a></div>';
      echo '<div class="infbutton"><input type="checkbox" name="i[]" value="'.$f.'" onClick="addToDownload(\''.$f.'\')" title="+/- to multiple download" /></div>';
      echo '</div>';
      echo '</div>'."\n";
      ob_flush();
    }
    echo '</div>';
    echo '<script src="?static=lazy.min"></script><script>lazy.init({delay:200});</script>';
    echo '<div class="footer">Navigation: <a href="?">Back to album selection</a></div>';
    echo $this->html_tail;
  }
  /*========================================================================*/
  /**
   * @brief Shows detail of given photo.
   * @param string $f Path to original image.
   */
  public function showImage($f) {
    $f=$this->sanitizePath(urldecode($f));
    $bn=basename($f);

    // zamykaci soubor
    $lf = substr($f, 0, -1*mb_strlen($bn)).$this->lockfname;
    if (file_exists($lf)) {
      $this->islocked = true;
      $this->validusers = file($lf);
      foreach ($this->validusers as $key=>$value) {
        $this->validusers[$key] = trim($value);
      }
    } else {
      $this->islocked = false;
    }

    // hlavicka
    $ext = strtolower($this->getExt($f));
    echo str_replace('{title}', $bn, $this->html_head);
    
    // je to zamcene?
    if ($this->islocked && !$this->isAccessible()) {
      $this->showPassForm();
      echo '<div class="footer">Navigation: <a href="?">Back to album selection</a></div>';
      echo $this->html_tail;
      die();
    }
    
    echo '<div class="foto">';
    if (in_array($ext, $this->extsVideo)) {
      echo '<video height="480" width="854" src="'.$f.'" controls="controls">';
      echo '<source src="'.$f.'" type="'.$this->avMime[$ext].'" />';
      echo 'Your browser does not support the video tag.';
      echo '</video>';
    } elseif (in_array($ext, $this->extsAudio)) {
      echo '<audio src="'.$f.'" controls="controls">';
      echo '<source src="'.$f.'" type="'.$this->avMime[$ext].'" />';
      echo 'Your browser does not support the audio tag.';
      echo '</audio>';
    } else {
      $middle = $this->getMiddleName($f);
      if (file_exists($middle)) {
        echo '<img src="'.$middle.'" alt="'.$bn.'" />';
      } else {
        echo '<img src="?mkmid='.urlencode($f).'" alt="'.$bn.'" />';
      }
    }
    echo '<div class="desc">';
    echo '<div>Navigation: <a href="?alb='.urlencode(substr($f,0,-1*strlen(basename($f)))).'">Back to album thumbnails</a></div><br />';
    
    echo '<ul class="tabs">';
    echo '  <li><a href="#tab-base">Base info</a></li>';
    echo '  <li><a href="#tab-exif">EXIF details</a></li>';
    echo '  <li><a href="#tab-gps">GPS</a></li>';
    echo '</ul>';
    
    echo '<div id="tab-base" class="tab_content">';
    echo '<div class="tab_inner_content">';
    echo '<h1>'.$bn.'</h1>';
    echo '<a href="'.$f.'">download full size</a> ('.round(filesize($f)/(1024*1024),2).' MB)';
    echo '</div>';
    echo '</div>';

    // EXIF
    if (in_array($ext, $this->extsExif)) {
      $exif=exif_read_data($f);
      echo '<div id="tab-exif" class="tab_content">';
      echo '<div class="tab_inner_content">';
      echo '<ul>';
      echo '<li>date: <strong>'.$exif['DateTimeOriginal'].'</strong></li>';
      echo '<li>orig. filesize: <strong>'.round($exif['FileSize']/(1024*1024),2).' MB</strong></li>';
      echo '<li>orig. size: <strong>'.$exif['COMPUTED']['Width'].'*'.$exif['COMPUTED']['Height'].' px</strong></li>';
      echo '<li>exposition: '.$exif['ExposureTime'].' s</li>';
      echo '<li>ISO: '.$exif['ISOSpeedRatings'].'</li>';
      echo '<li>Anum: '.$exif['COMPUTED']['ApertureFNumber'].'</li>';
      echo '<li>FocalLength: '.$exif['FocalLength'].' mm</li>';
      echo '<li>Orientation: '.$exif['Orientation'].'</li>';
      echo '<li>Camera model: '.$exif['Model'].'</li>';
      echo '</ul>';
      echo '</div>';
      echo '</div>';

      // GPS
      echo '<div id="tab-gps" class="tab_content">';
      echo '<div class="tab_inner_content">';
      if ($this->hasGPSData($exif)) {
        $gps = $this->getGPSLatLon($exif);
        $hgps = $this->getHumanGPS($gps[0], $gps[1]);
        echo '<h2>'.$hgps['lat'].', '.$hgps['lon'].'</h2>';
        echo '<a href="http://mapy.cz/#t=s&q='.urlencode($gps[0].', '.$gps[1]).'">mapy.cz</a><br />';
        echo '<a href="http://maps.google.cz/maps?q='.urlencode($gps[0].', '.$gps[1]).'">maps.google.com</a><br /><br />';
        echo '<div class="gps-container">';
        echo '<div>';
        echo '<img src="http://pafciu17.dev.openstreetmap.org/?module=map&center='.$gps[1].','.$gps[0].',&zoom=13&type=mapnik&width=240&height=240&points='.$gps[1].','.$gps[0].',pointImagePattern:red" /><br class="clall">';
        echo '</div>';
        echo '<div>';
        echo '<img src="http://ojw.dev.openstreetmap.org/StaticMap/?lat='.$gps[0].'&lon='.$gps[1].'&z=10&w=240&h=240&layer=hiking&mode=Add+icon&mlat0='.$gps[0].'&mlon0='.$gps[1].'&show=1" /><br class="clall">';
        echo '</div>';
        echo '<div>';
        echo '<img src="http://ojw.dev.openstreetmap.org/StaticMap/?lat='.$gps[0].'&lon='.$gps[1].'&z=13&w=240&h=240&layer=hiking&mode=Add+icon&mlat0='.$gps[0].'&mlon0='.$gps[1].'&show=1" /><br class="clall">';
        echo '</div>';  
        echo '</div>';
      }  else {
        echo 'No GPS data.';
      }
      echo '</div>';
      echo '</div>';
      
    } else {
      // no exif
      echo '<div id="tab-exif" class="tab_content">';
      echo 'No EXIF data.';
      echo '</div>';
      echo '<div id="tab-gps" class="tab_content">';
      echo 'No GPS data.';
      echo '</div>';
    }
    echo '</div>';
    echo '</div>';
    echo $this->html_tail;
  }
  /*========================================================================*/
  /**
   * @brief Shows form for grant access.
   */
  public function showPassForm() {
    require_once 'authform.html';
  }
  /*========================================================================*/
  /**
   * @brief Shows credit page.
   */
  public function showCreditPage() {
    echo str_replace('{title}', $this->galTitle, $this->html_head);
    require_once 'credits.html';
    echo $this->html_tail;
  }
  /*========================================================================*/
  private function sortItems($array, $callback_id) {
    $callback = $this->$callback_id;
    if (isset($callback) && $callback !== NULL && function_exists($callback)) {
      return call_user_func($callback, $array);
    }
    return $array;
  }
  /*========================================================================*/
  /**
   * @brief Get sorted (by name reversed eg. 9->0, Z->A) array of all albums.
   * @returns An array of all albums in defined dir ($this->dir).
   */
  public function getAlbums() {
    if (isset($this->func_getalbums) && $this->func_getalbums !== NULL && function_exists($this->func_getalbums)) {
      $files = call_user_func($this->func_getalbums, $this->dir, $this->exts);
      return $this->sortItems($files, 'func_sortalbums');
    }

    $files = glob($this->dir.'*');
    foreach($files as $k => $v) {
      if (is_dir($v)) {
        $files[$k] = $v.'/';
      } else {
        unset($files[$k]);
      }
    }
    $files = $this->sortItems($files, 'func_sortalbums');
    return $files;
  }
  /*========================================================================*/
  /**
   * @brief Reads possible lock file and parse its contents into current object.
   */
   // TODO: respektovat separatory adresaru napric OS
  public function readLock($dir) {
    $abslockfname = $dir.'/'.$this->lockfname;
    $this->islocked = false;
    if (file_exists($abslockfname)) {
      $this->islocked = true;
      // read user names and passwords
      $this->validusers = file($abslockfname);
      // remove linebreaks
      foreach ($this->validusers as $key=>$val) {
        $this->validusers[$key] = trim($val);
      }
    }
  }
  /*========================================================================*/
  /**
   * @brief Returns all images from given directory sorted by name and read possible locks of album.
   * @param string $dir Source directory for scan with terminating slash.
   * @returns An array of all images.
   */
  public function getImages($dir) {
    
    $files = array();

    // if we have scanning callback defined, lets use it...
    if (isset($this->func_scandir) && $this->func_scandir !== NULL && function_exists($this->func_scandir)) {
      $files = call_user_func($this->func_scandir, $array);
    } else {
      $r = glob($dir.'*');
      foreach($r as $file) {
        // filter to only permited extensions
        $ext = strtolower($this->getExt($file));
        if (in_array($ext, $this->exts)) $files[] = $file;
      }
    }
    
    $files = $this->sortItems($files, 'func_sortimages');
    return $files;
  }
  /*========================================================================*/
  /**
   * @brief Gets path to title image of given album. If there are no iconificable image, the $this->defaultIcon is used.
   * @param string $album Path to album directory with terminating slash
   * @returns URL of title image.
   */
  public function getAlbumTitleFile($album) {
    // if is title photo defined in specified file, we use it
    if (file_exists($album.$this->icotitlefname)) return $album.trim(file_get_contents($album.$this->icotitlefname));
    // else we, use the first iconificable image
    $files = glob($album.'*');
    foreach($files as $file) {
      $ext = strtolower($this->getExt($file));
      if (in_array($ext, $this->extsIcon)) return $file;
    }
    // fallback - no suitable icon
    return $this->defaultIcon;
  }
  /*========================================================================*/
  public function downloadZippedImages() {
    $url = parse_url($_POST['imgalbum'],PHP_URL_QUERY); // only part after ?
    $archive = urlencode(substr($url, 1 + strpos($url, '=')));
    $imgs = array();
    foreach ($_POST as $key=>$val) {
      if (preg_match('~img[0-9]{1,}~', $key)) {
        $imgs[] = $val;
      }
    }

    $zip = new ZipStream($archive.'.zip');
    $zip->addDirectory($archive);

    foreach ($imgs as $file) {
      if (is_file($file)) {
        $zip->addLargeFile($file, $archive."/".basename($file), filectime($file));
      }
    }

    // add readme
    $data  = "Downloaded ".date("Y-m-d H:i")." from ".$_POST['imgalbum']."\r\n--\r\n";
    $data .= "Simple gallery script SiGal: http://gimli2.gipix.net/sigal/ \r\n";
    $zip->addFile($data, $archive."/readme.txt");

    return $zip->finalize();
  }
  /*========================================================================*/
  /**
   * @brief Creates thumbnail of given image.
   * @param string $file The original filename.
   */
  public function makeThumbImage($file) {
    $f = $this->sanitizePath(urldecode($file));
    $ext = strtolower($this->getExt($f));
    if (file_exists($f) && in_array($ext, $this->extsIcon)) {
      $thumb = $this->resizeImage($f, $this->thumb_x);
      header('Location: '.$thumb);
      die();
    }
    header('Status: 404 Not Found');
    die();
  }
  /*========================================================================*/
  /**
   * @brief Creates middle size image of given original image.
   * @param string $file The original filename.
   */
  public function makeMiddleImage($file) {
    $f = $this->sanitizePath(urldecode($file));
    $ext = strtolower($this->getExt($f));
    if (file_exists($f) && in_array($ext, $this->extsIcon)) {
      $middle = $this->resizeImage($f, $this->middle_x);
      header('Location: '.$middle);
      die();
    }
    header('Status: 404 Not Found');
    die();
  }
  /*========================================================================*/
  /**
   * @brief Test whether given album is accessible. In $_SESSION['givenaccess'] must we at least one valid user from array $this->validusers.
   * @returns TRUE when album is accessible, FALSE otherwise.
   */
  private function isAccessible() {
    if (!isset($_SESSION['givenaccess'])) $_SESSION['givenaccess'] = array();
    // $_SESSION['givenaccess'] je string: "user:pass"
    foreach ($_SESSION['givenaccess'] as $user) {
      // polozky v $this->validusers jsou zase string: "user:pass"
      foreach ($this->validusers as $valid) {
         if ($user == $valid) return true;
      }
    }
    return false;
  }
  /*========================================================================*/
  /**
   * @brief Sanitize path of file or album. No jumps to parent dirs, no wildcards for glob(), path must start in pictures directory.
   * @param string $p Path for sanitization.
   * @returns Sanitized path.
   */
  private function sanitizePath($p) {
    $p = trim($p);  // no whitespaces
    if ($this->dir != mb_substr($p, 0, mb_strlen($this->dir))) return ''; // it must start in $dir
    $p = str_replace('..','',$p);   // no jumps to parent dirs
    $p = str_replace('*','',$p);     // no wildcards for glob
    $p = str_replace('://','',$p);     // no protocols
    return $p;
  }
  /*========================================================================*/
  /**
   * @brief Gets the last extension of file.
   * @param string $file File to get extension.
   * @returns The last extension from given file.
   */
  private function getExt($file) {
    return mb_substr($file,mb_strrpos($file, '.')+1);
  }
  /*========================================================================*/
  // TODO: use PECL extension if available
  /**
   * @brief Try to get mime type of file.
   * @param string $file The original filename.
   * @returns Mime type of given file.
   */
  private function getMimeType($file) {
    //$finfo = finfo_open(FILEINFO_MIME);
    //$mimetype = finfo_file($finfo, $f);
    //finfo_close($finfo);
    $mime = $this->defaultMime;
    if (function_exists('mime_content_type')) $mime = mime_content_type($file);
    return $mime;
  }
  /*========================================================================*/
  /**
   * @brief Creates a filename for middle size image.
   * @param string $file The original filename.
   */
  private function getMiddleName($file) {
    // is given file iconificable?
    $ext = strtolower($this->getExt($file));
    if (in_array($ext, $this->extsIcon)) {
      $md5 = MD5($file.$this->middle_x);
      $targetDir = $this->cache.substr($md5,0,1).'/';
      $targetImagePath = $targetDir.$md5.".jpg";
      return $targetImagePath;
    }
    // fallback - no suitable icon
    return $this->defaultIcon;
  }
  /*========================================================================*/
  /**
   * @brief Creates a filename for thumbnail of given image.
   * @param string $file The original filename.
   */
  private function getThumbName($file) {
    // default icon has itself as thumbnail
    if ($file === $this->defaultIcon) return $file;
    // is given file iconificable?
    $ext = strtolower($this->getExt($file));
    if (in_array($ext, $this->extsIcon)) {
      $md5 = MD5($file.$this->thumb_x);
      $targetDir = $this->cache.substr($md5,0,1).'/';
      $targetImagePath = $targetDir.$md5.".jpg";
      return $targetImagePath;
    }
    // fallback - no suitable icon
    return $this->defaultIcon;
  }
  /*========================================================================*/
  /**
   * @brief Gets title for image. Of the name is too long, the first 16 chars are used.
   * @param string $file The original filename.
   * @returns HTML H2 tag with title of an image.
   */
  private function getImageTitle($file){
    $bn = basename($file);
    $elipse = (mb_strlen($bn)>$this->imgTitleLen) ? '&hellip;':'';
    return '<h2 title="'.$bn.'">'.mb_substr($bn,0,$this->imgTitleLen).$elipse.'</h2>';
  }
  /*========================================================================*/
  /**
   * @brief Gets title for album. It can reorder parts of dir name, add some infromation like a date of modification etc.
   * @param string $file The original album dir.
   * @returns HTML H2 tag with title of an album.
   */
  private function getAlbumTitle($file){
    $bn = basename($file);

    if (isset($this->func_albumname) && $this->func_albumname !== NULL && function_exists($this->func_albumname)) {
      $title = call_user_func($this->func_albumname, $bn);
    } else {
      $patterns = array('~(19|20)(\d{2})-(\d{1,2})-(\d{1,2})_(.*)~si',
                        '~(19|20)(\d{2})-(\d{1,2})-(\d{1,2})-(\d{1,2})_(.*)~si');
      $replacements = array('\5 (\4. \3. \1\2)',
                            '\6 (\4-\5. \3. \1\2)');
      $bn = preg_replace($patterns, $replacements , $bn);
      $elipse = (strlen($bn) > $this->imgTitleLen) ? '&hellip;':'';
      $title = substr($bn, 0, $this->imgTitleLen).$elipse;
    }
    return '<h2 title="'.$bn.'">'.$title.'</h2>';
  }
  /*========================================================================*/
  /**
   * @brief Determines whether given array of EXIF contains dome GPS related data.
   * @param array $exif The array of exif data.
   * @returns boolean TRUE when some GPS related data was found, FALSE otherwise.
   */
  private function hasGPSData($exif) {
    return (isset($exif['GPSLatitude']) && isset($exif['GPSLongitude']));
  }
  /*========================================================================*/
  /**
   * @brief Parses EXIF GPS data to double representation.
   * @param array $exif The array of exif data.
   * @returns array $a[0] => (double) latitude, $a[1] => (double) longitude;   
   */
  private function getGPSLatLon($exif) {
    if (isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
      $lat = $exif['GPSLatitude'];
      $lon = $exif['GPSLongitude'];
      list($cit,$jmen) = explode('/', $lat[0]);
      if ($jmen == 0) return array(0,0);
      $gpslat = $cit/$jmen;
      list($cit,$jmen) = explode('/', $lat[1]);
      if ($jmen == 0) return array(0,0);
      $gpslat += $cit/($jmen*60);
      list($cit,$jmen) = explode('/', $lat[2]);
      if ($jmen == 0) return array(0,0);
      $gpslat += $cit/($jmen*3600);
      list($cit,$jmen) = explode('/', $lon[0]);
      if ($jmen == 0) return array(0,0);
      $gpslon = $cit/$jmen;
      list($cit,$jmen) = explode('/', $lon[1]);
      if ($jmen == 0) return array(0,0);
      $gpslon += $cit/($jmen*60);
      list($cit,$jmen) = explode('/', $lon[2]);
      if ($jmen == 0) return array(0,0);
      $gpslon += $cit/($jmen*3600);
      if($exif['GPSLatitudeRef'] == 'S') { $gpslat = -$gpslat; }
      if($exif['GPSLongitudeRef'] == 'W') { $gpslon = -$gpslon; }
      return array($gpslat,$gpslon);
    } else {
      return array(0,0);
    }
  }
  /*========================================================================*/
  // N 50° 47.880, E 15° 11.938
  /**
   * @brief Converts double representation of lat/lon to human form eg. N 50° 42.000, E 15° 42.000
   * @param double $lat Latitude.
   * @param double $lon Longitude.
   * @returns array $a['lat'] => (string) latitude, $a['lon'] => (string) longitude;
   */
  private function getHumanGPS($lat, $lon) {
    $slat = ($lat > 0) ? 'N ' : 'S ';
    $whole = floor($lat);
    $slat .= $whole.'&deg;&nbsp;'.number_format(($lat-$whole)*60,3);
    $slon = ($lon > 0) ? 'E ' : 'W ';
    $whole = floor($lon);
    $slon .= $whole.'&deg;&nbsp;'.number_format(($lon-$whole)*60,3);
    return array('lat'=>$slat, 'lon'=>$slon);
  }
  /*========================================================================*/
  /**
   * @brief Resizes given image (JPG, PNG, GIF, BMP) with respect to aspect ratio. Saves final image to cache.
   * @param string $path The original image..
   * @param double $max_x Final width.
   * @returns string Full path of resized image in cache.
   */
  public function resizeImage($path, $max_x) {
    $sourceImagePath = $path;
    $md5 = MD5($path.$max_x);
    $targetDir = $this->cache.substr($md5,0,1).'/';
    $targetImagePath = $targetDir.$md5.".jpg";
    $outputImageQuality = 80;

    if (!file_exists($targetDir)) mkdir($targetDir);

    /* Check if file is already cached, if so just deliver existing image */
    if(!file_exists($targetImagePath)) {

      /* MAIN RESIZING SCRIPT */

      /* Load Dimensions of Original Image */
      $originalImageSize = getimagesize($sourceImagePath);
      $original_x = $originalImageSize[0];
      $original_y = $originalImageSize[1];

      $square = 0;
      if($original_x > $original_y) {
        $max_y = 0;
        $max_x = $max_x;
      }
      else if($original_x < $original_y) {
        $max_y = $max_x;
        $max_x = 0;
      }
      else {
        $max_y = $max_x;
        $max_x = $max_x;
        $square = 1;
      }

      /* Work out ratios and which way to crop */
      $state = 0;
      if($square == 1) {
        if($max_x == 0) $max_x = $max_y;
        elseif($max_y == 0) $max_y = $max_x;
      }
      if($max_x == 0) $state = 1;
      elseif($max_y == 0) $state = 2;
      if($state == 0) {
        $testratio = $max_x / $max_y;
        $origratio = $original_x / $original_y;
        if($origratio > $testratio) $state = 1;
        elseif($origratio < $testratio) $state = 2;
        else $state = 3;
      }

      /* With ratios sorted, plot co-ordinates */
      if($state == 1) {
        /* make new-y = max-y OR crop sides */
        if($square == 0) {
          if(($original_y > $max_y) || ($enlarge == 1)) $new_y = $max_y;
          else $new_y = $original_y;
          $new_x = round(($original_x / ($original_y / $new_y)), 0);
          $srcx = 0;
          $srcy = 0;
          $srcw = $original_x;
          $srch = $original_y;
        } else {
          if(($original_y > $max_y) || ($enlarge == 1)) $new_y = $max_y;
          else $new_y = $original_y;
          $new_x = $new_y;
          $tempratio = ($original_y / $new_y);
          $sectionwidth = $new_y * $tempratio;
          $srcy = 0;
          $srch = $original_y;
          $srcx = floor(($original_x - $sectionwidth) / 2);
          $srcw = floor($sectionwidth);
        }

      }

      elseif($state == 2) {
        /* make new-x = max-x OR crop top & bottom */
        if($square == 0) {
          if(($original_x > $max_x) || ($enlarge == 1)) $new_x = $max_x;
          else $new_x = $original_x;
          $new_y = round(($original_y / ($original_x / $new_x)), 0);
          $srcx = 0;
          $srcy = 0;
          $srcw = $original_x;
          $srch = $original_y;
        } else {
          if(($original_x > $max_x) || ($enlarge == 1)) $new_x = $max_x;
          else $new_x = $original_x;
          $new_y = $new_x;
          $tempratio = ($original_x / $new_x);
          $sectionheight = $new_x * $tempratio;
          $srcx = 0;
          $srcw = $original_x;
          $srcy = floor(($original_y - $sectionheight) / 2);
          $srch = floor($sectionheight);
        }
      }
      elseif($state == 3) {
        /* original image ratio = new image ratio - use all of image */
        if($square == 0) {
          if(($original_x > $max_x) || ($enlarge == 1)) $new_x = $max_x;
          else $new_x = $original_x;
          $new_y = round(($original_y / ($original_x / $new_x)), 0);
          $srcx = 0;
          $srcy = 0;
          $srcw = $original_x;
          $srch = $original_y;
        } else {
          if(($original_x > $max_x) || ($enlarge == 1)) $new_x = $max_x;
          else $new_x = $original_x;
          $new_y = $new_x;
          $srcx = 0;
          $srcy = 0;
          $srcw = $original_x;
          $srch = $original_y;
        }
      }

      /* Do Conversion */
      $ext = strtolower($this->getExt($path));
      switch ($ext) {
        case 'jpg':
        case 'jpeg':
          $originalImage = imagecreatefromjpeg($sourceImagePath);
        break;
        case 'png':
          $originalImage = imagecreatefrompng($sourceImagePath);
        break;
        case 'gif':
          $originalImage = imagecreatefromgif($sourceImagePath);
        break;
        case 'bmp':
          $originalImage = imagecreatefromwbmp($sourceImagePath);
        break;
        default:
          $originalImage = imagecreatefromjpeg($sourceImagePath);
        break;
      }
      $newImage = ImageCreateTrueColor($new_x, $new_y);
      ImageCopyResampled ($newImage, $originalImage, 0, 0, $srcx, $srcy, $new_x, $new_y, $srcw, $srch);
      ImageJPEG ($newImage, $targetImagePath, $outputImageQuality);
      ImageDestroy($newImage);
      ImageDestroy($originalImage);

    }

    /* Output Image */
    $imageSize = getimagesize($targetImagePath);
    return $targetImagePath;
  }
  /*========================================================================*/
  /*========================================================================*/
}
?>
