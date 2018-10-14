<?php
namespace WP_Ops;

class Util {

	const AS_MIXED = 0;
	const AS_INT = 1;

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

	/**
	 * @param mixed $value
	 * @param int $as
	 *
	 * @return mixed|null
	 */
	static function null_if_zero( $value, $as = self::AS_MIXED ) {
		return 0 !== $value
			? ( self::AS_INT === $as ? intval( $value ) : $value )
			: null;
	}

	/**
	 * @param array $args
	 * @param null $defaults
	 *
	 * @return array|mixed
	 */
	static function parse_args( $args, $defaults = null ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$args =& $args;
		} else {
			parse_str( $args, $args );
			if ( get_magic_quotes_gpc() ) {
				$args = self::map_deep( $args, function( $value ) {
					return is_string( $value ) ? stripslashes( $value ) : $value;
				});
			}
		}
		if ( is_array( $defaults ) ) {
			return array_merge( $defaults, $args );
		}
		return $args;
	}

	/**
	 * @param array $value
	 * @param callable $callback
	 *
	 * @return array
	 */
	static function map_deep( $value, $callback ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $index => $item ) {
				$value[ $index ] = map_deep( $item, $callback );
			}
		} elseif ( is_object( $value ) ) {
			$object_vars = get_object_vars( $value );
			foreach ( $object_vars as $property_name => $property_value ) {
				$value->$property_name = map_deep( $property_value, $callback );
			}
		} else {
			$value = call_user_func( $callback, $value );
		}

		return $value;
	}

}