<?php

abstract class AbstractApi {

	/**
	 * Get requested action
	 */
	public function getRequestAction() {

		if( isset($_SERVER['PATH_INFO']) ) {

			$action = trim($_SERVER['PATH_INFO'], '/'); // secure?
			return $action;
		}
	}

	/**
	 * Get requested method
	 */
	public function getRequestMethod() {
		return isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
	}
}

class ApiController extends AbstractApi {

	/**
	 * Parsing request and execute the action
	 */
	public function route() {

		// Les actions disponible
		$actions = array(
			'feeds',
			'latest',
			'top',
			'search',
			'discussion',
			'bestlinks',
			'syncfeeds',
			'ping',
		);

		$action = $this->getRequestAction();

		if( !in_array($action, $actions) ) {

			$this->error('Bad request (invalid action)');
		}

		// Les formats disponibles
		$formats = array(
			'json',
			'rss',
			'opml',
		);

		// Default format: json
		$format = (isset($_GET['format']) && in_array($_GET['format'], $formats)) ? $_GET['format']: 'json';

		$arguments = $_GET;

		// Execute the action
		$results = $this->$action( $arguments );

		// Render results
		if( $format == 'json' ) {

			header('Cache-Control: no-cache, must-revalidate');
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
			header('Content-type: application/json');

			// Petty JSON
			if( isset($_GET['pretty']) && $_GET['pretty'] == 1 ) {

				echo json_encode($results, JSON_PRETTY_PRINT);
			}
			else {

				echo json_encode($results);				
			}
		}
		// RSS
		elseif( $format == 'rss' ) {

			if( $action == 'latest' ) {

				$config = array(
					'title' => 'Shaarli API - Latest entries',
				);
			}
			elseif( $action == 'search' ) {

				$config = array(
					'title' => 'Shaarli API - Search feed',
				);				
			}
			elseif( $action == 'bestlinks' ) {

				$config = array(
					'title' => 'Shaarli API - Best links',
				);
			}
			else {
				$this->error('Bad request (RSS format unavailable for this action)');
				exit();
			}

			header('Content-type: application/rss+xml; charset=UTF-8');
			$this->outputRSS( $results, $config );
			exit();
		}
		// OPML
		elseif( $format == 'opml' ) {

			if( $action == 'feeds' ) {

				header('Content-type: application/x-xml; charset=UTF-8');
				header('Content-Disposition: attachment; filename="subscriptions.opml"');
				$this->outputOPML( $results );
				exit();
			}
			else {
				$this->error('Bad request (OPML format unavailable for this action)');
			}
		}

		exit();
	}

	/**
	 * Send error message
	 * @param string message
	 * @param int http_code
	 */
	protected function error( $message, $http_code = null ) {

		// TODO use good http code

		echo json_encode(array('error' => $message));
		exit();
	}

	/**
	 * Create OPML formated file
	 */
	protected function outputOPML( $feeds ) {

		// Code from: https://github.com/pfeff/opml.php
		$xml = new XMLWriter();
		$xml->openURI('php://output');

		$xml->startDocument('1.0', 'UTF-8');
		$xml->startElement('opml');
		$xml->writeAttribute('version', '2.0');

		// Header
		$xml->startElement('head');
		$xml->writeElement('title', 'Shaarli API OPML');
		$xml->writeElement('dateModified', date("D, d M Y H:i:s T"));
		$xml->endElement();

		// Body
		$xml->startElement('body');

			foreach ($feeds as $feed) {

			    $xml->startElement('outline');
			    $xml->writeAttribute('text', $feed['title']);
			    $xml->writeAttribute('htmlUrl', $feed['link']);
			    $xml->writeAttribute('xmlUrl', $feed['url']);
			    $xml->endElement();
			}

		$xml->endElement();

		$xml->endElement();
		$xml->endDocument();

		$xml->flush();
	}

