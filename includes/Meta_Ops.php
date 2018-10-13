<?php

namespace WP_Ops;

use WP_Query;
use WP_Post;
use WP_Error;
use WP_Ops;

class Meta_Ops {

	/**
	 * @var string
	 */
	private $_type;

	/**
	 * @var mixed
	 */
	private $_last_result;

	/**
	 * Meta_Ops constructor.
	 *
	 * @param string $type
	 */
	function __construct( $type ) {
		$this->_type = $type;
	}

	/**
	 * @param array $args
	 * @return WP_Post[]|WP_Error[]
	 */
	function delete_all( $args = array() ) {
		$this->delete_many( array(
			'post_ids'    => null,
			'user_ids'    => null,
			'term_ids'    => null,
			'comment_ids' => null,
		), $args );
	}

	/**
	 * @param array $query
	 * @param array $args
	 * @return WP_Post[]|WP_Error[]
	 */
	function delete_many( $query = array(), $args = array() ) {
		$args = wp_parse_args( $args, array(
			'truncate' => false,
		));
		if ( $args[ 'truncate' ] ) {
			DB_Ops::truncate_table( 'postmeta' );
		}
		$results = array();
		foreach( $this->list( $query ) as $meta ) {
			$results[ $meta->meta_id() ] = $this->delete( $meta );
		}
		return $this->_last_result = $results;
	}

	/**
	 * @param Meta|int $meta
	 *
	 * @return false|int
	 */

	function delete( $meta ) {
		global $wpdb;
		$meta = self::normalize_meta( $this->_type, $meta );
		$where_sql = $wpdb->prepare( "AND meta_id=%d", $meta->meta_id() );
		$sql = self::get_meta_sql( $this->_type, array(
			'delete_sql' => 'DELETE',
			'where_sql' => $where_sql,
		));
		return $this->_last_result = $wpdb->query( $sql );
	}

	/**
	 * @param array $args
	 * @return WP_Post[]|WP_Error[]
	 */
	function list( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args($args, array(
			'post_ids'    => null,
			'user_ids'    => null,
			'term_ids'    => null,
			'comment_ids' => null,
		));

		$where_sql = '';
		foreach( self::valid_types() as $valid_type ) {
			if ( empty( $args[ "{$valid_type}_id" ] ) ) {
				continue;
			}
			$object_ids = is_array( $args[ "{$valid_type}_ids" ] )
				? array_map( 'intval', $args[ "{$valid_type}_ids" ] )
				: array( intval( $args[ "{$valid_type}_ids" ] ) );

			$where_sql = ' AND {$valid_type}_id IN (' . implode( ',', $object_ids ) . ')';
			break;
		}
		$results = $wpdb->get_results( self::get_meta_sql(
			$this->_type,
			"where_sql={$where_sql}"
		));
		$meta = array();
		foreach( $results as $result ) {
			$meta[ $result->meta_id ] = self::make_new( $this->_type, $result );
		}
		return $_last_result = $meta;

	}

	static function sanitize_type( $type ) {
		return preg_match( '#^(post|user|term|comment)$#', strtolower( $type ) )
			? strtolower( $type )
			: null;
	}

	static function valid_types() {
		return explode( '|', 'post|user|term|comment' );
	}

	static function get_meta_sql( $type, $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array_merge(
			$prefix_args = array(
				'update_sql' => null,
				'insert_sql' => null,
				'replace_sql'=> null,
				'delete_sql' => null,
				'select_sql' => "SELECT meta_id, {$type}_id as object_id, meta_key",
			),
			array(
				'where_sql'  => null,
				'meta_value' => false,
			)
		));
		$table = "{$wpdb->prefix}{$type}meta";
		/**
		 * Use the first non-null object_id
		 */
		foreach( array_keys( $prefix_args ) as $prefix_arg ) {
			if ( empty( $args[ $prefix_arg ] ) ) {
				continue;
			}
			$prefix_sql = $args[ $prefix_arg ];
			if ( 'select_sql' && $args[ 'meta_value' ] ) {
				$prefix_sql .= ', meta_value';
			}
			break;
		}
		$sql = "{$prefix_sql} FROM {$table} WHERE 1=1 {$args[ 'where_sql' ]}";
		return $sql;
	}

	/**
	 * @param string $type
	 * @param object $meta_obj {
	 *      @type int meta_id
	 *      @type string meta_key
	 *      @type int object_id
	 * }
	 * @return Meta
	 */
	static function make_new( $type, $meta_obj ) {
		return new Meta( $type, $meta_obj->meta_key, array(
			'meta_id'   => $meta_obj->meta_id,
			'object_id' => $meta_obj->object_id,
		));
	}

	/**
	 * @param string $type
	 * @param Meta|int $meta
	 *
	 * @return Meta
	 */
	static function normalize_meta( $type, $meta ) {
		do {
			global $wpdb;
			if ( is_object( $meta ) ) {
				break;
			}
			if ( ! is_numeric( $meta ) ) {
				break;
			}
			$sql = self::get_meta_sql( $type, array(
				'where_sql' => $wpdb->prepare( 'AND meta_id=%d', $meta ),
			));
			if ( ! $result = $wpdb->get_results( $sql ) ) {
				$meta = new Meta( $type, 'unknown' );
				break;
			}
			$meta = self::make_new( $type, $result );
		} while ( false );
		return $meta;
	}

}