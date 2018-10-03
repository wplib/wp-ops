<?php

class WP_Ops {

	/**
	 * @var WP_Ops
	 */
	private static $_instance;

	var $post;

	static function instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	function __construct() {
		$this->post = new WP_Ops\Post();
	}

	/**
	 * @return \WP_Ops\Post
	 */
	static function post() {
		return self::$_instance->post;
	}

}