	/**
	 * Output as RSS
	 * @param entries
	 * @param config
	 */
	protected function outputRSS( $entries, $config ) {

		// Inspired from http://www.phpntips.com/xmlwriter-2009-06/
		$xml = new XMLWriter();

		// Output directly to the user
		$xml->openURI('php://output');
		$xml->startDocument('1.0');
		$xml->setIndent(2);
		//rss
		$xml->startElement('rss');
		$xml->writeAttribute('version', '2.0');
		$xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');

		//channel
		$xml->startElement('channel');

		// title, desc, link, date
		$xml->writeElement('title', $config['title']);
		// $xml->writeElement('description', $config['description']);
		// $xml->writeElement('link', 'http://www.example.com/rss.hml');
		$xml->writeElement('pubDate', date('r'));

		if( !empty($entries) ) {

			foreach( $entries as $entry ) {

				// item
				$xml->startElement('item');
				$xml->writeElement('title', $entry['title']);
				$xml->writeElement('link', $entry['permalink']);
				$xml->startElement('description');
				$xml->writeCData($entry['content']);
				$xml->endElement();
				$xml->writeElement('pubDate', date('r', strtotime($entry['date'])));

				// category
				// $xml->startElement('category');
				// $xml->writeAttribute('domain', 'http://www.example.com/cat1.htm');
				// $xml->text('News');
				// $xml->endElement();

				// end item
				$xml->endElement();
			}	
		}

		// end channel
		$xml->endElement();

		// end rss
		$xml->endElement();

		// end doc
		$xml->endDocument();

		// flush
		$xml->flush();
	}

	/**
	 * Feeds list
	 * @route /feeds
	 */
	protected function feeds( $arguments ) {

		$feeds = Feed::factory()->select_expr('id, url, link, title');

		// Full list
		if( isset($arguments['full']) && $arguments['full'] == 1 ) {

			$feeds->select_expr('https, enabled, fetched_at, created_at');
		}
		else { // Active feeds

			$feeds->where_not_null('link');
			$feeds->where('enabled', 1);
		}

		return $feeds->findArray();
	}

	/**
	 * Latest entries
	 * @route /latest
	 */
	protected function latest( $arguments ) {

		$entries = Feed::factory()
					 ->select_expr('feeds.id AS feed_id, feeds.url AS feed_url, feeds.link AS feed_link, feeds.title AS feed_title')
					 ->select_expr('entries.id, date, permalink, entries.title, content, categories')
					->join('entries', array('entries.feed_id', '=', 'feeds.id'))
					->order_by_desc('date');

		// Count reshare links
		if( isset($arguments['reshare']) && $arguments['reshare'] == 1 ) {

			$entries->select_expr('(SELECT COUNT(1) FROM entries AS e2 WHERE entries.permalink=e2.permalink) AS shares');
		}

		// Limit
		if( isset($arguments['limit']) && $arguments['limit'] >= 5 && $arguments['limit'] <= 50 ) {
			$entries->limit( (int) $arguments['limit'] );
		}
		else {
			$entries->limit(50);
		}

		$entries = $entries->findArray();

		if( $entries != null ) {

			foreach( $entries as &$entry ) {

				$entry['feed']['id'] = $entry['feed_id'];
				$entry['feed']['url'] = $entry['feed_url'];				
				$entry['feed']['link'] = $entry['feed_link'];
				$entry['feed']['title'] = $entry['feed_title'];

				unset($entry['feed_id'], $entry['feed_url'], $entry['feed_link'], $entry['feed_title']);
			}
		}

		return $entries;
	}

