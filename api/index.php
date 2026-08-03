<?php
ob_start();
libxml_use_internal_errors(true);
$validFor = 10; // seconds
$_GET = array_change_key_case($_GET, CASE_LOWER);
$dateFormat = 'd.m.Y';
$useHtml = false;
if (isset($_GET['dateformat']))
	$dateFormat = $_GET['dateformat'];
$timeFormat = 'H:i:s';
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
	foreach ($css_urls as $css_url) {
		echo "\t\t<link rel=\"stylesheet\" href=\"" . htmlspecialchars($css_url, ENT_QUOTES, 'UTF-8') . "\">\n";
	}
	echo "\t</head>\n";
}

if ($useHtml) {
	$styles = [];
	if (isset($_GET['fontfamily']))
		$styles[] = 'font-family: ' . htmlspecialchars($_GET['fontfamily'], ENT_QUOTES, 'UTF-8') . ';';
	if (isset($_GET['fontsize']))
		$styles[] = 'font-size: ' . htmlspecialchars($_GET['fontsize'], ENT_QUOTES, 'UTF-8') . ';';
	if (isset($_GET['fontcolor']))
		$styles[] = 'color: ' . htmlspecialchars($_GET['fontcolor'], ENT_QUOTES, 'UTF-8') . ';';
	echo "\t<body";
	if ($styles) {
		echo ' style="' . implode(';', $styles) . '"';
	}
	echo ">\n";
}

function getWithCurl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $retValue = curl_exec($ch);          
    curl_close($ch);
    return $retValue;
}

