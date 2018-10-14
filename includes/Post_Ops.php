<?php

namespace WP_Ops;

use WP_Query;
use WP_Post;
use WP_Error;
use WP_Ops;

class Post_Ops {

	/**
	 * @var string
	 */
	private $_post_type = 'any';

	/**
	 * @var mixed
	 */
	private $_last_result;

	function __construct( $post_type = 'any' ) {
		$this->_post_type = $post_type;
	}

	/**
	 * @param int|int[] $post_ids
	 * @param array $args
	 * @return array
	 */
	function delete_all( $args = array() ) {
		return $this->delete_many( [], $args );
	}

	/**
	 * @param array $query
	 * @param array $args
	 * @return WP_Post[]|WP_Error[]
	 */
	function delete_many( $query = array(), $args = array() ) {
		$query = Util::parse_args( $query, array(
			'post_type'      => [ $this->_post_type, 'revision' ],
			'post_status'    => [ 'publish', 'trash', 'auto-draft', 'inherit' ],
			'posts_per_page' => - 1,
		));
		$args = Util::parse_args( $args, array(
			'truncate'  => false,
			'reset'     => false,
			'force'     => true,
		));
		$query[ 'fields' ] = 'ids';
		$results = array();
		foreach( $this->list( $query ) as $post ) {
			$results[ $post->post_id() ] = $this->delete( $post, $args );
		}
		if ( $args[ 'truncate' ] ) {
			DB_Ops::truncate_table( 'posts' );
		}
		if ( $args[ 'reset' ] ) {
			DB_Ops::reset_auto_increment( 'posts' );
		}
		return $this->_last_result = $results;
	}

	/**
	 * @param Post|int
	 * @param array $args
	 * @return array
	 */
	function delete( $post, $args = array() ) {
		$args = Util::parse_args($args, array(
			'force' => false,
		));
		if ( is_int( $post ) ) {
			$post = new Post( $post );
		}
		$post_id = $post->post_id();
		$post->set_wp_post( wp_delete_post( $post_id, $args[ 'force' ] ) );
		return $this->_last_result = $post;
	}

