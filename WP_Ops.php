<?php

class WP_Ops {

	/**
	 * @var WP_Ops
	 */
	private static $_instance;

	/**
	 * @var string
	 */
	private static $_files_dir;

	/**
	 * @var array
	 */
	private static $_errors = array();

	/**
	 * @var \WP_Ops\Post_Ops
	 */
	private $_post;

	/**
	 * @var \WP_Ops\Media_Ops
	 */
	private $_media;

	/**
	 * @var \WP_Ops\Meta_Ops[]
	 */
	private $_meta;

	/**
	 * @var \WP_Ops\Logger
	 */
	private $_logger;

	function __construct() {
		$this->_media = new WP_Ops\Media_Ops();
		$this->_post = new WP_Ops\Post_Ops();
		$this->_logger = new WP_Ops\Logger();
	}

	/**
	 * @param $message
	 */
	static function log( $message ) {
		self::logger()->log( $message );
	}

	/**
	 * @param $error
	 */
	static function add_error( $error ) {
		self::$_errors[] = $error;
	}

	/**
	 * @return \WP_Ops
	 */
	static function instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * @return \WP_Ops\Logger
	 */
	static function logger() {
		return self::instance()->_logger;
	}

	/**
	 * @return \WP_Ops\Post_Ops
	 */
	static function post() {
		return self::instance()->_post;
	}

	/**
	 * @return \WP_Ops\Media_Ops
	 */
	static function media() {
		return self::instance()->_media;
	}

	/**
	 * @return \WP_Ops\Meta_Ops
	 */
	static function meta( $type ) {
		$instance = self::instance();
		if ( ! isset( $instance->_meta[ $type ] ) ) {
			$instance->_meta[ $type ] = new \WP_Ops\Meta_Ops( $type );
		}
		return $instance->_meta[ $type ];
	}

	/**
	 * Transforms 2d array into array of objects
	 *
	 * Transforms an array of arrays where first sub-array (row) contains header names
	 * and remaining sub-arrays (rows) get transformed into objects with headers as properties
	 *
	 * @example:
	 *
	 *      [
	 *           [ 'foo', 'bar', 'baz' ],
	 *           [ 1, 2, 3 ],
	 *           [ 4, 5, 6 ],
	 *      ]
	 *
	 *      Gets transformed into:
	 *
	 *      [
	 *           (object)[ 'foo'=>1, 'bar'=>2, 'baz'=>3 ],
	 *           (object)[ 'foo'=>4, 'bar'=>5, 'baz'=>6 ],
	 *      ]
	 *
	 * @param array[] $data
	 *
	 * @return object[]
	 */
	static function transform_test_data( $data ) {
		$headers = array_shift( $data );
		foreach( $data as $row => $item ) {
			$object = array();
			foreach( $headers as $col => $header ) {
				$object[ $header ] = $item[ $col ];
			}
			$data[ $row ] = (object)$object;
		}
		return $data;
	}

	static function set_files_dir( $files_dir ) {
		self::$_files_dir = $files_dir;
	}

	static function files_dir() {
		return self::$_files_dir;
	}

	static function foreach( $array, $callback ) {
		foreach( $array as $index => $element ) {
			$array[ $index ] = call_user_func( $callback, $element, $index );
		}
		return $array;
	}

	/**
	 * @param callable|mixed $target
	 * @param array $args
	 *
	 * @return mixed
	 */
	static function get_arg( $target, $args = array() ) {
		return is_callable( $target )
			? call_user_func_array( $target, $args )
			: $target;
	}

}