	/**
	 * Top shared links
	 * @route /top
	 * @args interval={interval}
	 */
	protected function top( $arguments ) {

		$intervals = array(
			'12h',
			'24h',
			'48h',
			'1month',
			'3month',
			'alltime',
		);

		if( isset($arguments['interval']) ) {

			if( in_array($arguments['interval'], $intervals) ) {

				$entries = Entry::factory()
						->select_expr('permalink, entries.title, COUNT(1) AS count')
						->order_by_desc('count')
						->group_by('permalink')
						->having_gt('count', 1);

				switch ($arguments['interval']) {
					case '12h':					
						$entries->where_raw('date > ADDDATE(NOW(), INTERVAL -12 HOUR)');
						break;
					case '24h':					
						$entries->where_raw('date > ADDDATE(NOW(), INTERVAL -24 HOUR)');
						break;
					case '48h':
						$entries->where_raw('date > ADDDATE(NOW(), INTERVAL -48 HOUR)');
						break;
					case '1month':					
						$entries->where_raw('date > ADDDATE(NOW(), INTERVAL -1 MONTH)');
						break;
					case '3month':					
						$entries->where_raw('date > ADDDATE(NOW(), INTERVAL -1 MONTH)');
						break;
				}

				return $entries->findArray();				
			}
			else {

				$this->error('Invalid interval (?interval={' . implode('|', $intervals) . '})');
			}
		}
		elseif( isset($arguments['date']) && !empty($arguments['date'])) {

			// TODO parse date
			$date = date('Y-m-d', strtotime('-1 days'));

			$results = array();

			$entries = Entry::factory()
				->select_expr('permalink, entries.title, COUNT(1) AS count')
				->where_raw('DATE(entries.date)=?', array($date))
				->order_by_desc('count')
				->group_by('permalink')
				->having_gt('count', 1);

			$entries = $entries->findArray();

			$results['date'] = $date;
			$results['entries'] = $entries;

			return $results;
		}
	}

	/**
	 * Best reshared links
	 */
	protected function bestlinks( $arguments ) {

		$entries = Feed::factory()
				->select_expr('feeds.id AS feed_id, feeds.url AS feed_url, feeds.link AS feed_link, feeds.title AS feed_title')
				->select_expr('MIN(entries.date) AS date, permalink, entries.title, content, COUNT(1) AS count')
				->join('entries', array('entries.feed_id', '=', 'feeds.id'))
				->order_by_expr('MIN(entries.date) DESC')
				->group_by('permalink')
				->having_raw('count > 1'); // TODO group order wtf

		$entries = $entries->findArray();

		if( $entries != null ) {

			foreach( $entries as &$entry ) {

				$entry['feed']['id'] = $entry['feed_id'];
				$entry['feed']['url'] = $entry['feed_url'];
				$entry['feed']['link'] = $entry['feed_link'];
				$entry['feed']['title'] = $entry['feed_title'];

				unset($entry['feed_id'], $entry['feed_url'], $entry['feed_link'], $entry['feed_title']);
			}
		}

		return $entries;
	}

	/**
	 * Search entries
	 * @route /search
	 * @args q={searchterm}
	 */
	protected function search( $arguments ) {

		if( isset($arguments['q']) && !empty($arguments['q']) ) {

			$term = $arguments['q'];

			// Advanced Search
			$adv_search = null;

			if( preg_match('/^title\:/i', $term) ) {

				$term = preg_replace('/^title\:/', '', $term);
				$adv_search = 'title';
			}

			$term = '%' . $term . '%';

			$entries = Feed::factory()
					->select_expr('feeds.id AS feed_id, feeds.url AS feed_url, feeds.link AS feed_link, feeds.title AS feed_title')
					->select_expr('entries.id, date, permalink, entries.title, content, categories')
					->join('entries', array('entries.feed_id', '=', 'feeds.id'))
					->order_by_desc('date');

			if( $adv_search == 'title' ) { // Search in title only

				$entries->where_like('entries.title', $term);
			}
			else {

				$entries->where_raw('(entries.title LIKE ? OR entries.content LIKE ?)', array($term, $term)); // security: possible injection?
			}

			$entries = $entries->findArray();

			if( $entries != null ) {

				foreach( $entries as &$entry ) {

					$entry['feed']['id'] = $entry['feed_id'];
					$entry['feed']['url'] = $entry['feed_url'];
					$entry['feed']['link'] = $entry['feed_link'];
					$entry['feed']['title'] = $entry['feed_title'];

					unset($entry['feed_id'], $entry['feed_url'], $entry['feed_link'], $entry['feed_title']);
				}
			}

			return $entries;
		}
		else {

			$this->error('Need search term (?q=searchterm)');
		}
	}

