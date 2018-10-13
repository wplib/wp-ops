<?php

namespace WP_Ops;

class DB_Ops {

	/**
	 * @param string $table
	 */
	static function truncate_table( $table ) {
		global $wpdb;
		$table = $wpdb->{$table};
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

}