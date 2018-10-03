<?php

namespace WP_Ops;

use WP_Query;
use WP_Post;
use WP_Error;

class Post {

	/**
	 * @param int|int[] $post_ids
	 * @param array $args
	 * @return array
	 */
	function delete( $post_ids, $args = array() ) {
		$args = wp_parse_args($args, array(
			'force' => false,
		));
		if ( ! is_array( $post_ids ) ) {
			$post_ids = array( $post_ids );
		}
		$result = array();
		foreach ( $post_ids as $post_id ) {
			$result[ $post_id ] = wp_delete_post( $post_id, $args[ 'force' ] );
		}
		return $result;
	}

	/**
	 * @param array $args
	 *
	 * @return WP_Post[]|object[]|int[]
	 */
	function list( $args = array() ) {
		$args = wp_parse_args($args, array(
			'post_type'   => 'post',
			'post_status' => 'publish',
		));
		$query = new WP_Query( $args );
		return $query->posts;
	}

	/**
	 * @param array $args
	 *
	 * @return int|WP_Error
	 */
	function create( $args = array() ) {
		global $wpdb;
		$new_id = 1 + $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );
		$args = wp_parse_args($args, array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => "Post #{$new_id}",
		));
		return wp_insert_post( $args );
	}

}