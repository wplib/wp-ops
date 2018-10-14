<?php
namespace WP_Ops;

class Util {

	static function guid() {

		if ( function_exists( 'com_create_guid' ) === true ) {
			return trim( com_create_guid(), '{}' );
		}
		mt_srand( floor( microtime( true ) * 10000 ) );
		return sprintf(
			'%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
			mt_rand( 0, 65535 ),
			mt_rand( 0, 65535 ),
			mt_rand( 0, 65535 ),
			mt_rand( 16384, 20479 ),
			mt_rand( 32768, 49151 ),
			mt_rand( 0, 65535 ),
			mt_rand( 0, 65535 ),
			mt_rand( 0, 65535 )
		);
	}

	/**
	 * @param callable|mixed $target
	 * @param array $args
	 *
	 * @return mixed
	 */
	static function get_arg( $target, $args = array() ) {
		return is_callable( $target )
			? call_user_func_array( $target, $args )
			: $target;
	}

}