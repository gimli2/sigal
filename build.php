<?php
set_time_limit(0);
date_default_timezone_set('Europe/Prague');
echo "\n<pre>\n";

	$in  = './index.php';
	$out = './index.min.php';
	
	include_once('sigal.class.php');
	$gg  = new Sigal();
	$out_downloadable = './'.$gg->version.'_index.min.php.txt';
	$out_demo = './demo/index.php';

	$nocomment = true;
	if (isset($_GET['nocomment']) && $_GET['nocomment']===1) {
		$nocomment = false;	// ve vystupu budou komentare
	}
	$comments = array(T_COMMENT, T_ML_COMMENT, T_DOC_COMMENT);

	copy($in, $out);
	$loop = 0;
	do {
		$data = file_get_contents($out);
		$md5 = md5($data); 
		$tokens = token_get_all($data);
		$cnt = count($tokens);
		
		$ndata = '';
		$i = 0;
		while($i < $cnt) {
			list($tid, $content) = $tokens[$i];
			//if ($tid===T_INCLUDE || $tid === T_INCLUDE_ONCE || $tid === T_REQUIRE || $tid === T_REQUIRE_ONCE) {
			// samotne include si ponechame pro nacitani pridavneho konfigu, takze se nesmi prelozit pri kompilaci
			if ($tid === T_INCLUDE_ONCE || $tid === T_REQUIRE || $tid === T_REQUIRE_ONCE) {
				$expr = '';
				while($tid !== ';') {
					list($tid, $content) = $tokens[++$i];
					if ($tid === T_STRING || $tid === T_CONSTANT_ENCAPSED_STRING) $expr .= $content; 
				}
				//echo "expr=$expr<br>";
				$fn = substr($expr, 1, -1);
				$ndata .= include_file($fn);
				$i++;
			}		
	
			//if (in_array($tid, $comments)) echo $content."<br>";

			// sestavime novy obsah
			if (is_array($tokens[$i])) {
				 
				if (in_array($tid, $comments)) {
					// komentare zachovame jen pokud nebude zaple nocomment
					if (!$nocomment) $ndata .= $content;
					if ($content == '/*START-DO-NOT-REMOVE-THIS*/') $ndata .= $content;
				} else {
          if ($tid === T_WHITESPACE) {
          /*
            if (strlen($content) > 1) {
              $ndata .= "\n";
            } else {
              $ndata .= $content;
            }
            */
            $ndata .= $content;
          } else {
            $ndata .= $content;
          }
				}
			} else {
        $ndata .= $tid;
			}
			
			$i++;
		}
		file_put_contents($out, $ndata);
		
		echo 'Compiling - loop = '.$loop."\n oldmd5=".$md5." ?= ".md5($ndata)."\n";	
		$loop++;
		// az se nic nezmeni, koncime
	} while(md5($ndata) !== $md5);

