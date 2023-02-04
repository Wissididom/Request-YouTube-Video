<?php
$validFor = 10; // Seconds
$_GET = array_change_key_case($_GET, CASE_LOWER);
$dateFormat = 'd.m.Y';
$useHtml = false;
if (isset($_GET['dateformat']))
	$dateFormat = $_GET['dateformat'];
$timeFormat = 'H:i:Y';
if (isset($_GET['timeformat']))
	$timeFormat = $_GET['timeformat'];
$dateTimeFormat = 'd.m.Y H:i:s';
if (isset($_GET['datetimeformat']))
	$dateTimeFormat = $_GET['datetimeformat'];
if (isset($_GET['fontfamily']) || isset($_GET['fontsize']) || isset($_GET['cssurl']) || isset($_GET['fontcolor'])) {
	$useHtml = true;
?>
<!DOCTYPE html>
<html>
<?php
}
if (isset($_GET['cssurl'])) {
	$css_urls = explode(';', $_GET['cssurl']);
	echo "\t<head>\n";
	for ($i = 0; $i < count($css_urls); $i++) {
		echo "\t\t<link rel=\"stylesheet\" href=\"" . $css_urls[$i] . "\">\n";
	}
	echo "\t</head>\n";
}
$bodyTag = "\t<body style=\"";
if (isset($_GET['fontfamily']))
	$bodyTag .= 'font-family: ' . $_GET['fontfamily'] . ';';
if (isset($_GET['fontsize']))
	$bodyTag .= 'font-size: ' . $_GET['fontsize'] . ';';
if (isset($_GET['fontcolor']))
	$bodyTag .= 'color: ' . $_GET['fontcolor'] . ';';
if ($useHtml === true)
	echo $bodyTag . "\">\n";

function getWithCurl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $retValue = curl_exec($ch);          
    curl_close($ch);
    return $retValue;
}

function xmlToArray($xml, $options = array()) {
    $defaults = array(
        'namespaceSeparator' => ':',//you may want this to be something other than a colon
        'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
        'alwaysArray' => array(),   //array of xml tag names which should always become arrays
        'autoArray' => true,        //only create arrays for tags which appear more than once
        'textContent' => '$',       //key used for the text content of elements
        'autoText' => true,         //skip textContent key if node has no attributes or child nodes
        'keySearch' => false,       //optional search and replace on tag and attribute names
        'keyReplace' => false       //replace values for above search values (as passed to str_replace())
    );
    $options = array_merge($defaults, $options);
    $namespaces = $xml->getDocNamespaces();
    $namespaces[''] = null; //add base (empty) namespace
 
    //get attributes from all namespaces
    $attributesArray = array();
    foreach ($namespaces as $prefix => $namespace) {
        foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
            //replace characters in attribute name
            if ($options['keySearch']) $attributeName =
                    str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
            $attributeKey = $options['attributePrefix']
                    . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                    . $attributeName;
            $attributesArray[$attributeKey] = (string)$attribute;
        }
    }
 
    //get child nodes from all namespaces
    $tagsArray = array();
    foreach ($namespaces as $prefix => $namespace) {
        foreach ($xml->children($namespace) as $childXml) {
            //recurse into child nodes
            $childArray = xmlToArray($childXml, $options);
            //Deprecated PHP8 "each" can be replaced with: $childTagName = key($childArray); $childProperties = current($childArray);
            //list($childTagName, $childProperties) = each($childArray);
            $childTagName = key($childArray); $childProperties = current($childArray);
 
            //replace characters in tag name
            if ($options['keySearch']) $childTagName =
                    str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
            //add namespace prefix, if any
            if ($prefix) $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;
 
            if (!isset($tagsArray[$childTagName])) {
                //only entry with this key
                //test if tags of this type should always be arrays, no matter the element count
                $tagsArray[$childTagName] =
                        in_array($childTagName, $options['alwaysArray']) || !$options['autoArray']
                        ? array($childProperties) : $childProperties;
            } elseif (
                is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                === range(0, count($tagsArray[$childTagName]) - 1)
            ) {
                //key already exists and is integer indexed array
                $tagsArray[$childTagName][] = $childProperties;
            } else {
                //key exists so convert to integer indexed array with previous value in position 0
                $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
            }
        }
    }
 
    //get text content of node
    $textContentArray = array();
    $plainText = trim((string)$xml);
    if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;
 
    //stick it all together
    $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;
 
    //return node as array
    return array(
        $xml->getName() => $propertiesArray
    );
}
/**
 * Turn all URLs in clickable links.
 *
 * @param string $value
 * @param array $protocols http/https, ftp, mail, twitter
 * @param array $attributes
 * @return string
 */
