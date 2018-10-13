<?php

namespace WP_Ops;

class PostMeta extends Meta {

	/**
	 * Meta constructor.
	 *
	 * @param int $meta_id
	 * @param array $args
	 */
	function __construct( $meta_id, $args = array() ) {
		parent::__construct( 'post', $meta_id, $args );
	}

}