<?php
  /*========================================================================*/

  /*STATIC-FILES-PLACEHOLDER*/

  /*========================================================================*/
  @set_time_limit(120);
  //error_reporting(6135); // errors and warnings
  error_reporting(E_ERROR | E_WARNING | E_PARSE);
  
  include_once 'sigal.class.php';
  include_once 'zipstream.class.php';
  include_once 'functions.php'; // olny for language switching combobox

  // read $translations array
  foreach (glob('lang/*.lang.php') as $filename) {
    include $filename;
  }
  $gg = new Sigal();

  /*========================================================================*/
  /* load additional configuration */
  $conf = array();
  if (file_exists('./config.php')) include './config.php';
  $kws = array(
    'dir', 'cache', 'defaultIcon', 'icotitlefname', 'lockfname', 'thumb_x', 'thumb_y', 'middle_x', 'imgTitleLen', 'galTitle', 'legal_notice', 'date_format',
    'enable_mass_download', 'show_exif_tab', 'show_gps_tab', 'cache_image_quality', 
    'func_sortimages', 'func_sortalbums', 'func_sortgroups', 'func_scandir', 'func_albumname', 'func_groupname', 'func_getalbums', 'func_videoimage', 'func_avfileplay'
  );
  foreach ($kws as $item) {
    if (isset($conf[$item])) $gg->$item = $conf[$item];
  }
  // PROBABLY BROKES getThumb()
  // assume, that we are capable to get thumbnails for video
  if(isset($gg->func_videoimage) && $gg->func_videoimage!=='') {
    $gg->extsIcon = array_merge($gg->extsIcon, $gg->extsVideo);
  }
  /*========================================================================*/
  if (isset($_POST["lang"])) {
    $gg->cookie("sigal_lang", $_POST["lang"]);
    $loc = $gg->remove_from_uri();
    $loc = ($loc !== '') ? $loc : '.';
    header("Location: ".$loc);
    die();
  }
  /*========================================================================*/
  if (isset($_GET['credits'])) {
    $gg->showCreditPage();
    die();
  }
  /*========================================================================*/
  if (isset($_GET['dlselected'])) {
    $gg->downloadZippedImages();
  }
  /*========================================================================*/
  if (isset($_GET['mkmid'])) {
    $gg->makeMiddleImage($_GET['mkmid']);
  }
  /*========================================================================*/
  if (isset($_GET['mkthumb'])) {
    $gg->makeThumbImage($_GET['mkthumb']);
  }
  /*========================================================================*/
  if (isset($_GET['foto'])) {
    session_start();
    if (isset($_POST['fakce']) && $_POST['fakce']==='addaccess') $gg->addAccess();
    $gg->showImage($_GET['foto']);
    die();
  }
  /*========================================================================*/
  if (isset($_GET['alb']) && $_GET['alb']!=='') {
    session_start();
    if (isset($_POST['fakce']) && $_POST['fakce']==='addaccess') $gg->addAccess();
    $gg->showAlbum($_GET['alb']);
    die();
  }
  /*========================================================================*/
  if (isset($_GET['avfile'])) {
    $gg->showVideo($_GET['avfile']);
    die();
  }
  /*========================================================================*/
  // jen pro vyvoj neminifikovane verze
  if (isset($_GET['static'])) {
    header('Location: index.min.php?static='.$_GET['static']);
  }
  /*========================================================================*/
  $gg->showGallery();