function xmlToArray($xml, $options = array()) {
	if ($xml === false) {
		http_response_code(502);
		exit("Invalid XML returned by YouTube.");
	}
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
function fetchFeed($cacheKey, $url, $validFor) {
	$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
	$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.xml';
	if (!file_exists($cacheDir))
		mkdir($cacheDir, 0777, true);
	if (file_exists($cacheFile)) {
		$valid_until = filemtime($cacheFile) + $validFor;
		$expired = $valid_until < time();
		if ($expired) {
			$xml = getWithCurl($url);
			if ($xml === false) {
				http_response_code(502);
				exit("Unable to retrieve YouTube feed.");
			}
			file_put_contents($cacheFile, $xml);
			$xml = xmlToArray(simplexml_load_string($xml));
		} else {
			$xml = xmlToArray(simplexml_load_string(file_get_contents($cacheFile)));
		}
	} else {
		$xml = getWithCurl($url);
		if ($xml === false) {
			http_response_code(502);
			exit("Unable to retrieve YouTube feed.");
		}
		file_put_contents($cacheFile, $xml);
		$xml = xmlToArray(simplexml_load_string($xml));
	}
	return $xml;
}
$index = 0;
if (isset($_GET['channelid']) || isset($_GET['playlistid'])) {
	$xml = null;
	if (isset($_GET['channelid'])) {
		$channelid = $_GET['channelid'];
		$xml = fetchFeed(hash('sha256', $channelid), 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channelid, $validFor);
	} else if (isset($_GET['playlistid'])) {
		$playlistid = $_GET['playlistid'];
		$xml = fetchFeed(hash('sha256', $playlistid), 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . $playlistid, $validFor);
	} else {
		http_response_code(400);
		exit("Please either use channelid or playlistid.");
	}
	if (isset($_GET['index'])) {
		$index = max(0, (int)$_GET['index']);
	}
	if (!isset($xml['feed']['entry'][$index])) {
		http_response_code(404);
		exit('Video index out of range.');
	}
	if (!empty($xml) && isset($_GET['redirect'])) {
		header('Location: https://www.youtube.com/embed/' . $xml['feed']['entry'][$index]['yt:videoId'] . (isset($_GET['autoplay']) ? '?autoplay=1' : ''));
		exit(0);
	} else if (!empty($xml) && isset($_GET['redirecttothumbnail'])) {
		header('Location: ' . $xml['feed']['entry'][$index]['media:group']['media:thumbnail']['@url']);
		exit(0);
	} else if (!empty($xml) && isset($_GET['redirecttovideo'])) {
		header('Location: ' . $xml['feed']['entry'][$index]['link']['@href']);
		exit(0);
	} else if (isset($_GET['contenttype'])) {
		header('Content-Type: ' . $_GET['contenttype']);
	} else if ($useHtml) {
		header('Content-Type: text/html');
	} else {
		header('Content-Type: text/plain');
	}
	if (!empty($xml) && isset($_GET['print'])) {
		$toPrint = $_GET['print'];
		if (isset($_GET['contenttype']) || isset($_GET['fontfamily']) || isset($_GET['fontsize'])) {
			$toPrint = "\t\t" . $toPrint;
		}
		// Date-Format-String-Description: https://www.php.net/manual/de/function.date.php
		$published = new DateTime($xml['feed']['entry'][$index]['published']);
		$updated = new DateTime($xml['feed']['entry'][$index]['updated']);
		$toPrint = strtr($toPrint, [
			'$playlistid$' => $xml['feed']['yt:playlistId'],
			'$channelid$' => $xml['feed']['yt:channelId'],
			'$feedurl$' => $xml['feed']['link']['@href'],
			'$pltitle$' => $xml['feed']['title'],
			'$plauthorname$' => $xml['feed']['author']['name'],
			'$plauthorurl$' => $xml['feed']['author']['uri'],
			'$index$' => $index,
			'$videoid$' => $xml['feed']['entry'][$index]['yt:videoId'],
			'$uploaderid$' => $xml['feed']['entry'][$index]['yt:channelId'],
			'$videotitle$' => $useHtml ? htmlspecialchars($xml['feed']['entry'][$index]['media:group']['media:title'], ENT_QUOTES, 'UTF-8') : $xml['feed']['entry'][$index]['media:group']['media:title'],
			'$videothumbnail$' => $xml['feed']['entry'][$index]['media:group']['media:thumbnail']['@url'],
			'$videodescription$' => $useHtml ? linkify(htmlspecialchars($xml['feed']['entry'][$index]['media:group']['media:description'], ENT_QUOTES, 'UTF-8'), array('http', 'mail'), array('target' => '_blank')) : $xml['feed']['entry'][$index]['media:group']['media:description'],
			'$videostarcount$' => $xml['feed']['entry'][$index]['media:group']['media:community']['media:starRating']['@count'],
			'$videostaraverage$' => $xml['feed']['entry'][$index]['media:group']['media:community']['media:starRating']['@average'],
			'$videostarmin$' => $xml['feed']['entry'][$index]['media:group']['media:community']['media:starRating']['@min'],
			'$videostarmax$' => $xml['feed']['entry'][$index]['media:group']['media:community']['media:starRating']['@max'],
			'$videoviews$' => $xml['feed']['entry'][$index]['media:group']['media:community']['media:statistics']['@views'],
			'$videolink$' => $xml['feed']['entry'][$index]['link']['@href'],
			'$videoauthorname$' => $xml['feed']['entry'][$index]['author']['name'],
			'$videoauthorurl$' => $xml['feed']['entry'][$index]['author']['uri'],
			'$videopublished$' => $published->format($dateFormat),
			'$videopublishedtime$' => $published->format($timeFormat),
			'$videopublisheddatetime$' => $published->format($dateTimeFormat),
			'$videoupdated$' => $updated->format($dateFormat),
			'$videoupdatedtime$' => $updated->format($timeFormat),
			'$videoupdateddatetime$' => $updated->format($dateTimeFormat),
			"\\r" => "\r",
			"\\n" => "\n",
			"\n" => $useHtml ? "<br>" : "\n"
		]);
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
<?php
}
/*if (isset($_GET['channelid'])) {
	echo '<!-- <iframe width="560" height="315" src="' . $_SERVER['PHP_SELF'] . '?channelid=' . $_GET['channelid'] . '&redirect' . '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe> -->';
} else if (isset($_GET['playlistid'])) {
	echo '<!-- <iframe width="560" height="315" src="' . $_SERVER['PHP_SELF'] . '?playlistid=' . $_GET['playlistid'] . '&redirect' . '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe> -->';
}*/
ob_end_flush();
?>
