<?php

namespace WP_Ops;

class PostMeta extends Meta {

	/**
	 * Meta constructor.
	 *
	 * @param int $meta_id
	 * @param array|string $args
	 */
	function __construct( $meta_id, $args = array() ) {
		$args = wp_parse_args( $args );
		if ( isset( $args[ 'post_id' ] ) ) {
			$args[ 'object_id' ] = intval( $args[ 'post_id' ] );
			unset( $args[ 'post_id' ] );
		}
		parent::__construct( 'post', $meta_id, $args );
	}

	function post_id() {
		return $this->_object_id;
	}

	function set_post_id( $post_id ) {
		$this->_object_id = $post_id;
	}

}