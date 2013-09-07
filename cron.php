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

		// Execute http request
		$xml = file_get_contents( $feed->url );

		// Parsing feed
		$simplepie = new SimplePie();
		$simplepie->set_cache_location( __DIR__ . '/cache/simplepie/' );

		$simplepie->set_raw_data( $xml );

		$simplepie->init();
		// $feed->handle_content_type();

		$feed->title = $simplepie->get_title();

		foreach($simplepie->get_items() as $item) {

			$entry = Entry::create();

			$entry->hash = $item->get_id(true);
			$entry->feed_id = $feed->id;

			if( !$entry->exists() ) {

				$entry->title = $item->get_title();
				$entry->permalink = $item->get_permalink();
				$entry->content = $item->get_content();
				$entry->date = $item->get_date('Y-m-d H:i:s');

				$entry->save();
			}
		}

		$feed->fetched();
		$feed->save();
	}

	/**
	 * Verbose
	 * @param string str
	 */
	public function verbose( $str ) {

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