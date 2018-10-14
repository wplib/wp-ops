<?php

namespace WP_Ops;

use WP_Query;
use WP_Post;
use WP_Error;

abstract class Meta {

	/**
	 * @var string
	 */
	protected $_type;

	/**
	 * @var int
	 */
	protected $_meta_id;

	/**
	 * @var int
	 */
	protected $_object_id;

	/**
	 * @var string
	 */
	protected $_meta_key;

	/**
	 * @var mixed
	 */
	protected $_meta_value;

	/**
	 * @var bool
	 */
	protected $_exists;

	/**
	 * Meta Factory
	 *
	 * @param string $type
	 * @param string $meta_key
	 * @param array $args
	 */
	static function make_new( $type, $meta_key, $args ) {
		do {

			$meta = null;

			$args = wp_parse_args( $args, array(
			    'class_name' => __NAMESPACE__ . '\\' . ucfirst( $type ) . 'Meta',
			));

			if ( ! class_exists( $args[ 'class_name' ] ) ) {
				trigger_error( sprintf(
					'No class [%s] for meta type [%s] when calling %s().',
					$args[ 'class_name' ],
					$type,
					__METHOD__
				));
				break;
			}

			$class_name = $args[ 'class_name' ];

			$meta = new $class_name( $meta_key, $args );

		} while ( false );
		return $meta;
	}

	/**
	 * Meta constructor.
	 *
	 * @param string $type
	 * @param string $meta_key
	 * @param array $args
	 */
	function __construct( $type, $meta_key, $args = array() ) {

		$this->_type      = Meta_Ops::sanitize_type( $type );
		$this->_meta_key  = $meta_key;

		$args = wp_parse_args( $args, array(
			'meta_id'    => null,
			'object_id'  => null,
			'meta_value' => null,
		));

		$this->_meta_id    = Util::null_if_zero( $args[ 'meta_id' ], Util::AS_INT );
		$this->_object_id  = Util::null_if_zero( $args[ 'object_id' ], Util::AS_INT );

		$this->_meta_value = $args[ 'meta_value' ];

	}

	function meta_id() {
		return $this->_meta_id;
	}

	function set_meta_id( $meta_id ) {
		$this->_meta_id = $meta_id;
	}

	function meta_key() {
		return $this->_meta_key;
	}

	function set_meta_key( $meta_key ) {
		$this->_meta_key = $meta_key;
	}

	function meta_value() {
		return $this->_meta_value;
	}

	function set_meta_value( $meta_value ) {
		$this->_meta_value = $meta_value;
	}

	function set_object_id( $object_id ) {
		$this->_object_id = $object_id;
	}

	function exists() {
	}

	function get() {
		return get_metadata( $this->_type, $this->_object_id, $this->_meta_key, true );
	}

	function update( $value = null ) {
		if ( is_null( $value ) ) {
			$value = $this->_meta_value;
		} else {
			$this->_meta_value = $value;
		}
		/**
		 * Fires immediately before updating metadata of a specific type.
		 *
		 * The dynamic portion of the hook, `$meta_type`, refers to the meta
		 * object type (comment, post, or user).
		 *
		 * @since 2.9.0
		 *
		 * @param int    $meta_id    ID of the metadata entry to update.
		 * @param int    $object_id  Object ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 */
		$meta = $this;
		$count = 0;
		$hook = function( $meta_id ) use ( $meta, &$count ) {
			if ( 0 < $count ) {
				trigger_error( sprintf( '%s() does not currently support multiple value meta_keys.', __METHOD__ ) );
				die(1);
			}
			$meta->set_meta_id( $meta_id );
			$count++;
		};
		add_action( "added_{$this->_type}_meta", $hook );
		add_action( "update_{$this->_type}_meta", $hook );
		$result = update_metadata( $this->_type, $this->_object_id, $this->_meta_key, $value );
		remove_action( "added_{$this->_type}_meta", $hook );
		remove_action( "update_{$this->_type}_meta", $hook );
		$this->_exists = true;
		return $result;
	}


}