<?php

class ShaarliApi {
	
	/**
	 * Feeds list
	 * @route /feeds
	 */
	public function feeds( $arguments ) {

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
	public function latest( $arguments ) {

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
	public function top( $arguments ) {

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

				throw new ShaarliApiException('Invalid interval (?interval={' . implode('|', $intervals) . '})');				
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
	public function bestlinks( $arguments ) {

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
	public function search( $arguments ) {

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

			throw new ShaarliApiException('Need search term (?q=searchterm)');
		}
	}

	/**
	 * Search linked entries
	 * @route /discussion
	 * @args url={url}
	 */
	public function discussion( $arguments ) {

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

			throw new ShaarliApiException('Need url (?url=url)');
		}
	}

	/**
	 * Sync feeds list with other API nodes
	 * @param array nodes
	 */
	public function syncfeeds( $nodes ) {

		if( !empty($nodes) ) {

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
		}
	}

	/**
	 * Sync with OPML files
	 * @param array files
	 */
	public function syncWithOpmlFiles( $files ) {

		if( !empty($files) ) {

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
	}

	/**
	 * Push feed url list in database
	 * @param array urls
	 */
	public function addFeeds( $urls ) {

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
	 * Favicon service
	 * @route /getfavicon
	 * @args id={feed_id}
	 */
	public function getfavicon( $arguments ) {

		if( isset($arguments['id']) && !empty($arguments['id']) ) {

			if( is_numeric($arguments['id']) && $arguments['id'] > 0 ) {

				$favicon = FAVICON_DIRECTORY . $arguments['id'] . '.ico';

				// if( !file_exists($favicon) ) {
				// 	$favicon = __DIR__ . '/favicon.ico';
				// }

				return array('favicon' => $favicon);
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

			throw new ShaarliApiException('Need url (?url=url)');
		}		
	}
}

class ShaarliApiException extends Exception {}