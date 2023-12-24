<?php

// ------------------------------------------------------------------------
// START CONFIG

// Base URL of this service, include trailing slash
$baseurl = "https://yourdomain.com/";

// Date to start the playlist publishing from. 
// Setting it in the past ensures other (actual) podcast episodes appear first on the Light Phone
$publish_date = date_format( date_create('1980-01-01'), 'D, d M Y H:i:s O' ); 

// Location of playlists as mapped in docker container, including trailing slash
$playlist_root = '/var/playlists/';

// Full path for media mount, including trailing slash. 
// Be sure this matches what Jellyfin's docker and this docker have set.
$media_root = '/media/Music/';

// If you want your playlist randomized (every time Light Phone refreshes the podcast feed)
$randomize = true;

// END CONFIG
// ------------------------------------------------------------------------
	
if(isset($_GET['playlist'])) {
	$playlist = urldecode($_GET['playlist']);
	processPlaylist($playlist);
	
} elseif (isset($_GET['song'])) {
	$song = urldecode($_GET['song']);
	streamSong($song);
	
} else {
	listPlaylists();
}


function listPlaylists() {
	global $baseurl, $playlist_root;
	
	echo "Jellyfin Playlists";
	
	$playlists = array_diff(scandir($playlist_root), array('..', '.'));

	$link_format = "<a href='%s'>%s</a>";
	
	echo "<ul>";
	foreach($playlists as $playlist) {
		$url = $baseurl . "?playlist=" . $playlist;
		echo "<li>" . sprintf($link_format, $url, $playlist) . "</li>";
	}
	echo "</ul>";
}




function processPlaylist($playlist_name) {
	global $playlist_root, $randomize, $media_root, $publish_date, $baseurl;
	
	$playlist = $playlist_root . $playlist_name . '/playlist.xml';

	$objXmlDocument = simplexml_load_file($playlist);
	if ($objXmlDocument === FALSE) {
		echo "There were errors parsing the XML file.\n";
		foreach(libxml_get_errors() as $error) {
			echo $error->message;
		}
		exit;
	}

	$playlist_items = json_decode( json_encode ($objXmlDocument->PlaylistItems), TRUE);
	$playlist_items = $playlist_items['PlaylistItem'];

	$dom = new DOMDocument();
	$dom->encoding = 'utf-8';
	$dom->xmlVersion = '1.0';
	$dom->formatOutput = true;

	$root = $dom->createElement('rss');
	$root->setAttributeNode(new DOMAttr('version', '2.0'));
	$root->setAttributeNode(new DOMAttr('xmlns:itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd'));
	$root->setAttributeNode(new DOMAttr('xmlns:content', 'http://purl.org/rss/1.0/modules/content/'));

    $channel_node = $dom->createElement('channel');

    // Channel Title
    $title_node = $dom->createElement('title');
    $title_node->appendChild( $dom->createTextNode($objXmlDocument->LocalTitle) );
    $channel_node->appendChild($title_node);

    // Channel Owner
    $owner_node = $dom->createElement('itunes:owner');
    $owner_name_node = $dom->createElement('itunes:name');
    $owner_name_node->appendChild( $dom->createTextNode("Jellyfin") );
    $owner_node->appendChild($owner_name_node);
    $channel_node->appendChild($owner_node);
 
    $iteration_count = 1;

    // Randomize
    if($randomize) {
		shuffle($playlist_items);
    }

	foreach($playlist_items as $item) {

		$rel_path = str_replace( $media_root, '', $item['Path'] );
		$path_parts = explode( '/', $rel_path );	
		$file_parts = explode(".", $path_parts[2]);
		$song_parts = explode(" - ", $file_parts[0]);

		$song_name = $song_parts[1];
		$artist_name = $path_parts[0];
			
		$guid = sprintf(
			'%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
			mt_rand(0, 65535),
			mt_rand(0, 65535),
			mt_rand(0, 65535),
			mt_rand(16384, 20479),
			mt_rand(32768, 49151),
			mt_rand(0, 65535),
			mt_rand(0, 65535),
			mt_rand(0, 65535));

        // Item	
        $item_node = $dom->createElement("item");
        $channel_node->appendChild($item_node);

        // Item Title
		$item_title_node = $dom->createElement("title");
		$item_title_node->appendChild( 
			$dom->createTextNode( $song_name . ' (' . $artist_name .')' ) );
		$item_node->appendChild($item_title_node);

		// Item Publish Date
		$pub_node = $dom->createElement('pubDate');
			$pub_node->appendChild( $dom->createTextNode( $publish_date ) );
		$item_node->appendChild( $pub_node ); 

		// Item GUID
		$guid_node = $dom->createElement("guid");
		$guid_node->setAttributeNode( new DOMAttr('isPermaLink', 'false') );
			$guid_node->appendChild( $dom->createTextNode( $guid ) );
		$item_node->appendChild($guid_node);

		// Item Episode
		$episode_node = $dom->createElement('itunes:episode');
		$episode_node->appendChild( $dom->createTextNode( $iteration_count ) ); 
		$item_node->appendChild($episode_node); 

		// Item Enclosure
		$enclosure_node = $dom->createElement("enclosure");
		$enclosure_node->setAttributeNode(
			new DOMAttr('url', $baseurl . "song.php?q=" . urlencode($item['Path'])));
		$enclosure_node->setAttributeNode( new DOMAttr('type', 'audio/mpeg') ); 
			$item_node->appendChild($enclosure_node);

		$iteration_count++;
	}

    $root->appendChild($channel_node);
	$dom->appendChild($root);

	Header('Content-type: text/xml');
	print $dom->saveXML();

}




function streamSong($song) {
	// Mostly taken from https://github.com/tuxxin/MP4Streaming

	if(!file_exists($song)) {
	  echo "no file";
	  exit;
	}
	$fp = @fopen($file, 'rb');

	$size = filesize($file); // File size
	$length = $size; // Content length
	$start = 0; // Start byte
	$end = $size - 1; // End byte


	header('Content-type: audio/m4a');
	//header("Accept-Ranges: 0-$length");
	header("Accept-Ranges: bytes");
	if (isset($_SERVER['HTTP_RANGE'])) {
		$c_start = $start;
		$c_end = $end;
		list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
		if (strpos($range, ',') !== false) {
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			exit;
		}
		
		if ($range == '-') {
			$c_start = $size - substr($range, 1);
		}else{
			$range = explode('-', $range);
			$c_start = $range[0];
			$c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
		}
		$c_end = ($c_end > $end) ? $end : $c_end;
		
		if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			exit;
		}
		$start = $c_start;
		$end = $c_end;
		$length = $end - $start + 1;
		fseek($fp, $start);
		header('HTTP/1.1 206 Partial Content');
	}
	header("Content-Range: bytes $start-$end/$size");
	header("Content-Length: ".$length);
	$buffer = 1024 * 8;
	while(!feof($fp) && ($p = ftell($fp)) <= $end) {
		if ($p + $buffer > $end) {
			$buffer = $end - $p + 1;
		}
		set_time_limit(0);
		echo fread($fp, $buffer);
		flush();
	}
	fclose($fp);
	exit();
}


?>
