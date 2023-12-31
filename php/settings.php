<?

// ------------------------------------------------------------------------
// START CONFIG

// Base URL of this service, include trailing slash
$baseurl = "https://yourdomain.com/";

// Date to start the playlist publishing from. 
// Setting it in the past ensures other (actual) podcast episodes appear first on the Light Phone
$publish_date = date_format( date_create('1980-01-01'), 'D, d M Y H:i:s O' ); 

// Location of playlists as mapped in docker container, including trailing slash
$playlist_root = '/playlists/';

// Full path for media mount, including trailing slash. 
// Be sure this matches what Jellyfin's docker and this docker have set.
$media_root = '/media/Music/';

// If you want your playlist randomized (every time Light Phone refreshes the podcast feed)
$randomize = true;

// END CONFIG
// ------------------------------------------------------------------------
