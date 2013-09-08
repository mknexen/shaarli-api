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
	protected function fetch( Feed $feed ) {

		$this->verbose( 'Fetching: ' . $feed->url );

		// Execute http request
		$xml = $this->makeRequest( $feed->url );

		if( empty($xml) ) {
			return; // skip
		}

		// Parsing feed
		$simplepie = new SimplePie();
		// $simplepie->set_cache_location( __DIR__ . '/cache/simplepie/' );

		$simplepie->set_raw_data( $xml );

		$simplepie->init();
		// $feed->handle_content_type();

		$feed->title = $simplepie->get_title();
		$feed->link = $simplepie->get_link();

		foreach($simplepie->get_items() as $item) {

			$entry = Entry::create();

			$entry->hash = $item->get_id(true);
			$entry->feed_id = $feed->id;

			if( !$entry->exists() ) {

				$entry->title = $item->get_title();
				$entry->permalink = $item->get_permalink();
				$entry->content = $item->get_content();
				$entry->date = $item->get_date('Y-m-d H:i:s');

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
	 * Make http request and return html content
	 */
	protected function makeRequest( $url ) {

		$options = array(
		  'http' => array(
		    'method' => "GET",
		    'header' => "Accept-language: fr\r\n" .
		              "User-Agent: shaarli-api\r\n"
		  )
		);

		$context = stream_context_create($options);

		$content = @file_get_contents($url, false, $context);

		return $content;
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