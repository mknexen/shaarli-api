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
	 * Set feed as fetched
	 */
	public function fetched() {
		$this->set_expr('fetched_at', 'NOW()');
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
