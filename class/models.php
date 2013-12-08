<?php

/**
 * Models
 */
class ModelBase extends Model {

	/**
	 * Get model factory
	 */
	public static function factory() {
		return Model::factory(get_called_class());
	}

	/**
	 * Create new model
	 */
	public static function create() {
		return Model::factory(get_called_class())->create();
	}
}

/**
 * Feed Model
 */
class Feed extends ModelBase {

	public static $_table = 'feeds';

	/**
	 * Url setter
	 */
	public function setUrl( $url ) {

		$url = trim($url);
		// $url = str_replace('//', '/', $url);

		$this->url = $url;
	}

	/**
	 * Set feed as fetched
	 */
	public function fetched() {
		$this->set_expr('fetched_at', 'NOW()');
	}

	/**
	 * Check if feed already exist
	 * @return bool
	 */
	public function exists( $config = array() ) {

		$url = $this->url = trim($this->url);

		if( !empty($url) ) {

			$parts = parse_url( $url );

			if( $parts['scheme'] == 'http' ) {

				$http = $url;
				$https = $url = preg_replace("/^http:/", "https:", $url);
			}
			elseif( $parts['scheme'] == 'https' ) {

				$http = preg_replace("/^https:/", "http:", $url);
				$https = $url;
			}
			else {
				throw new Exception("Bad scheme", 1);
			}

			$count = self::factory()
				->where_raw('url = ? OR url = ? OR url LIKE ?', array($http, $https, '%' . $parts['host'] . '%')) // Retrict one shaarli per domain to avoid malformed urls => TODO: format url correctly
				->count();

			return $count > 0;
		}
		else {
			throw new Exception("empty url", 1);
		}
	}

	/**
	 * Find single feed by this id
	 * @param int id
	 */
	public static function findById( $id ) {

		return self::factory()->find_one($id);
	}

	/**
	 * Find single feed by this url
	 * @param string url
	 */
	public static function findByUrl( $url ) {

		return self::factory()
			->where_raw('link = ? OR url = ?', array($url, $url))
			->limit(1)
			->find_one();
	}
}

/**
 * Entry Model
 */
class Entry extends ModelBase {

	public static $_table = 'entries';

	/**
	 * Check if entry already exist
	 * @return bool
	 */
	public function exists() {

		$count = self::factory()
			->where('feed_id', $this->feed_id)
			->where('hash', $this->hash)
			->count();

		return $count > 0;
	}
}
