<?php

namespace WP_Ops;

class Logger {

	function log( $message ) {

		if ( 1 !== func_num_args() ) {
			$message = call_user_func_array( 'sprintf', func_get_args() );
		}
		fwrite( STDERR, $message );

	}


}