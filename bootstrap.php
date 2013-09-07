<?php

/**
 * Shaarli REST API
 * @version devel
 * @authors:
 * 	nexen (nexen@dukgo.com, nexen@irc.freenode.net, http://nexen.mkdir.fr/shaarli)
 */

require_once __DIR__ . '/vendor/idiorm.php';
require_once __DIR__ . '/vendor/paris.php';
require_once __DIR__ . '/models/models.php';

/**
 * Database configuration
 * doc: http://paris.readthedocs.org/en/latest/configuration.html#setup
 */

// sqlite
// ORM::configure('sqlite:./data/database.db');

// mysql
ORM::configure('mysql:host=localhost;dbname=shaarli-api');
ORM::configure('username', 'root');
ORM::configure('password', '');