function linkify($value, $protocols = array('http', 'mail'), array $attributes = array()) {
	// Link attributes
	$attr = '';
	foreach ($attributes as $key => $val) {
		$attr .= ' ' . $key . '="' . htmlentities($val) . '"';
	}
	$links = array();
	// Extract existing links and tags
	$value = preg_replace_callback('~(<a .*?>.*?</a>|<.*?>)~i', function ($match) use (&$links) { return '<' . array_push($links, $match[1]) . '>'; }, $value);
	// Extract text links for each protocol
	foreach ((array)$protocols as $protocol) {
		switch ($protocol) {
			case 'http':
			case 'https':
				$value = preg_replace_callback('~(?:(https?)://([^\s<]+)|(www\.[^\s<]+?\.[^\s<]+))(?<![\.,:])~i', function ($match) use ($protocol, &$links, $attr) { if ($match[1]) $protocol = $match[1]; $link = $match[2] ?: $match[3]; return '<' . array_push($links, "<a $attr href=\"$protocol://$link\">$link</a>") . '>'; }, $value);
				break;
			case 'mail':
				$value = preg_replace_callback('~([^\s<]+?@[^\s<]+?\.[^\s<]+)(?<![\.,:])~', function ($match) use (&$links, $attr) { return '<' . array_push($links, "<a $attr href=\"mailto:{$match[1]}\">{$match[1]}</a>") . '>'; }, $value);
				break;
			case 'twitter':
				$value = preg_replace_callback('~(?<!\w)[@#](\w++)~', function ($match) use (&$links, $attr) { return '<' . array_push($links, "<a $attr href=\"https://twitter.com/" . ($match[0][0] == '@' ? '' : 'search/%23') . $match[1]  . "\">{$match[0]}</a>") . '>'; }, $value);
				break;
			default:
				$value = preg_replace_callback('~' . preg_quote($protocol, '~') . '://([^\s<]+?)(?<![\.,:])~i', function ($match) use ($protocol, &$links, $attr) { return '<' . array_push($links, "<a $attr href=\"$protocol://{$match[1]}\">{$match[1]}</a>") . '>'; }, $value);
				break;
		}
	}
	// Insert all link
	return preg_replace_callback('/<(\d+)>/', function ($match) use (&$links) { return $links[$match[1] - 1]; }, $value);
}
$index = 0;
if (isset($_GET['channelid']) || isset($_GET['playlistid']) || isset($_GET['username'])) {
	$xml = null;
	$fileSeparator = '';
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')           
		$fileSeparator = '\\';
	else 
		$fileSeparator = '/';
	if (!file_exists(getcwd() . $fileSeparator . 'cache'))
		mkdir(getcwd() . $fileSeparator . 'cache');
	if (isset($_GET['channelid'])) {
		$channelid = $_GET['channelid'];
		if (file_exists(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $channelid . '.xml')) {
			$valid_until = filemtime(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $channelid . '.xml') + $validFor;
			$expired = $valid_until < time();
			if ($expired === true) {
				$xml = getWithCurl('https://www.youtube.com/feeds/videos.xml?channel_id=' . $channelid);
				file_put_contents(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $channelid . '.xml', $xml);
				$xml = xmlToArray(simplexml_load_string($xml));
			} else {
				$xml = xmlToArray(simplexml_load_string(file_get_contents(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $channelid . '.xml')));
			}
		} else {
			$xml = getWithCurl('https://www.youtube.com/feeds/videos.xml?channel_id=' . $_GET['channelid']);
			file_put_contents(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $channelid . '.xml', $xml);
			$xml = xmlToArray(simplexml_load_string($xml));
		}
	} else if (isset($_GET['playlistid'])) {
		$playlistid = $_GET['playlistid'];
		if (file_exists(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $playlistid . '.xml')) {
			$valid_until = filemtime(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $playlistid . '.xml') + $validFor;
			$expired = $valid_until < time();
			if ($expired === true) {
				$xml = getWithCurl('https://www.youtube.com/feeds/videos.xml?playlist_id=' . $playlistid);
				file_put_contents(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $playlistid . '.xml', $xml);
				$xml = xmlToArray(simplexml_load_string($xml));
			} else {
				$xml = xmlToArray(simplexml_load_string(file_get_contents(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $playlistid . '.xml')));
			}
		} else {
			$xml = getWithCurl('https://www.youtube.com/feeds/videos.xml?playlist_id=' . $playlistid);
			file_put_contents(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $playlistid . '.xml', $xml);
			$xml = xmlToArray(simplexml_load_string($xml));
		}
	} else if (isset($_GET['username'])) {
		$username = $_GET['username'];
		if (file_exists(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $username . '.xml')) {
			$valid_until = filemtime(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $username . '.xml') + $validFor;
			$expired = $valid_until < time();
			if ($expired === true) {
				$xml = getWithCurl('https://www.youtube.com/feeds/videos.xml?user=' . $username);
				file_put_contents(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $username . '.xml', $xml);
				$xml = xmlToArray(simplexml_load_string($xml));
			} else {
				$xml = xmlToArray(simplexml_load_string(file_get_contents(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $username . '.xml')));
			}
		} else {
			$xml = getWithCurl('https://www.youtube.com/feeds/videos.xml?user=' . $username);
			file_put_contents(getcwd() . $fileSeparator . 'cache' . $fileSeparator . $username . '.xml', $xml);
			$xml = xmlToArray(simplexml_load_string($xml));
		}
	} else {
		echo "<!DOCTYPE html>\n<html>\n\t<body>\n\t\t<center style=\"color: red;\">\n\t\t\t500 Internal Server Error\n\t\t</center>\n\t</body>\n</html>\n";
		http_response_code(500);
	}
	if (isset($_GET['index'])) {
		$index = intval($_GET['index']);
	}
	if ($xml != null && isset($_GET['redirect'])) {
		header('Location: https://www.youtube.com/embed/' . $xml['feed']['entry'][$index]['yt:videoId'] . (isset($_GET['autoplay']) ? 'autoplay=1' : ''));
		exit(0);
	} else if ($xml != null && isset($_GET['redirecttothumbnail'])) {
		header('Location: ' . $xml['feed']['entry'][$index]['media:group']['media:thumbnail']['@url']);
		exit(0);
	} else if ($xml != null && isset($_GET['redirecttovideo'])) {
		header('Location: ' . $xml['feed']['entry'][$index]['link']['@href']);
		exit(0);
	} else if (isset($_GET['contenttype'])) {
		header('Content-Type: ' . $_GET['contenttype']);
	} else if ($useHtml) {
		header('Content-Type: text/html');
	} else {
		header('Content-Type: text/plain');
	}
	if ($xml != null && isset($_GET['print'])) {
		$toPrint = $_GET['print'];
		if (isset($_GET['contenttype']) || isset($_GET['fontfamily']) || isset($_GET['fontsize'])) {
			$toPrint = "\t\t" . $toPrint;
		}
		// Date-Format-String-Description: https://www.php.net/manual/de/function.date.php
		$toPrint = str_ireplace('$playlistid$', $xml['feed']['yt:playlistId'], $toPrint);
		$toPrint = str_ireplace('$channelid$', $xml['feed']['yt:channelId'], $toPrint);
		$toPrint = str_ireplace('$feedurl$', $xml['feed']['link']['@href'], $toPrint);
		$toPrint = str_ireplace('$pltitle$', $xml['feed']['title'], $toPrint);
		$toPrint = str_ireplace('$plauthorname$', $xml['feed']['author']['name'], $toPrint);
		$toPrint = str_ireplace('$plauthorurl$', $xml['feed']['author']['uri'], $toPrint);
		$toPrint = str_ireplace('$index$', $index, $toPrint);
		$toPrint = str_ireplace('$videoid$', $xml['feed']['entry'][$index]['yt:videoId'], $toPrint);
		$toPrint = str_ireplace('$uploaderid$', $xml['feed']['entry'][$index]['yt:channelId'], $toPrint);
		if ($useHtml === true)
			$toPrint = str_ireplace('$videotitle$', htmlentities($xml['feed']['entry'][$index]['media:group']['media:title']), $toPrint);
		else
			$toPrint = str_ireplace('$videotitle$', $xml['feed']['entry'][$index]['media:group']['media:title'], $toPrint);
		$toPrint = str_ireplace('$videothumbnail$', $xml['feed']['entry'][$index]['media:group']['media:thumbnail']['@url'], $toPrint);
		if ($useHtml === true)
			$toPrint = str_ireplace('$videodescription$', htmlentities(linkify($xml['feed']['entry'][$index]['media:group']['media:description'], array('http', 'mail'), array('target' => '_blank'))), $toPrint);
		else
			$toPrint = str_ireplace('$videodescription$', $xml['feed']['entry'][$index]['media:group']['media:description'], $toPrint);
		$toPrint = str_ireplace('$videostarcount$', $xml['feed']['entry'][$index]['media:group']['media:community']['media:starRating']['@count'], $toPrint);
		$toPrint = str_ireplace('$videostaraverage$', $xml['feed']['entry'][$index]['media:group']['media:community']['media:starRating']['@average'], $toPrint);
		$toPrint = str_ireplace('$videostarmin$', $xml['feed']['entry'][$index]['media:group']['media:community']['media:starRating']['@min'], $toPrint);
		$toPrint = str_ireplace('$videostarmax$', $xml['feed']['entry'][$index]['media:group']['media:community']['media:starRating']['@max'], $toPrint);
		$toPrint = str_ireplace('$videoviews$', $xml['feed']['entry'][$index]['media:group']['media:community']['media:statistics']['@views'], $toPrint);
		$toPrint = str_ireplace('$videolink$', $xml['feed']['entry'][$index]['link']['@href'], $toPrint);
		$toPrint = str_ireplace('$videoauthorname$', $xml['feed']['entry'][$index]['author']['name'], $toPrint);
		$toPrint = str_ireplace('$videoauthorurl$', $xml['feed']['entry'][$index]['author']['uri'], $toPrint);
		$date = new DateTime($xml['feed']['entry'][$index]['published']);
		$toPrint = str_ireplace('$videopublished$', $date->format($dateFormat), $toPrint);
		$toPrint = str_ireplace('$videopublishedtime$', $date->format($timeFormat), $toPrint);
		$toPrint = str_ireplace('$videopublisheddatetime$', $date->format($dateTimeFormat), $toPrint);
		$date = new DateTime($xml['feed']['entry'][$index]['updated']);
		$toPrint = str_ireplace('$videoupdated$', $date->format($dateFormat), $toPrint);
		$toPrint = str_ireplace('$videoupdatedtime$', $date->format($timeFormat), $toPrint);
		$toPrint = str_ireplace('$videoupdateddatetime$', $date->format($dateTimeFormat), $toPrint);
		$toPrint = str_replace("\\r", "\r", $toPrint);
		$toPrint = str_replace("\\n", "\n", $toPrint);
		if ($useHtml === true) {
			$toPrint = str_ireplace("\n", '<br>', $toPrint);
			$toPrint .= "\n";
		}
		echo $toPrint;
	}
} else {
	echo "<!DOCTYPE html>\n<html>\n\t<body>\n\t\t<center style=\"color: red;\">\n\t\t\tWeder Channel-ID noch Playlist-ID oder Username angegeben\n\t\t</center>\n\t</body>\n</html>\n";
	http_response_code(400);
}
if ($useHtml) {
?>
	</body>
</html>
<?php } ?>
<?php
/*if (isset($_GET['channelid'])) {
	echo '<!-- <iframe width="560" height="315" src="' . $_SERVER['PHP_SELF'] . '?channelid=' . $_GET['channelid'] . '&redirect' . '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe> -->';
} else if (isset($_GET['playlistid'])) {
	echo '<!-- <iframe width="560" height="315" src="' . $_SERVER['PHP_SELF'] . '?playlistid=' . $_GET['playlistid'] . '&redirect' . '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe> -->';
}*/ ?>
