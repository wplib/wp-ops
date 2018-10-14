<?php

namespace WP_Ops;

/**
 * Class Error - Immutable
 * @package WP_Ops
 */
class Error {

	/**
	 * @var string
	 */
	private $_message;

	/**
	 * @var array
	 */
	private $_args;

	/**
	 * Error constructor.
	 *
	 * @param string $message
	 * @param array $args
	 */
	function __construct( $message, $args = array() ) {
		$this->_message = $message;
		$this->_args    = $args;
	}

	/**
	 * @return string
	 */
	function message() {
		return "ERROR: {$this->_message}";
	}

	/**
	 * @return array
	 */
	function args() {
		return $this->_args;
	}

}