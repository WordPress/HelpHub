<?php
// Adapted from WP Social under the GPL - thanks to Alex King (https://github.com/crowdfavorite/wp-social)
/**
 * Semaphore Lock Management
 */
class UpdraftPlus_Semaphore {

	/**
	 * Initializes the semaphore object.
	 *
	 * @static
	 * @return UpdraftPlus_Semaphore
	 */
	public static function factory() {
		return new self;
	}

	/**
	 * @var bool
	 */
	protected $lock_broke = false;

	public $lock_name = 'lock';

	/**
	 * Attempts to start the lock. If the rename works, the lock is started.
	 *
	 * @return bool
	 */
	public function lock() {
		global $wpdb, $updraftplus;

		// Attempt to set the lock
		$affected = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_name = 'updraftplus_locked_".$this->lock_name."'
			 WHERE option_name = 'updraftplus_unlocked_".$this->lock_name."'
		");

		if ($affected == '0' and !$this->stuck_check()) {
			$updraftplus->log('Semaphore lock ('.$this->lock_name.') failed (line '.__LINE__.')');
			return false;
		}

		// Check to see if all processes are complete
		$affected = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_value = CAST(option_value AS UNSIGNED) + 1
			 WHERE option_name = 'updraftplus_semaphore_".$this->lock_name."'
			   AND option_value = '0'
		");
		if ($affected != '1') {
			if (!$this->stuck_check()) {
				$updraftplus->log('Semaphore lock ('.$this->lock_name.') failed (line '.__LINE__.')');
				return false;
			}

			// Reset the semaphore to 1
			$wpdb->query("
				UPDATE $wpdb->options
				   SET option_value = '1'
				 WHERE option_name = 'updraftplus_semaphore_".$this->lock_name."'
			");

			$updraftplus->log('Semaphore ('.$this->lock_name.') reset to 1');
		}

		// Set the lock time
		$wpdb->query($wpdb->prepare("
			UPDATE $wpdb->options
			   SET option_value = %s
			 WHERE option_name = 'updraftplus_last_lock_time_".$this->lock_name."'
		", current_time('mysql', 1)));
		$updraftplus->log('Set semaphore last lock ('.$this->lock_name.') time to '.current_time('mysql', 1));

		$updraftplus->log('Semaphore lock ('.$this->lock_name.') complete');
		return true;
	}

	/**
	 * Increment the semaphore.
	 *
	 * @param  array  $filters
	 * @return Social_Semaphore
	 */
	public function increment(array $filters = array()) {
		global $wpdb;

		if (count($filters)) {
			// Loop through all of the filters and increment the semaphore
			foreach ($filters as $priority) {
				for ($i = 0, $j = count($priority); $i < $j; ++$i) {
					$this->increment();
				}
			}
		}
		else {
			$wpdb->query("
				UPDATE $wpdb->options
				   SET option_value = CAST(option_value AS UNSIGNED) + 1
				 WHERE option_name = 'updraftplus_semaphore_".$this->lock_name."'
			");
			$updraftplus->log('Incremented the semaphore ('.$this->lock_name.') by 1');
		}

		return $this;
	}

	/**
	 * Decrements the semaphore.
	 *
	 * @return void
	 */
	public function decrement() {
		global $wpdb, $updraftplus;

		$wpdb->query("
			UPDATE $wpdb->options
			   SET option_value = CAST(option_value AS UNSIGNED) - 1
			 WHERE option_name = 'updraftplus_semaphore_".$this->lock_name."'
			   AND CAST(option_value AS UNSIGNED) > 0
		");
		$updraftplus->log('Decremented the semaphore ('.$this->lock_name.') by 1');
	}

	/**
	 * Unlocks the process.
	 *
	 * @return bool
	 */
	public function unlock() {
		global $wpdb, $updraftplus;

		// Decrement for the master process.
		$this->decrement();

		$result = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_name = 'updraftplus_unlocked_".$this->lock_name."'
			 WHERE option_name = 'updraftplus_locked_".$this->lock_name."'
		");

		if ($result == '1') {
			$updraftplus->log('Semaphore ('.$this->lock_name.') unlocked');
			return true;
		}

		$updraftplus->log('Semaphore ('.$this->lock_name.') still locked ('.$result.')');
		return false;
	}

	/**
	 * Attempts to jiggle the stuck lock loose.
	 *
	 * @return bool
	 */
	private function stuck_check() {
		global $wpdb, $updraftplus;

		// Check to see if we already broke the lock.
		if ($this->lock_broke) {
			return true;
		}

		$current_time = current_time('mysql', 1);
		$three_minutes_before = gmdate('Y-m-d H:i:s', time()-(defined('UPDRAFTPLUS_SEMAPHORE_LOCK_WAIT') ? UPDRAFTPLUS_SEMAPHORE_LOCK_WAIT : 180));

		$affected = $wpdb->query($wpdb->prepare("
			UPDATE $wpdb->options
			   SET option_value = %s
			 WHERE option_name = 'updraftplus_last_lock_time_".$this->lock_name."'
			   AND option_value <= %s
		", $current_time, $three_minutes_before));

		if ('1' == $affected) {
			$updraftplus->log('Semaphore ('.$this->lock_name.') was stuck, set lock time to '.$current_time);
			$this->lock_broke = true;
			return true;
		}

		return false;
	}

} // End UpdraftPlus_Semaphore