/*============================================================================*/
	// replace static files
	$sfiles = array(
		'./images/favicon.png',
		'./images/lock.png',
		'./images/defico.png',
    './images/defdirico.png',
		'./images/info.png',
		'./images/download.png',
		'./images/1px.gif',
		'./css/style.css',
		'./js/sigal.min.js',
		'./js/lazy.min.js',
		'./modules/ceebox/css/ceebox-min-static-img.css',
    /*
		'./modules/ceebox/js/jquery.js',
		'./modules/ceebox/js/jquery.metadata.min.js',
		'./modules/ceebox/js/jquery.swfobject.js',
		'./modules/ceebox/js/jquery.ceebox-min.js',
    */
    './modules/ceebox/js/ceeboxall.min.js',
		'./modules/ceebox/images/cee-close-btn.png',
		'./modules/ceebox/images/cee-next-btn-gif.gif',
		'./modules/ceebox/images/cee-next-btn.png',
		'./modules/ceebox/images/cee-prev-btn-gif.gif',
		'./modules/ceebox/images/cee-prev-btn.png',
		'./modules/ceebox/images/loader.gif',
	);
	
	foreach ($sfiles as $sf) {
		echo "Importing static file: ".$sf." ";
		$key = substr(basename($sf), 0, strrpos(basename($sf), '.'));
		$mime = 'text/plain';
		// rozpoznavani na widlich neni moc spolehlive
		if (function_exists('mime_content_type')) $mime = mime_content_type($sf);
		if (getExtension($sf) == 'css') $mime = 'text/css';
		if (getExtension($sf) == 'js') $mime = 'text/javascript';
		echo ' a mime type was recognized as <b>'.$mime."</b>\n";
    /*
    if ($mime == 'text/javascript') {
      $content = addslashes(file_get_contents($sf));
      $decodeIN = 'stripslashes(';
      $decodeOUT = ')';
    } else {
    */
      $content = base64_encode(file_get_contents($sf));
      $decodeIN = 'base64_decode(';
      $decodeOUT = ')';
    /*
    }
    */
    if ($sf == './modules/ceebox/js/ceeboxall.min.js') {
      $content = base64_encode(gzdeflate(file_get_contents($sf), 9));
      $decodeIN = 'gzinflate(base64_decode(';
      $decodeOUT = '))';
    }

 		$ndata = str_replace($sf, '?static='.$key, $ndata);
		$ndata = str_replace("/*START-DO-NOT-REMOVE-THIS*/", '
		if (isset($_GET["static"]) && $_GET["static"]==="'.$key.'") {
  		header("Content-Type: '.$mime.'"); header("Expires: Tue, 1 Jan 2030 05:00:00 GMT"); header("Cache-Control: max-age=8640000, public"); echo '.$decodeIN.'"' . $content . '"'.$decodeOUT.'; exit;
		}
		/*START-DO-NOT-REMOVE-THIS*/', $ndata);
 	}
	

	file_put_contents($out, $ndata);
	file_put_contents($out_downloadable, $ndata);
  file_put_contents($out_demo, $ndata);
	
/*
	$shrink = new ShrinkPHP;
	$shrink->addFile($out);
	$content = $shrink->getOutput();
	$content = str_replace("\r\n", "\n", $content);
	$content = trim(preg_replace("#[\t ]+(\r?\n)#", '$1', $content)); // right trim
	file_put_contents($out."min2", $content);	// save minified file
*/
echo "\n</pre>\n";

/*============================================================================*/
function getExtension($fname) {
	return mb_substr($fname,mb_strrpos($fname, '.')+1);
}
/*============================================================================*/
function include_file($fname) {
	echo 'Including file: '.$fname."\n";
  $data = file_get_contents($fname);
  $tokens = token_get_all($data);
  $first = reset($tokens);
  $last = end($tokens);
  
	if (is_array($first) && ($first[0] == T_OPEN_TAG)) {
		$ret_start = "\n";
		array_shift($tokens);	// kill 1st token
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
/*============================================================================*/
class ShrinkPHP
{
	public $firstComment = NULL;
	public $useNamespaces = FALSE;
	private $output = '';
	private $uses = array();
	private $inPHP;
	private $namespace;
	private $files;

	public function addFile($file)
	{
		$this->files[realpath($file)] = TRUE;
		$content = file_get_contents($file);
		$this->addContent($content, dirname($file));
	}


	public function addContent($content, $dir = NULL)
	{
		$tokens = token_get_all($content);

		if ($this->useNamespaces) { // find namespace
			$hasNamespace = FALSE;
			foreach ($tokens as $num => $token)	{
				if ($token[0] === T_NAMESPACE) {
					$hasNamespace = TRUE;
					break;
				}
			}
			if (!$hasNamespace) {
				$tokens = token_get_all(preg_replace('#<\?php#A', "<?php\nnamespace;", $content)); // . '}');
			}
		}

		if ($this->inPHP) {
			if (is_array($tokens[0]) && $tokens[0][0] === T_OPEN_TAG) {
				// trick to eliminate ?><?php
				unset($tokens[0]);
			} else {
				$this->output .= '?>';
				$this->inPHP = FALSE;
			}
		}


		$set = '!"#$&\'()*+,-./:;<=>?@[\]^`{|}';
		$space = $pending = FALSE;

		reset($tokens);
		while (list($num, $token) = each($tokens))
		{
			if (is_array($token)) {
				$name = $token[0];
				$token = $token[1];
			} else {
				$name = NULL;
			}

			if ($name === T_CLASS || $name === T_INTERFACE) {
				for ($i = $num + 1; @$tokens[$i][0] !== T_STRING; $i++);

			} elseif ($name === T_COMMENT || $name === T_WHITESPACE) {
				if ($pending) {
					$expr .= ' ';
				} else {
					$space = TRUE;
				}
				continue;

			} elseif ($name === T_PUBLIC && ($tokens[$num + 2][0] === T_FUNCTION || $tokens[$num + 4][0] === T_FUNCTION)) {
				next($tokens);
				continue;

			} elseif ($name === T_DOC_COMMENT) {
				if (!$this->firstComment) {
					$this->firstComment = $token;
					$this->output .= $token . "\n";
					$space = TRUE;
					continue;

				} elseif (preg_match('# @[A-Z]#', $token)) { // phpDoc annotations leave unchanged

				} else {
					$space = TRUE;
					continue;
				}

			} elseif ($name === T_INCLUDE || $name === T_INCLUDE_ONCE || $name === T_REQUIRE || $name === T_REQUIRE_ONCE) {
				$pending = $name;
				$reqToken = $token;
				$expr = '';
				continue;

			} elseif ($name === T_NAMESPACE || $name === T_USE) {
				$pending = $name;
				$expr = '';
				continue;

			} elseif ($pending && ($name === T_CLOSE_TAG || ($name === NULL && ($token === ';' || $token === '{' || $token === ',') || ($pending === T_USE && $token === '(')))) { // end of special
				$expr = trim($expr);
				if ($pending === T_NAMESPACE) {
					if ($this->namespace !== $expr) {
						if ($this->namespace !== NULL) {
							$this->output .= "}";
						}
						$this->output .= "namespace $expr{";
						$this->uses = array();
						$this->namespace = $expr;
					}

				} elseif ($pending === T_USE) {
					if ($token === '(') {
						$this->output .= "use(";

					} elseif (!isset($this->uses[$expr])) {
						$this->uses[$expr] = TRUE;
						$this->output .= "use\n$expr;";
					}

				} else { 
					// T_REQUIRE_ONCE, T_REQUIRE, T_INCLUDE, T_INCLUDE_ONCE
					$newFile = strtr($expr, array(
						'__DIR__' => "'" . addcslashes($dir, '\\\'') . "'",
						'dirname(__FILE__)' => "'" . addcslashes($dir, '\\\'') . "'",
					));
					$newFile = @eval('return ' . $newFile . ';');

					if ($newFile && realpath($newFile)) {
						$oldNamespace = $this->namespace;

						if ($pending !== T_REQUIRE_ONCE || !isset($this->files[realpath($newFile)])) {
							$this->addFile($newFile);
						}

						if (!$this->inPHP && $name !== T_CLOSE_TAG) {
							$this->output .= '<?php ';
							$this->inPHP = TRUE;
						}

						if ($this->namespace !== $oldNamespace) {
							if ($this->namespace !== NULL) {
								$this->output .= "}";
							}
							$this->namespace = $oldNamespace;
							$this->output .= "namespace $oldNamespace{";
							if ($this->uses && $oldNamespace) {
								$this->output .= "use\n" . implode(',', array_keys($this->uses)) . ";";
							}
						}
					} else {
						$this->output .= " $reqToken $expr;";
					}
				}
				if ($token !== ',') {
					$pending = FALSE;
				}
				$expr = '';
				continue;

			} elseif ($name === T_OPEN_TAG || $name === T_OPEN_TAG_WITH_ECHO) { // <?php <? <% <?=  <%=
				$this->inPHP = TRUE;

			} elseif ($name === T_CLOSE_TAG) { // ? > %>
				if ($num === count($token-1)) continue; // eliminate last close tag
				$this->inPHP = FALSE;

			} elseif ($token === ')' && substr($this->output, -1) === ',') {  // array(... ,)
				$this->output = substr($this->output, 0, -1);

			} elseif ($pending) {
				$expr .= $token;
				continue;
			}

			if ($space) {
				if (strpos($set, substr($this->output, -1)) === FALSE && strpos($set, $token{0}) === FALSE) {
					$this->output .= "\n";
				}
				$space = FALSE;
			}

			$this->output .= $token;
		}
	}



	public function getOutput()
	{
		if ($this->namespace !== NULL) {
			$this->output .= "}";
			$this->namespace = NULL;
		}
		return $this->output;
	}

}


?>
