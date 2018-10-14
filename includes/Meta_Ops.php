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
		return $this->delete_many( array(
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
		$args = Util::parse_args( $args, array(
			'truncate'    => false,
			'reset'       => false,
		));
		/**
		 * When 'post_exists' is false then delete meta
		 * only for posts that do NOT exist.
		 */
		$args[ 'post_exists' ] = false;
		$results = array();
		foreach( $this->list( $query, $args ) as $meta ) {
			$results[ $meta->meta_id() ] = $this->delete( $meta );
		}
		if ( $args[ 'truncate' ] ) {
			DB_Ops::truncate_table( 'postmeta' );
		}
		if ( $args[ 'reset' ] ) {
			DB_Ops::reset_auto_increment( 'postmeta' );
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
			'where_sql'  => $where_sql,
		));
		return $this->_last_result = $wpdb->query( $sql );
	}

	/**
	 * @param array $query
	 * @param array $args {
	 *      @type bool $post_exists When true-ish then only meta for posts that DO exist.
	 *                              When false-ish then only meta for posts that do NOT exist.
	 *                              When null then all meta.
	 * }
	 * @return WP_Post[]|WP_Error[]
	 */
	function list( $query = array(), $args = array() ) {
		global $wpdb;
		$query = Util::parse_args( $query, array(
			'post_ids'    => null,
			'user_ids'    => null,
			'term_ids'    => null,
			'comment_ids' => null,
		));
		$args = Util::parse_args( $args, array(
			'post_exists' => null,
		));

		$where_sql = '';
		foreach( self::valid_types() as $valid_type ) {
			if ( empty( $query[ "{$valid_type}_id" ] ) ) {
				continue;
			}
			$object_ids = is_array( $query[ "{$valid_type}_ids" ] )
				? array_map( 'intval', $query[ "{$valid_type}_ids" ] )
				: array( intval( $query[ "{$valid_type}_ids" ] ) );

			$where_sql = ' AND {$valid_type}_id IN (' . implode( ',', $object_ids ) . ')';
			if ( ! is_null( $args[ 'post_exists' ] ) {
				$table = "{$valid_type}s";
				$table = $wpdb->{$table};
				$where_sql .= $args[ 'post_exists' ]
					? ' AND (post_id IN (SELECT ID FROM {$table}))'
					: ' AND (post_id NOT IN (SELECT ID FROM {$table}))';
			}
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

	/**
	 * @param string $meta_type
	 *
	 * @return null|string
	 */
	static function sanitize_type( $meta_type ) {
		return preg_match( '#^(post|user|term|comment)$#', strtolower( $meta_type ) )
			? strtolower( $meta_type )
			: null;
	}

	/**
	 * @return array
	 */
	static function valid_types() {
		return explode( '|', 'post|user|term|comment' );
	}

	/**
	 * @param $meta_type
	 * @param array $args
	 *
	 * @return string
	 */
	static function get_meta_sql( $meta_type, $args = array() ) {
		global $wpdb;
		$args = Util::parse_args( $args, array_merge(
			$prefix_args = array(
				'update_sql' => null,
				'insert_sql' => null,
				'replace_sql'=> null,
				'delete_sql' => null,
				'select_sql' => "SELECT meta_id, {$meta_type}_id as object_id, meta_key",
			),
			array(
				'where_sql'  => null,
				'meta_value' => false,
			)
		));
		$table = "{$wpdb->prefix}{$meta_type}meta";
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
		return Meta::make_new( $type, $meta_obj->meta_key, array(
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
				$meta = Meta::make_new( $type, 'unknown' );
				break;
			}
			$meta = self::make_new( $type, $result );
		} while ( false );
		return $meta;
	}

}