	/**
	 * @param array $args
	 *
	 * @return Post[]
	 */
	function list( $args = array() ) {
		$args = Util::parse_args( $args, array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		));
		$args[ 'fields' ] = 'ids';
		$query = new WP_Query( $args );
		$posts = array();
		foreach( $query->posts as $index => $post_id ) {
			$posts[ $index ] = new Post( $post_id );
		}
		return $this->_last_result = $posts;
	}

	/**
	 * Create multiple posts from an array
	 * 
	 * @example
	 *  [
	 *      [ 'filename',   'status',  'title' ],
	 *      [ 'racism',     'publish', 'Early African-American Schools Refuted White Supremacists’ View' ],
	 *      [ 'exhibit',    'publish', 'The High’s Latest Exhibit Reinterprets The 1968 Olympic Protest' ],
	 *      [ 'buford',     'publish', 'Loss Of Affordable Complexes Worries Buford Highway Community','' ],
	 *  ]    
	 *
	 * @param array $post_arr
	 * @param array $args
	 *
	 * @return Post[]|WP_Error[]
	 */
	function create_from( $post_arr, $args = array() ) {
		$args = Util::parse_args($args, array(
			'truncate' => false,
		));
		if ( $args[ 'truncate' ] ) {
			DB_Ops::truncate_table( 'posts' );
		}
		$assets_dir = WP_Ops::assets_dir();
		$post_objs = WP_Ops::transform_test_data( $post_arr );
		$posts = array();
		foreach( $post_objs as $post_obj ) {
			$filepath = "{$assets_dir}/{$post_obj->slug}.html";
			$post_content = is_file( $filepath )
				? file_get_contents( $filepath )
				: null;
			$posts[ $post_obj->slug ] = $this->create( array(
				'post_name'    => $post_obj->slug   ?? null,
				'post_title'   => $post_obj->title  ?? null,
				'post_type'    => $post_obj->type   ?? null,
				'post_status'  => $post_obj->status ?? null,
				'post_content' => $post_content,
			));
		}
		return $this->_last_result = $posts;
	}

	/**
	 * @param array $args
	 *
	 * @return Post|WP_Error
	 */
	function create( $args = array() ) {
		global $wpdb;

		$new_id = 1 + $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );

		$args = Util::parse_args($args, array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => "Post #{$new_id}",
			'post_content' => null,
		));

		$post_id = $this->insert_post( $args );

		$post = is_numeric( $post_id )
			? new Post( $post_id )
			: $post_id;

		return $this->_last_result = $post;
	}

	/**
	 * @param array[] $post_media
	 * @param Post[] $posts
	 * @param Media[] $media_objs
	 *
	 * @example $post_media: [
	 *      [ 'post_slug',  'media_path',           'type',         'meta_key'   ],
	 *      [ 'racism',     '2018/08/black.png',    'thumbnail',    ''           ],
	 *      [ 'exhibit',    '2018/08/white.png',    'attached' ,    ''           ],
	 *      [ 'buford',     '2018/08/red.png',      'embedded' ,    ''           ],
	 *      [ 'atl-mayor',  '2018/08/lime.png',     'custom'   ,    '_image_url' ],
	 * ]
	 *
	 */
	function associate_media_from( $post_media, $posts, $media_items ) {
		$results = array();
		$post_media = WP_Ops::transform_test_data( $post_media );
		foreach( $post_media as $media_obj ) {
			if ( ! isset( $posts[ $slug = $media_obj->post_slug ] ) ) {
				continue;
			}
			$media_path = preg_replace(
				'#^(.+)(-\d{2,4}x\d{2,4})(\.[a-z]{3,10})$#',
				'$1$3',
				$media_obj->media_path
			);
			if ( ! isset( $media_items[ $media_path ] ) ) {
				continue;
			}
			$post = $posts[ $slug ];
			$media = $media_items[ $media_path ];
			$type = $media_obj->type;
			$key = "{$slug}-{$type}-{$media_path}";
			$media = $post->associate_media( $type, $media, array(
				'media_obj' => $media_obj
			));
			$media->set_parent_id( $post->post_id() );
			$results[ $type ][ $key ] = $media;
		}
		return $results;
	}

	/**
	 * Return last result from any of the methods in this class.
	 *
	 * @return mixed
	 */
	function last_result() {
		return isset( $this->_last_result )
			? $this->_last_result
			: null;
	}

	/**
	 * @param array $args
	 *
	 * @return Post[]|WP_Error
	 */
	function find_by( $by, $lookup_value ) {
		global $wpdb;

		switch ( $by ) {
			case 'ID':
			case 'post_id':
				$by = 'ID';
			case 'guid':
				$sql = "SELECT ID FROM {$wpdb->posts} WHERE {$by}=%s";
				$post_ids = $wpdb->get_col( $wpdb->prepare( $sql, $lookup_value ) );
				break;
		}
		$posts = array();
		foreach( $post_ids as $post_id ) {
			$posts[ $post_id ] = new Post( $post_id );
		}
		return $this->_last_result = $posts;
	}

	/**
	 * Calls wp_insert_post() while ensuring {{image_url}} is not stripped out.
	 *
	 * @param $post_arr
	 */
	function insert_post( $post_arr ) {
		return $this->_insert_update_post( 'insert', $post_arr );
	}

	/**
	 * Calls wp_update_post() while ensuring {{image_url}} is not stripped out.
	 *
	 * @param $post_arr
	 */
	function update_post( $post_arr ) {
		return $this->_insert_update_post( 'update', $post_arr );
	}

	/**
	 * Calls wp_insert_post() while ensuring {{image_url}} is not stripped out.
	 *
	 * @param $post_arr
	 */
	private function _insert_update_post( $mode, $post_arr ) {
		$placeholder = null;
		if ( $post_arr[ 'post_content' ] ) {
			/**
			 * wp_kses() will strip out {{image_url}} and {{image_url:9999x999}}
			 * when used in the img src="" so create a placeholder with htts://
			 * and then a GUID to replace it temporarily
			 */
			$post_arr[ 'post_content' ] = str_replace(
				'{{image_url',
				$placeholder = 'https://' . Util::guid(),
				$post_arr[ 'post_content' ]
			);
		}
		add_action( 'wp_insert_post_data', $hook = function( $data ) use ( $placeholder ) {
			/**
			 * Now set the placeholder back to '{{image_url' before saving.
			 */
			$data[ 'post_content' ] = str_replace( $placeholder, '{{image_url', $data[ 'post_content' ] );
			return $data;
		});
		if ( 'insert' === $mode ) {
			$post_id = wp_insert_post( $post_arr );
		} else if ( 'update' === $mode ) {
			$post_id = wp_update_post( $post_arr );
		}
		remove_filter( 'wp_insert_post_data', $hook );
		return $post_id;
	}

}