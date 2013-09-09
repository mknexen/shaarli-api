<?php

class CronController {

	/**
	 * Fetch all feeds
	 */
	public function fetchAll() {

		$feeds = Feed::factory()
					->where('enabled', 1)
					->findMany();

		if( $feeds != null ) {

			require_once __DIR__ . '/vendor/simplepie-simplepie-e9472a1/autoloader.php';
			
			foreach( $feeds as $feed ) {

				$this->fetch( $feed );
			}
		}
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

			$feed->title = '[ERROR HTTP CODE ' . $request['info']['http_code'] . ']';
			$feed->link = null;
			$feed->save();

			$this->verbose( 'Error Fetching: ' . $feed->url );

			return; // skip
		}
		if( empty($request['html']) ) {

			$feed->title = '[ERROR SERVER RETURN EMPTY CONTENT]';
			$feed->link = null;
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

			$feed->title = '[ERROR PARSING FEED]';
			$feed->link = null;
			$feed->save();

			$this->verbose( 'Error parsing: ' . $feed->url );

			return; // skip
		}

		$feed->title = $simplepie->get_title();
		$feed->link = $simplepie->get_link();

		$items = $simplepie->get_items();

		foreach($items as $item) {

			$entry = Entry::create();

			$entry->hash = $item->get_id(true);
			$entry->feed_id = $feed->id;

			if( !$entry->exists() ) {

				$entry->title = $item->get_title();
				$entry->permalink = $item->get_permalink();
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
			}
		}

		$feed->fetched();
		$feed->save();
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
				CURLOPT_ENCODING => 'gzip',
				CURLOPT_HTTPHEADER => array(
					'User-Agent: Shaarli-API (Mozilla Firefox)',
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Language: en-US,en;q=0.5',
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
if( !is_php_cli() ) die();

require_once __DIR__ . '/bootstrap.php';

$controller = new CronController();
$controller->fetchAll();
