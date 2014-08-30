<?php

/**
 * Shaarli REST API
 */

$configFile = __DIR__.'/config.php';

if( !file_exists('config.php') )
	exit('Please setup your config.php');

require $configFile;

require __DIR__.'/vendor/autoload.php';

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
