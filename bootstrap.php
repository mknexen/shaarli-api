<?php

/**
 * Shaarli REST API
 */

$configFile = __DIR__.'/config.php';

if( !file_exists('config.php') )
	exit('Please setup your config.php');

require_once __DIR__ . '/vendor/idiorm.php';
require_once __DIR__ . '/vendor/paris.php';
require_once __DIR__ . '/class/models.php';
require_once $configFile;


/**
 * Database configuration
 * doc: http://paris.readthedocs.org/en/latest/configuration.html#setup
 */

// sqlite
// ORM::configure('sqlite:./data/database.db');

// mysql
ORM::configure('mysql:host='. DB_HOST .';dbname='. DB_NAME);
ORM::configure('username', DB_USER);
ORM::configure('password', DB_PASS);


/**
 * Sync configuration
 */

// shaarli-api nodes list
function shaarli_api_nodes() {
	return array(
		// Nexen
		'https://nexen.mkdir.fr/shaarli-api/feeds',
		// Porneia (http://porneia.free.fr/pub/bazaar/shaarli_online.html)
		// 'https://nexen.mkdir.fr/dropbox/porneia.json',
	);
}
// OPML files
function shaarli_opml_files() {
	return array(
		// Oros OPML
		'https://ecirtam.net/shaarlirss/custom/people.opml', 
		// shaarli.fr OPML
		'https://shaarli.fr/opml.php?mod=opml',
	);
}

/**
 * Other consts
 */

// Favicon directory
define('FAVICON_DIRECTORY', __DIR__ . '/favicon/');