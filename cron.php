<?php

class CronController {

	public $verbose = true;

	/**
	 * Check database
	 */
	public function check() {

		try {
			
			$this->countFeeds();

		} catch (Exception $e) {
			
			if( $e->getCode() == '42S02' ) {

				$this->verbose('Empty database! Creating tables...');

				$scheme = __DIR__ . '/database/mysql_schema.sql';

				if( file_exists($scheme) ) {

					$scheme = file_get_contents( $scheme );

					ORM::for_table('')->raw_execute( $scheme );					
				}
			};
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

		$feeds = Feed::factory()
					->where_raw('(fetched_at IS NULL OR fetched_at < ADDDATE(NOW(), INTERVAL (fetch_interval * -1) MINUTE))')
					->where('enabled', 1)
					->findMany();

		if( $feeds != null ) {

			require_once __DIR__ . '/vendor/simplepie-simplepie-e9472a1/autoloader.php';
			
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

		if( !file_exists($favicon) ) {

			if( $feed->link != null ) {

				$service = 'http://g.etfv.co/' . urlencode($feed->link); // it's google app engine service, fuck!

				$this->verbose('Downloading favicon: ' . $feed->link);

				$request = $this->makeRequest($service);

				if( $request['info']['http_code'] == 200 && !empty($request['html']) ) {

					file_put_contents($favicon, $request['html']);
				}
			}
		}
	}

	/**
	 * Sync feeds lists
	 */
	public function syncFeeds() {

		$this->verbose('Syncing feeds list... (got ' . $this->countFeeds() . ' feeds)');

		require_once __DIR__ . '/class/ApiController.php';

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
					'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:24.0) Gecko/20100101 Firefox/24.0',
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Language: fr,fr-fr;q=0.8,en-us;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate',
					'DNT: 1',
					'Connection: keep-alive',
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

	require_once __DIR__ . '/bootstrap.php';

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