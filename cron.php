<?php

use Favicon\Favicon;

class CronController {

	public $verbose = true;

	/**
	 * Check database
	 */
	public function check() {

		try {
			
			$this->countFeeds();

		} catch (Exception $e) {
			
			if( in_array($e->getCode(), array('42S02', 'HY000') )) {

				$this->verbose('Empty database! Creating tables...');

				if(DB_TYPE=="sqlite"){
					$scheme = __DIR__ . '/database/sqlite_schema.sql';
					if( file_exists($scheme) ) {
						$scheme = file_get_contents( $scheme );
						foreach(explode("-- next query", $scheme) as $query){
							// je ne sais pas pourquoi mais avec sqlite, 
							// il n'y a que 1 requête d'exécuté
							// donc je fais une boucle sur chaque requête
							ORM::for_table('')->raw_execute( $query );
						}
						$db = ORM::get_db();
						$db->exec('PRAGMA journal_mode=WAL;');
					}
				}elseif(DB_TYPE=="mysql"){
					$scheme = __DIR__ . '/database/mysql_schema.sql';
					if( file_exists($scheme) ) {
						$scheme = file_get_contents( $scheme );
						ORM::for_table('')->raw_execute( $scheme );
					}
				}else{
					die("Error in config.php. DB_TYPE is not sqlite or mysql");
				}
				
			}
		}

		try {
			
			$count = $this->countFeeds();

			if( $count == 0 ) {

				$this->syncFeeds();
			}

		} catch (Exception $e) {
			
			$this->verbose('Unable to create tables');
		}
	}

	/**
	 * Run
	 */
	public function run() {

		if( $this->countFeeds() == 0 ) { // Initialize feeds list
			$this->syncFeeds();
		}

		$this->fetchAll();
	}

	/**
	 * Fetch all feeds
	 */
	public function fetchAll() {
		if(DB_TYPE=="sqlite"){
			$feeds = Feed::factory()
					->where_raw("(fetched_at IS NULL OR fetched_at < strftime('%Y-%m-%d %H:%M:%S', 'now','-1 minute'))")
					->where('enabled', 1)
					->findMany();
		}elseif(DB_TYPE=="mysql"){
			$feeds = Feed::factory()
					->where_raw('(fetched_at IS NULL OR fetched_at < ADDDATE(NOW(), INTERVAL (fetch_interval * -1) MINUTE))')
					->where('enabled', 1)
					->findMany();
		}else{
			die("Error in config.php. DB_TYPE is not sqlite or mysql");
		}		

		if( $feeds != null ) {

			foreach( $feeds as &$feed ) {

				$this->fetch( $feed );
			}

			return true;
		}

		return false;
	}

	/**
	 * Fetch single feed
	 * @param Feed feed
	 */
	public function fetch( Feed $feed ) {

		$this->verbose( 'Fetching: ' . $feed->url );

		// Check HTTPS capability
		if( $feed->https == null ) {

			$this->checkHttpsCapability( $feed );
		}

		// Execute HTTP Request
		$request = $this->makeRequest( $feed->url );

		if( $request['info']['http_code'] != 200 ) {

			$feed->error = '[ERROR HTTP CODE ' . $request['info']['http_code'] . ']';
			$feed->fetch_interval = 60;
			$feed->fetched();
			$feed->save();

			$this->verbose( 'Error Fetching: ' . $feed->url );

			return; // skip
		}
		if( empty($request['html']) ) {

			$feed->error = '[ERROR SERVER RETURN EMPTY CONTENT]';
			$feed->fetch_interval = 60;
			$feed->fetched();
			$feed->save();

			$this->verbose( 'Error Fetching: ' . $feed->url );

			return; // skip
		}

		// Parsing feed
		$simplepie = new SimplePie();
		// $simplepie->set_cache_location( __DIR__ . '/cache/simplepie/' );

		$simplepie->set_raw_data( $request['html'] );

		$success = $simplepie->init();

		if( $success === false ) {

			$feed->error = '[ERROR PARSING FEED]';
			$feed->fetch_interval = 60;
			$feed->fetched();
			$feed->save();

			$this->verbose( 'Error parsing: ' . $feed->url );

			return; // skip
		}

		$feed->title = $simplepie->get_title();
		$feed->link = $simplepie->get_link();

		$items = $simplepie->get_items();

		$new_entries_counter = 0;

		foreach($items as $item) {

			$entry = Entry::create();

			$entry->hash = $item->get_id(true);
			$entry->feed_id = $feed->id;

			if( !$entry->exists() ) {

				$entry->title = $item->get_title();
				$entry->permalink = htmlspecialchars_decode($item->get_permalink());
				$entry->content = $item->get_content();
				$entry->date = $item->get_date('Y-m-d H:i:s');
				if( $entry->date == null ) $entry->date = date('Y-m-d H:i:s');

				$categories = $item->get_categories();

				if( !empty($categories) ) {

					$entry_categories = array();

					foreach ($categories as $category) {

						$entry_categories[] = $category->get_label();
					}

					if( !empty($categories) ) {
						$entry->categories = implode(',', $entry_categories);
					}
				}

				unset($categories, $entry_categories);

				$entry->save();

				$new_entries_counter++;
			}
		}

		// Activity detection
		if( $new_entries_counter > 0 ) {
			$feed->fetch_interval = 3;
		}
		else {
			if( ($feed->fetch_interval * 1.5 ) <= 20 ) {
				$feed->fetch_interval = round( $feed->fetch_interval * 1.5 );
			}
		}

		$feed->error = null;
		$feed->fetched();
		$feed->save();

		$this->getFavicon( $feed );
	}

