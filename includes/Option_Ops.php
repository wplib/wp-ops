<?php

namespace WP_Ops;

class Option_Ops {

	/**
	 * @var Error
	 */
	private $_last_error;

	/**
	 * @return Error
	 */
	function last_error() {
		return $this->_last_error;
	}

	/**
	 * @param string $option_name
	 */
	function delete( $option_name ) {
		delete_option( $option_name );
	}


}