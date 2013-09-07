<?php

class ApiController {

	/**
	 * Parsing request
	 */
	public function route() {

		// TODO
		// Need good regex to parse request uri

		$action = 'feeds';
		$format = 'json';
		$arguments = array();

		$data = $this->$action();

		if( $format == 'json' ) {

			header('Cache-Control: no-cache, must-revalidate');
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
			header('Content-type: application/json');

			echo json_encode($data);
		}
	}

	/**
	 * Feeds list
	 * @route /feeds
	 */
	public function feeds() {

		$feeds = Feed::factory()
					->select_expr('id, url, title')
					->where('enabled', 1)
					->findArray();

		return $feeds;
	}

	/**
	 * Lastest entries
	 * @route /lastest
	 */
	public function lastest() {

		$entries = Entry::factory()
					->select_expr('id, date, permalink, title, content')
					->order_by_desc('date')
					->limit(50)
					->findArray();

		return $entries;
	}

	/**
	 * Top shared links
	 * @route /top
	 * @args interval={interval}
	 */
	public function top() {

		$intervals = array(
			'12h',
			'24h',
			'1month',
			'3month',
			'alltime',
		);

		// TODO

		return array();
	}

	/**
	 * Search entries
	 * @route /search
	 * @args q={searchterm}
	 */
	public function search() {

		// TODO

		return array();
	}
}

require_once __DIR__ . '/bootstrap.php';

$controller = new ApiController();
$controller->route();