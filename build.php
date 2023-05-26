<?php
  set_time_limit(0);
  date_default_timezone_set('Europe/Prague');

  define('C', "\033[39m");  // default
  define('CR', "\033[91m"); // red
  define('CG', "\033[92m"); // green
  define('CB', "\033[94m"); // blue
  

  // get version
  include_once('sigal.class.php');
  $gg  = new Sigal();
  $version = $gg->version;
  unset($gg);
  
  // set input and output files
  $in  = './index.php';
  $out = './index.min.php';
  $out_downloadable = './'.$version.'_index.min.php.txt';
  $out_demo = './demo/index.php';

  $nocomment = true;
  if (isset($_GET['nocomment']) && $_GET['nocomment']===1) {
    $nocomment = false; // ve vystupu budou komentare
  }
  $comments = @array(T_COMMENT, T_DOC_COMMENT);

  // process all INCLUDE_ONCE, REQUIRE and REQUIRE_ONCE
  // INCLUDE is reserved for including config.php, therefore must be skipped in replacing loop
  copy($in, $out);
  $loop = 0;
  do {
    $data = file_get_contents($out);
    $md5 = md5($data); 
    $tokens = token_get_all($data);
    // print_r($tokens);
    $cnt = count($tokens);
    
    $ndata = '';
    $i = 0;
    while($i < $cnt) {
      $tid='';
      list($tid, $content) = handle_token($tokens[$i]);
      
      // T_INCLUDE ommited, see comment above
      if ($tid === T_INCLUDE_ONCE || $tid === T_REQUIRE || $tid === T_REQUIRE_ONCE) {
        $expr = '';
        while($tid !== ';') {
          $i++;
          list($tid, $content) = handle_token($tokens[$i]);
          // echo "IN: ".$tid." => ".token_name($tid)." | ".$content."\n";
          if ($tid === T_STRING || $tid === T_CONSTANT_ENCAPSED_STRING) $expr .= $content; 
        }
        // echo "expr=$expr\n";
        // die();
        $fn = substr($expr, 1, -1);
        $ndata .= include_file($fn);
        $i++;
      }   
    
      // build new content
      if (is_array($tokens[$i])) {
         
        if (in_array($tid, $comments)) {
          // comments will be ommited unles $nocomment == false
          if (!$nocomment) $ndata .= $content;
          // this is reserved placeholder for including static files
          if ($content == '/*STATIC-FILES-PLACEHOLDER*/') $ndata .= $content;
        } else {
          $ndata .= $content;
        }
      } else {
        $ndata .= $tid;
      }
      
      $i++;
    }
    file_put_contents($out, $ndata);
    
    echo CB.'Compiling:'.C.' loop # '.$loop."\n"; // oldmd5=".$md5." ?= ".md5($ndata)."\n"; 
    $loop++;
    // we can finish when there is no diff between versions
  } while(md5($ndata) !== $md5);

/*============================================================================*/
  // replace static files
  $sfiles = array(
    # blueimpGallery
    './img/close.svg',
    './img/close.png',
    './img/error.svg',
    './img/error.png',
    './img/loading.svg',
    './img/loading.gif',
    './img/next.svg',
    './img/next.png',
    './img/play-pause.svg',
    './img/play-pause.png',
    './img/prev.svg',
    './img/prev.png',
    './img/video-play.svg',
    './img/video-play.png',
    # sigal
    './img/defico.svg',
    './img/defdirico.svg',
    './img/lock.svg',
    './img/favicon.png',
    './img/1px.gif',
    './css/style.css',
    './css/blueimp-gallery.min.css',
    './js/sigal.min.js',
    './js/lazy.min.js',
    './js/blueimp-gallery.min.js',
    './js/jquery-3.7.0.min.js',
  );
  
  foreach ($sfiles as $sf) {
    echo CB."Including static file: ".C.$sf." ";
    $key = substr(basename($sf), 0, strrpos(basename($sf), '.'));
    $key = basename($sf);
    $mime = 'text/plain';
    // notice: mime identification sotmetimes work wierd on windows
    if (function_exists('mime_content_type')) $mime = mime_content_type($sf);
    if (getExtension($sf) == 'css') $mime = 'text/css';
    if (getExtension($sf) == 'js') $mime = 'text/javascript';
    echo ' a mime type was recognized as '.$mime." | key = ".CG.$key.C."\n";
    
    $content = base64_encode(file_get_contents($sf));
    $decodeIN = 'base64_decode(';
    $decodeOUT = ')';
    
    // replace images in CSS by static ones
    if ($sf == './css/blueimp-gallery.min.css') {
      $content = file_get_contents($sf);
      $content = preg_replace('~url\(\.\./img/([^\.]+\.[a-z]+)\)~i', 'url(?static=\\1)', $content);
      $content = base64_encode($content);
    }
    
    if (substr($sf, -7) == '.min.js') {
      $content = base64_encode(gzdeflate(file_get_contents($sf), 9));
      $decodeIN = 'gzinflate(base64_decode(';
      $decodeOUT = '))';
    }

    $ndata = str_replace($sf, '?static='.$key, $ndata);
    $ndata = str_replace("/*STATIC-FILES-PLACEHOLDER*/", '
    if (isset($_GET["static"]) && $_GET["static"]==="'.$key.'") {
      header("Content-Type: '.$mime.'"); header("Expires: Tue, 1 Jan 2030 05:00:00 GMT"); header("Cache-Control: max-age=8640000, public"); echo '.$decodeIN.'"' . $content . '"'.$decodeOUT.'; exit;
    }
    /*STATIC-FILES-PLACEHOLDER*/', $ndata);
  }
  
  // store output
  file_put_contents($out, $ndata);
  file_put_contents($out_downloadable, $ndata);
  file_put_contents($out_demo, $ndata);
  
  $fs = filesize($out);
  echo "=========================================\n";
  echo CG."Sigal version: $version\n".C;
  echo CG."Compiled size: $fs B\n".C;
  echo "=========================================\n";
  
/*============================================================================*/
function handle_token($t) {
  // Since PHP 7.0 it must be handled separately due to not working assignments like: list($var1, $var2) = "string"
  if (is_array($t)) {
    return $t; 
  } else {
    return array($t, NULL);
  }
}
/*============================================================================*/
function getExtension($fname) {
  return mb_substr($fname,mb_strrpos($fname, '.')+1);
}
/*============================================================================*/
function include_file($fname) {
  echo CB."Including file: ".CG.$fname.C."\n";
  $data = file_get_contents($fname);
  $tokens = token_get_all($data);
  $first = reset($tokens);
  $last = end($tokens);
  
  if (is_array($first) && ($first[0] == T_OPEN_TAG)) {
    $ret_start = "\n";
    array_shift($tokens); // kill 1st token
  } else {
    $ret_start = "?>\n";
  }
  if (is_array($last) && ($last[0] == T_CLOSE_TAG)) {
    $ret_end =  "\n";
    array_pop($tokens); // kill last token
  } elseif (is_array($last) && ($last[0] == T_INLINE_HTML)) {
    $ret_end = "<?php\n";
  } else {
    $ret_end = "\n";
  }
  $data = implodeTokens($tokens);

  return $ret_start . $data . $ret_end;
}
/*============================================================================*/
function implodeTokens($tokens) {
  $cnt = count($tokens);
  $ndata = '';
  $i = 0;
  while($i < $cnt) {
    // sestavime novy obsah
    $ndata .= (is_array($tokens[$i])) ? $tokens[$i][1] : $tokens[$i];
    $i++;
  }
  return $ndata;
} 
