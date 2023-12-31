<?php

include 'settings.php';

	
if(isset($_GET['playlist'])) {
	$playlist = urldecode($_GET['playlist']);
	$randomize = urldecode($_GET['randomize']) === "true";
	processPlaylist($playlist, $randomize);
	
} elseif (isset($_GET['song'])) {
	$song = urldecode($_GET['song']);
	streamSong($song);
	
} else {
	listPlaylists();
}




function formatDateForRSS($date) {
  return date_format( $date, 'D, d M Y H:i:s O' );
}




function listPlaylists() {
	global $baseurl, $playlist_root;
	
	echo "<h1>Jellyfin Playlists</h1>";
	
	$playlists = array_diff(scandir($playlist_root), array('..', '.'));

	$link_format = "<a href='%s'>feed</a>";
	
	echo "<table>";
	echo "<thead>";
	echo "<td>Playlist</td>";
	echo "<td>Ordered</td>";
	echo "<td>Randomized</td>";
	echo "</thead>";
	foreach($playlists as $playlist) {
		$url_plain = $baseurl . "?playlist=" . $playlist . "&randomize=false";
		$url_randomize = $baseurl . "?playlist=" . $playlist . "&randomize=true";

		echo "<tr>";
		echo "<td>" . $playlist . "</td>";
		echo "<td>" . sprintf($link_format, $url_plain) . "</td>";
		echo "<td>" . sprintf($link_format, $url_randomize) . "</td>";
		echo "</tr>";
	}
	echo "</table>";
}




function processPlaylist($playlist_name, $randomize) {
	global $playlist_root, $media_root, $publish_date, $baseurl;
	
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

    // Channel Author
    $author_node = $dom->createElement('itunes:author');
    $author_node->appendChild( $dom->createTextNode('Jellyfin') );
    $channel_node->appendChild( $author_node );

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


        // Last Build Date
	$last_build_node = $dom->createElement('lastBuildDate');
	$last_build_node->appendChild(
	 	$dom->createTextNode( formatDateForRSS( date_create('now') ) ) );
        $channel_node->appendChild($last_build_node);


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
			$pub_node->appendChild( $dom->createTextNode( formatDateForRSS($publish_date) ) );
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
			new DOMAttr('url', $baseurl . "?song=" . urlencode($item['Path'])));
		$enclosure_node->setAttributeNode( new DOMAttr('type', 'audio/mpeg') ); 
			$item_node->appendChild($enclosure_node);

		$iteration_count++;
		$publish_date = date_add($publish_date,date_interval_create_from_date_string("1 day"));
	}

    $root->appendChild($channel_node);
	$dom->appendChild($root);

	Header('Content-type: text/xml');
	print $dom->saveXML();

}




function streamSong($file) {
	// Mostly taken from https://github.com/tuxxin/MP4Streaming

	if(!file_exists($file)) {
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
