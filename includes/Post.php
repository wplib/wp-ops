<?php

namespace WP_Ops;

use WP_Query;
use WP_Post;
use WP_Error;
use WP_Ops;
use Clos;

class Post {

	protected $_post_id;

	protected $_wp_post;

	function __construct( $post_id, $args = array() ) {
		$this->_post_id = $post_id;
	}

	function ID() {
		return $this->_post_id;
	}

	function post_id() {
		return $this->_post_id;
	}

	function set_post_id( $post_id ) {
		$this->_post_id = $post_id;
	}

	function wp_post() {
		return get_post( $this->_post_id );
	}

	function slug() {
		return isset( $this->_wp_post->post_name )
			? $this->_wp_post->post_name
			: null;
	}

	function set_wp_post( $wp_post ) {
		switch ( gettype( $wp_post ) ) {
			case 'object':
				$wp_post = get_post( $wp_post );
				break;
			case 'array':
				$wp_post = get_post( $wp_post );
				break;
			case 'WP_Post':
				break;
			default:
				$message = "Parameter type not 'object', 'array' or 'WP_Post': %s";
				$value = ! is_scalar( $wp_post )
					? gettype( $wp_post )
					: $wp_post;
				trigger_error( sprintf( $message, $value ) );
		}
		$this->_wp_post = $wp_post;
	}

	/**
	 * @param string $meta_key
	 * @param mixed $meta_value
	 *
	 * @return Meta
	 */
	function update_meta( $meta_key, $meta_value ) {
		$post_meta = new PostMeta( $meta_key, array(
			'post_id'    => $this->_post_id,
			'meta_value' => $meta_value,
		));
		$post_meta->update();
		return $post_meta;
	}

	/**
	 * @param Media $media
	 * @param array $args
	 *
	 * @return Media
	 */
	function embed_media( $media, $args = array() ) {
		do {
			if ( empty( $this->_post_id ) ) {
				break;
			}
			if ( ! is_numeric( $this->_post_id ) ) {
				break;
			}
			if ( empty( $content = $this->wp_post()->post_content ) ) {
				break;
			}
			$new_content = preg_replace(
				'#\{\{image_url(:.+?)?\}\}#',
				$media->url(),
				$this->wp_post()->post_content,
				$limit = 1
			);
			if ( $new_content === $content ) {
				break;
			}
			global $wpdb;

			WP_Ops::post()->update_post( array(
				'ID'           => $this->_post_id,
				'post_content' => $new_content,
			));

			WP_Ops::logger()->log(
				"Embedded file [%s] in content for post %d.\n",
				$media->uploads_filepath(),
				$this->_post_id
			);

		} while ( false );
		return $media;
	}

	/**
	 * @param $media
	 * @param array $args
	 *
	 * @return Media
	 */
	function feature_media( $media, $args = array() ) {
		$args = wp_parse_args( $args );
		$args[ 'featured-image' ] = true;
		return $this->attach_media( $media, $args );
	}

	/**
	 * @param Media $media
	 * @param array $args
	 *
	 * @return Media
	 */
	function attach_media( $media, $args = array() ) {
		$media = Media_Ops::normalize_media( $media );
		$title = wp_strip_all_tags( $media->title() );
		$args = wp_parse_args( $args, array(
			'title'             => $title,
			'caption'           => $title,
			'alt'               => $title,
			'desc'              => $title,
		));
		$media->set_parent_id( $args[ 'post_id' ] = $this->_post_id );
		return WP_Ops::media()->import( $media, $args );
	}

	/**
	 * @param Media $media
	 * @param array $args
	 *
	 * @return Media
	 */
	function add_custom_media( $media, $args = array() )  {
		do {
			$args = wp_parse_args( $args, array(
				'meta_key'   => function () use ( $media ) { return $media->meta_key(); },
				'meta_value' => function () use ( $media ) { return $media->uploads_filepath(); },
			));
			$meta_key = Util::get_arg( $args[ 'meta_key' ] );
			$post_meta = new PostMeta( $meta_key, "post_id={$this->_post_id}" );
			$media->set_meta( $post_meta );
			$post_meta->update( Util::get_arg( $args[ 'meta_value' ] ) );
			WP_Ops::logger()->log(
				"Added meta key [%s] to post %d.\n",
				$meta_key,
				$this->_post_id
			);
		} while ( false );
		return $media;
	}

	/**
	 * @param string $type
	 * @param Media $media
	 * @param array $args
	 *
	 * @return mixed
	 */
	function associate_media( $type, $media, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'media_obj' => null,
		));
		$media->set_image_type( $type );
		$args = wp_parse_args( $args );
		$media->set_parent_id( $this->_post_id );
		switch ( $type ) {
			default:
				$result = null;
				break;

			case 'attached':
				$result = $this->attach_media( $media, $args );
				break;

			case 'embedded':
				$result = $this->embed_media( $media, $args );
				break;

			case 'thumbnail':
				$result = $this->feature_media( $media, $args );
				break;

			case 'custom':
				do {
					if ( ! isset( $args[ 'media_obj' ]->meta_key ) ) {
						$result = null;
						break;
					}
					$args[ 'meta_key' ] = $args[ 'media_obj' ]->meta_key;
					$result = $this->add_custom_media( $media, $args );
				} while ( false );
				break;
		}
		if ( method_exists( $result, 'set_image_type' ) ) {
			$result->set_image_type( $type );
		}
		return $result;
	}

}