<?php
/*
 * Name:         WP Ops
 * Type:         PHP Library
 * Version:      1.0
 * Dependencies: None
 * Authors:      The WPLib Team <team@wplib.org>
 *               Mike Schinkel <mike@newclarity.net>
 * Description:  WordPress Operations Library with semantics similar to WP CLI
 *
 * License:
 *
 *  Copyright 2018 NewClarity Consulting LLC
 *
 *     Licensed under the Apache License, Version 2.0 (the "License");
 *     you may not use this file except in compliance with the License.
 *     You may obtain a copy of the License at
 *
 *         http://www.apache.org/licenses/LICENSE-2.0
 *
 *     Unless required by applicable law or agreed to in writing, software
 *     distributed under the License is distributed on an "AS IS" BASIS,
 *     WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *     See the License for the specific language governing permissions and
 *     limitations under the License.
*/

/**
 * Class WP_Ops
 */
class WP_Ops {

	/**
	 * @var WP_Ops
	 */
	private static $_instance;

	/**
	 * @var string
	 */
	private static $_assets_dir;

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
	 * @param string $post_type
	 *
	 * @return \WP_Ops\Post_Ops
	 */
	static function post( $post_type = 'any' ) {
		$instance = self::instance();
		if ( ! isset( $instance->_post[ $post_type ] ) ) {
			$instance->_post[ $post_type ] = new \WP_Ops\Post_Ops( $post_type );
		}
		return $instance->_post[ $post_type ];
	}

	/**
	 * @return \WP_Ops\Media_Ops
	 */
	static function media() {
		return self::instance()->_media;
	}

	/**
	 * @param string $meta_type
	 *
	 * @return \WP_Ops\Meta_Ops
	 */
	static function meta( $meta_type ) {
		$instance = self::instance();
		if ( ! isset( $instance->_meta[ $meta_type ] ) ) {
			$instance->_meta[ $meta_type ] = new \WP_Ops\Meta_Ops( $meta_type );
		}
		return $instance->_meta[ $meta_type ];
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

	static function set_assets_dir( $assets_dir ) {
		self::$_assets_dir = $assets_dir;
	}

	static function assets_dir() {
		return self::$_assets_dir;
	}

}