	/**
	 * Check HTTPS capability
	 * @param Feed feed
	 */
	public function checkHttpsCapability( Feed $feed ) {

		$this->verbose( 'Checking HTTPS capability: ' . $feed->url );

		$url = preg_replace("/^http:/", "https:", $feed->url);

		$request = $this->makeRequest( $url );

		$https_capable = false;

		if( $request['info']['http_code'] == 200 ) {

			$simplepie = new SimplePie();
			$simplepie->set_raw_data( $request['html'] );
			$success = $simplepie->init();

			if( $success !== false ) {

				$https_capable = true;
			}
			else {
				// Capable but unable to parse feed, maybe shaarli only served on port 80
			}
		}

		if( $https_capable ) {

			$feed->https = 1;
			$feed->url = $url;
		}
		else {

			$feed->https = 0;
			$feed->url = preg_replace("/^https:/", "http:", $feed->url);			
		}

		$feed->save();

		unset($request);
	}

	/**
	 * Get feed favicon
	 */
	protected function getFavicon( &$feed ) {

		$favicon = FAVICON_DIRECTORY . $feed->id . '.ico';

		if( (!file_exists($favicon) || (time() - filemtime($favicon)) > FAVICON_CACHE_DURATION) && $feed->link != null ) {
			$favService = new Favicon();

			$this->verbose('Downloading favicon for link #'. $feed->id .': ' . $feed->link);
			
			if( $favUrl = $favService->get($feed->link) ) {
				$favRequest = $this->makeRequest($favUrl);

				if( $favRequest['info']['http_code'] == 200 && !empty($favRequest['html']) ) {

					file_put_contents($favicon, $favRequest['html']);
				}
			}
			
			if( !file_exists($favicon) || !filesize($favicon) ) {
			    copy( FAVICON_DIRECTORY . FAVICON_DEFAULT, $favicon );
			}
		}
	}

	/**
	 * Sync feeds lists
	 */
	public function syncFeeds() {

		$this->verbose('Syncing feeds list... (got ' . $this->countFeeds() . ' feeds)');

		$controller = new ApiController();
		$controller->syncfeeds();

		unset( $controller );

		$this->verbose('Feeds list synced (got ' . $this->countFeeds() . ' feeds)');
	}

	protected function countFeeds() {
		return Feed::factory()->count();
	}

	/**
	 * Make http request and return html content
	 */
	public function makeRequest( $url ) {

		if( function_exists('curl_init') ) {

			$ch = curl_init();

			$options = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => false,
				CURLOPT_AUTOREFERER => false,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS => 5,
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
				CURLOPT_ENCODING => 'gzip',
				CURLOPT_HTTPHEADER => array(
	                'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:50.0) Gecko/20100101 Firefox/50.0',
	                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
	                'Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3',
	                'Accept-Encoding: gzip, deflate',
	                'DNT: 1',
	                'Connection: keep-alive',
	                'Upgrade-Insecure-Requests: 1',
				),
			);

			curl_setopt_array($ch, $options);

			$html = curl_exec($ch);
			$error = curl_error($ch);
			$errno = curl_errno($ch);
			$info = curl_getinfo($ch);

			return compact('html', 'error', 'errno', 'info');
		}
		else {
			throw new Exception("php-curl is required", 1);
		}
	}

	/**
	 * Verbose
	 * @param string str
	 */
	protected function verbose( $str ) {

		if( $this->verbose === true )
			echo implode("\t", array(
				date('d/m/Y H:i:s'),
				$str,
				"\n"
			));
	}
}

function is_php_cli() {
	return function_exists('php_sapi_name') && php_sapi_name() === 'cli';
}
if( is_php_cli() ) {

	require __DIR__ . '/bootstrap.php';
	
	// Let's not break everything if new config isn't set
	if( !defined('FAVICON_DEFAULT') ) { define('FAVICON_DEFAULT', 'default.ico'); }
	if( !defined('FAVICON_CACHE_DURATION') ) { define('FAVICON_CACHE_DURATION', 3600*24*30); }

	if( isset($argv[1]) ) {

		if( $argv[1] == '--daemon'  ) { // daemon mode

			while(true) {

				$controller = new CronController();
				$controller->verbose = false;
				$success = $controller->fetchAll();
				unset($controller);

				if( !$success ) sleep(30);
			}
		}
	}
	else { // standard mode

		$controller = new CronController();
		$controller->check();
		$controller->run();
	}
}
else {

	// TODO webcron

	die();
}
