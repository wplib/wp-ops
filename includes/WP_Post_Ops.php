<?php

namespace WP_Ops;

class Post {

	function create( $args = array() ) {
		global $wpdb;
		$new_id = 1 + $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );
		$args = wp_parse_args($args, array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => "Post #{$new_id}",
		));
		wp_insert_post( $args );
	}

}