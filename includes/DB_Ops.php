<?php

namespace WP_Ops;

class DB_Ops {

	/**
	 * @var Error
	 */
	private static $_last_error;

	/**
	 * @return Error
	 */
	static function last_error() {
		return self::$_last_error;
	}

	/**
	 * @param string $table
	 */
	static function truncate_table( $table ) {
		global $wpdb;
		$table = $wpdb->{$table};
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

}