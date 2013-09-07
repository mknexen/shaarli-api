<?php

class ApiController {

	/**
	 * Parsing request and execute the action
	 */
	public function route() {

		// $uri = trim($_SERVER['REQUEST_URI'], '/');

		$action = trim($_SERVER['PATH_INFO'], '/'); // secure?

		$method = $_SERVER['REQUEST_METHOD'];

		// Les actions disponible
		$actions = array(
			'feeds',
			'latest',
			'top',
			'search',
		);

		if( !in_array($action, $actions) ) {

			$this->error('Bad request (invalid action)');
		}

		// Les formats disponibles
		$formats = array(
			'json',
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

		exit();
	}

	/**
	 * Send error message
	 * @param string message
	 * @param int http_code
	 */
	protected function error( $message, $http_code ) {

		// TODO use good http code

		echo json_encode(array('error' => $message));
		exit();
	}

	/**
	 * Feeds list
	 * @route /feeds
	 */
	protected function feeds() {

		$feeds = Feed::factory()
					->select_expr('id, url, title')
					->where('enabled', 1)
					->findArray();

		return $feeds;
	}

	/**
	 * Latest entries
	 * @route /latest
	 */
	protected function latest() {

		$entries = Feed::factory()
					 ->select_expr('feeds.id AS feed_id, feeds.url AS feed_url, feeds.title AS feed_title, entries.id, date, permalink, entries.title, content')
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

			$entries = Entry::factory()
					->select_expr('id, date, permalink, title, content')
					// ->where_like('title', $term)
					->where_raw('(`title` LIKE ? OR `content` LIKE ?)', array($term, $term)) // security: possible injection?
					->order_by_desc('date')
					->findArray();

			return $entries;
		}
		else {

			$this->error('Need search term (?q=searchterm)');
		}
	}
}

require_once __DIR__ . '/bootstrap.php';

$controller = new ApiController();
$controller->route();