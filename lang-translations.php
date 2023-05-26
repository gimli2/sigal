#!/usr/bin/env php
<?php
error_reporting(6135); // errors and warnings
unset($_COOKIE["sigal_lang"]);
$_SESSION["lang"] = $_SERVER["argv"][1]; // Adminer functions read language from session
if (isset($_SESSION["lang"])) {
	//include dirname(__FILE__) . "/langs/*.lang.php";
	if (isset($_SERVER["argv"][2]) || (!isset($langs[$_SESSION["lang"]]) && $_SESSION["lang"] != "xx")) {
		echo "Usage: php lang.php [lang]\nPurpose: Update lang/*.inc.php from source code messages.\n";
		exit(1);
	}
}

$messages_all = array();
foreach (array_merge(
	//glob(dirname(__FILE__) . "/dir/*.php"),
	glob(dirname(__FILE__) . "/sigal.class.php"),
  glob(dirname(__FILE__) . "/functions.php"),
	glob(dirname(__FILE__) . "/index.php")
) as $filename) {
	$file = file_get_contents($filename);
	if (preg_match_all("~lang\\(('(?:[^\\\\']+|\\\\.)*')([),])~", $file, $matches)) { // lang() always uses apostrophes
		$messages_all += array_combine($matches[1], $matches[2]);
	}
}

foreach (glob(dirname(__FILE__) . "/lang/" . ($_SESSION["lang"] ? $_SESSION["lang"] : "*") . ".lang.php") as $filename) {
	$messages = $messages_all;
	$file = file_get_contents($filename);
	$file = str_replace("\r", "", $file);
	preg_match_all("~^(\\s*(?:// [^'].*\\s+)?)(?:// )?(('(?:[^\\\\']+|\\\\.)*') => .*[^,\n]),?~m", $file, $matches, PREG_SET_ORDER);
	$s = "";
	foreach ($matches as $match) {
		if (isset($messages[$match[3]])) {
			// keep current messages
			$s .= "$match[1]$match[2],\n";
			unset($messages[$match[3]]);
		} else {
			// comment deprecated messages
			$s .= "$match[1]// $match[2],\n";
		}
	}
	if ($messages) {
		if (basename($filename) != "en.lang.php") {
			$s .= "\n";
		}
		foreach ($messages as $idf => $val) {
      //echo $idf.'=>'.$val;
			// add new messages
			if ($val == "," && strpos($idf, "%d")) {
				$s .= "\t$idf => array(),\n";
			} elseif (basename($filename) != "en.lang.php") {
				$s .= "\t$idf => null,\n";
			}
		}
	}
  $id = substr(basename($filename), 0, 2);
	$s = "<?php\n\$translations['".$id."'] = array(\n$s);\n?>\n";
	if ($s != $file) {
		file_put_contents($filename, $s);
		echo "$filename updated.\n";
	}
}
