<?php

namespace WP_Ops;

use WP_Query;
use WP_Post;
use WP_Error;
use WP_Ops;

class Media extends Post {

	protected $_attachment_id;

	protected $_parent_id;

	protected $_filepath;

	protected $_image_type;

	/**
	 * @var Meta
	 */
	protected $_meta;

	function __construct( $filepath, $args = array() ) {
		$this->_filepath = $filepath;
		$args = Util::parse_args($args, $default_args = array(
			'parent_id'     => null,
			'attachment_id' => null,
			'image_type'    => null,
			'meta'          => null,
		));
		foreach( array_keys( $default_args ) as $default_arg ) {
			if ( is_null( $args[ $default_arg ] ) ) {
				continue;
			}
			$this->{"_{$default_arg}"} = $args[ $default_arg ];
			unset( $args[ $default_arg ] );
		}
		parent::__construct( $this->_attachment_id, $args );
	}

	/**
	 * @return int|null
	 */
	function meta_id() {
		return isset( $this->_meta )
			? $this->_meta->meta_id()
			: null;
	}

	/**
	 * @return string|null
	 */
	function meta_key() {
		return isset( $this->_meta )
			? $this->_meta->meta_key()
			: null;
	}

	function filepath() {
		return $this->_filepath;
	}

	/**
	 * @return Meta
	 */
	function meta() {
		return $this->_meta;
	}

	function set_meta( $meta ) {
		$this->_meta = $meta;
	}

	function image_type() {
		return $this->_image_type;
	}

	function set_image_type( $image_type ) {
		$this->_image_type = $image_type;
	}

	function attachment_id() {
		return $this->_post_id;
	}

	function set_attachment_id( $attachment_id ) {
		$this->_post_id = $attachment_id;
	}

	function parent_id() {
		return $this->_parent_id;
	}

	function set_parent_id( $parent_id ) {
		$this->_parent_id = $parent_id;
	}

	/**
	 * Omits leading slash, just like _wp_...
	 * @return string
	 */
	function uploads_filepath() {
		static $start;
		if ( ! isset( $start ) ) {
			$start = strlen( WP_Ops::media()->uploads_basedir() );
		}
		return ltrim( substr( $this->_filepath, $start ), DIRECTORY_SEPARATOR );
	}

	/**
	 * @return string
	 */
	function url() {
		return WP_Ops::media()->uploads_baseurl() . "/{$this->uploads_filepath()}";
	}

	/**
	 * @return string
	 */
	function title() {
		do {
			$title = null;
			$post = $this->_maybe_parent_post();
			if ( empty( $post->post_title ) ) {
				break;
			}
			$title = $post->post_title;
		} while ( false );
		return $title;
	}


	/**
	 * @return string
	 */
	function content() {
		do {
			$content = null;
			$post = $this->_maybe_parent_post();
			if ( empty( $post->post_content ) ) {
				break;
			}
			$content = $post->post_content;
		} while ( false );
		return $content;
	}

	private function _maybe_parent_post() {
		$post_id = $this->_post_id;
		if ( is_null( $post_id ) ) {
			$post_id = $this->_parent_id;
		}
		return ! is_null( $post_id )
			? get_post( $post_id )
			: null;
	}

}