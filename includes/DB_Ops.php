<?php

namespace WP_Ops;

class DB_Ops {

	/**
	 * @var Error
	 */
	private $_last_error;

	/**
	 * @return Error
	 */
	function last_error() {
		return $this->_last_error;
	}

	/**
	 * @param string $table
	 */
	function truncate_table( $table ) {
		global $wpdb;
		$table = $wpdb->{$table};
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * @param string $table
	 */
	function reset_auto_increment( $table ) {
		return self::set_auto_increment( $table, 1 );
	}

	/**
	 * @param string $table
	 * @param int $next_id
	 * @return bool Success (true) or failure (false)
	 */
	static function set_auto_increment( $table, $next_id ) {
		do {
			$success = false;
			global $wpdb;
			if ( ! isset( $wpdb->{$table} ) ) {
				self::$_last_error = new Error( sprintf( 'No property named [%s] in \$wpdb.', $table ) );
				break;
			}
			$table = $wpdb->{$table};
			if ( ! is_string( $table ) ) {
				self::$_last_error = new Error( sprintf( 'The \$wpdb property named [%s] is not a string.', $table ) );
				break;
			}
			$wpdb->query( $wpdb->prepare(
				"ALTER TABLE {$table} AUTO_INCREMENT=%d",
				$next_id
			));
			$success = true;
		} while ( false );
		return $success;
	}

}