	/**
	 * Search linked entries
	 * @route /discussion
	 * @args url={url}
	 */
	protected function discussion( $arguments ) {

		if( isset($arguments['url']) && !empty($arguments['url']) ) {

			$search_method = 'strict';

			$url = trim($arguments['url']);

			$entries = Feed::factory()
					->select_expr('feeds.id AS feed_id, feeds.url AS feed_url, feeds.link AS feed_link, feeds.title AS feed_title')
					->select_expr('entries.id, date, permalink, entries.title, content, categories')
					->join('entries', array('entries.feed_id', '=', 'feeds.id'))
					->order_by_desc('date');

			if( $search_method == 'strict' ) {

				$entries->where('entries.permalink', $url);
			}
			else if( $search_method == 'large' ) {

				// TODO: à réfléchir...
				$url = trim($url, '/') . '%';
				$entries->where_like('entries.permalink', $url);
			}

			$entries = $entries->findArray();

			if( $entries != null ) {

				foreach( $entries as &$entry ) {

					$entry['feed']['id'] = $entry['feed_id'];
					$entry['feed']['url'] = $entry['feed_url'];
					$entry['feed']['link'] = $entry['feed_link'];
					$entry['feed']['title'] = $entry['feed_title'];

					unset($entry['feed_id'], $entry['feed_url'], $entry['feed_link'], $entry['feed_title']);
				}
			}

			return $entries;
		}
		else {

			$this->error('Need url (?url=url)');
		}
	}

	/**
	 * Sync feeds list with other API nodes
	 */
	protected function syncfeeds() {

		// Shaarli API Nodes list
		$nodes = array(
			'https://nexen.mkdir.fr/shaarli-api/feeds',
		);

		foreach( $nodes as $node ) {

			$content = file_get_contents($node);
			$rows = json_decode($content);

			if( !empty($rows) ) {

				$urls = array();

				foreach( $rows as $row ) {

					if( isset($row->url) && !empty($row->url) ) {

						$urls[] = $row->url;
					}
				}

				if( !empty($urls) ) {

					$this->addFeeds( $urls );
				}
			}
		}

		$this->syncWithOpmlFiles();

		return array('success' => 1);
	}

	/**
	 * Sync with OPML files
	 */
	public function syncWithOpmlFiles() {

		$files = array(
			'https://ecirtam.net/shaarlirss/custom/people.opml', // thanks to Oros
			'https://shaarli.fr/opml.php?mod=opml', // thanks to shaarli.fr
		);

		foreach( $files as $file ) {

		    $body = file_get_contents($file);	

		    if( !empty($body) ) {

		    	$urls = array();

		    	// Code from Oros, thanks!
			    $xml = @simplexml_load_string($body);

			    foreach ($xml->body->outline as $value) {

			        $attributes = $value->attributes();

			        $urls[] = $attributes->xmlUrl;
			    }

		    	if( !empty($urls) ) {

					$this->addFeeds( $urls );
				}
		    }	
		}		
	}

	/**
	 * Push feed url list in database
	 * @param array urls
	 */
	private function addFeeds( $urls ) {

		if( !empty($urls) ) {

			$urls = array_unique($urls);

			foreach( $urls as $url ) {

				$feed = Feed::create();
				$feed->url = $url;

				if( !$feed->exists() ) {

					$feed->save();
				}
			}
		}
	}

	/**
	 * Ping service
	 * @route /ping
	 * @args url={url}
	 */
	public function ping( $arguments ) {

		if( isset($arguments['url']) && !empty($arguments['url']) ) {

			$json = array( 'success' => 0 );

			$feed = Feed::findByUrl( $arguments['url'] );

			if( $feed != null ) {

				$feed->fetched_at = null;
				$feed->save();

				$json['success'] = 1;	
			}

			return $json;
		}
		else {

			$this->error('Need url (?url=url)');
		}		
	}
}

require_once __DIR__ . '/bootstrap.php';

$controller = new ApiController();
$controller->route();
