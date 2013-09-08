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
			'syncfeeds',
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
		elseif( $format == 'rss' ) {

			// TODO RSS format
			
			$this->error('Unimplemented');
		}
		elseif( $format == 'ompl' ) {

			if( $action == 'feeds' ) {

				// TODO OPML format

				$this->error('Unimplemented');
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
	 * Feeds list
	 * @route /feeds
	 */
	protected function feeds( $arguments ) {

		$feeds = Feed::factory()->select_expr('id, url, link, title');

		// Full list
		if( isset($arguments['full']) && $arguments['full'] == 1 ) {

			$feeds->select_expr('fetched_at, enabled');
		}
		else { // Active feeds

			$feeds->where_not_null('title');
			$feeds->where_raw('title != \'\'');
			$feeds->where('enabled', 1);
		}

		return $feeds->findArray();
	}

	/**
	 * Latest entries
	 * @route /latest
	 */
	protected function latest() {

		$entries = Feed::factory()
					 ->select_expr('feeds.id AS feed_id, feeds.url AS feed_url, feeds.title AS feed_title, entries.id, date, permalink, entries.title, content, categories')
					->join('entries', array('entries.feed_id', '=', 'feeds.id'))
					->order_by_desc('date')
					->limit(50)
					->findArray();

		if( $entries != null ) {

			foreach( $entries as &$entry ) {

				$entry['feed']['id'] = $entry['feed_id'];
				$entry['feed']['url'] = $entry['feed_url'];
				$entry['feed']['title'] = $entry['feed_title'];

				unset($entry['feed_id'], $entry['feed_url'], $entry['feed_title']);
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

		if( isset($arguments['interval']) && in_array($arguments['interval'], $intervals)) {

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

	/**
	 * Search entries
	 * @route /search
	 * @args q={searchterm}
	 */
	protected function search( $arguments ) {

		if( isset($arguments['q']) && !empty($arguments['q']) ) {

			$term = '%' . $arguments['q'] . '%';

			$entries = Feed::factory()
					->select_expr('feeds.id AS feed_id, feeds.url AS feed_url, feeds.link AS feed_link, feeds.title AS feed_title')
					->select_expr('entries.id, date, permalink, entries.title, content, categories')
					->join('entries', array('entries.feed_id', '=', 'feeds.id'))
					// ->where_like('title', $term)
					->where_raw('(entries.title LIKE ? OR entries.content LIKE ?)', array($term, $term)) // security: possible injection?
					->order_by_desc('date')
					->findArray();

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
	 * Sync feeds list with other API nodes
	 */
	protected function syncfeeds() {

		// Shaarli API Nodes list
		$nodes = array(
			'http://nexen.mkdir.fr/shaarli-api/feeds',
		);

		foreach( $nodes as $node ) {

			$content = file_get_contents($node);
			$rows = json_decode($content);

			if( !empty($rows) ) {

				foreach( $rows as $row ) {

					if( isset($row->url) && !empty($row->url) ) {

						$feed = Feed::create();
						$feed->url = $row->url;

						try {	

							$feed->save();

						} catch (Exception $e) {
							
							// feed already exist
						}
					}
				}
			}
		}

		$this->syncWithOrosOpml();

		return array('success' => 1);
	}

	/**
	 * Sync with Oros OPML file (thanks bro)
	 */
	private function syncWithOrosOpml() {

		// récupération de la liste des shaarlis
		function get_shaarlis_list() {

		    $shaarli_list = array();

		    // Code from Oros, another thanks!
		    $body = file_get_contents("https://ecirtam.net/shaarlirss/custom/people.opml");

		    if(!empty($body)) {

			    $xml = @simplexml_load_string($body);

			    foreach ($xml->body->outline as $value) {

			        $attributes = $value->attributes();
			        $shaarli_list[] = $attributes->xmlUrl;
			    }
		    }

		    return $shaarli_list;       
		}

		$urls = get_shaarlis_list();

		foreach( $urls as $url ) {

			$feed = Feed::create();
			$feed->url = $url;

			try {	

				$feed->save();

			} catch (Exception $e) {
				
				// feed already exist
			}
		}
	}
}

require_once __DIR__ . '/bootstrap.php';

$controller = new ApiController();
$controller->route();