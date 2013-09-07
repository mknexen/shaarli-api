<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Initialize feed table
 */

// récupération de la liste des shaarlis
function get_shaarlis_list() {

    $shaarli_list=array();

    // Code from Oros, thanks
    $body =file_get_contents("https://ecirtam.net/shaarlirss/custom/people.opml");

    if(!empty($body)) {

	    $xml = @simplexml_load_string($body);

	    foreach ($xml->body->outline as $value) {

	        $a = $value->attributes();
	        $shaarli_list[] = $a->xmlUrl;
	    }
    }

    return $shaarli_list;       
}

$rows = get_shaarlis_list();

foreach( $rows as $row ) {

	$feed = Feed::create();
	$feed->url = $row;
	
	try {
		
		$feed->save();

	} catch (Exception $e) {
		
		// feed already exist
	}
}

echo 'Done!';