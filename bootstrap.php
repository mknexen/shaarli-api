<?php

/**
 * Shaarli REST API
 * @version 1.0 alpha
 * @authors:
 * 	nexen (nexen@dukgo.com, nexen@irc.freenode.net#debian, https://nexen.mkdir.fr/shaarli)
 */

require_once __DIR__ . '/vendor/idiorm.php';
require_once __DIR__ . '/vendor/paris.php';
require_once __DIR__ . '/class/models.php';


/**
 * Database configuration
 * doc: http://paris.readthedocs.org/en/latest/configuration.html#setup
 */

// sqlite
// ORM::configure('sqlite:./data/database.db');

// mysql
ORM::configure('mysql:host=localhost;dbname=shaarli-api');
ORM::configure('username', 'shaarli-api');
ORM::configure('password', 'shaarli-api');


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