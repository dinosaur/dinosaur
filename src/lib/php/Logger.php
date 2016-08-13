<?php
namespace Dinosaur;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\ErrorLogHandler;

class Logger extends MonologLogger {
	public static $loggers = [];

	/**
	 *
	 * @param string $name
	 * @param array $handlers
	 * @param array $processors
	 * @return \Dinosaur\Logger
	 */
	public static function get( string $name, array $handlers = [], array $processors = [] ): Logger
	{
		if ( isset( static::$loggers[ $name ] ) ) {
			return static::$loggers[ $name ];
		}

		$logger = new static( $name, $handlers, $processors );
		static::$loggers[ $name ] = $logger;
		return $logger;
	}

	/**
	 *
	 * @param string $name
	 * @param array $handlers
	 * @param array $processors
	 */
	public function __construct( string $name, array $handlers = [], array $processors = [] ) {
		parent::__construct( $name, $handlers, $processors );

		if ( empty( $handlers ) ) {
			$this->pushHandler( new ErrorLogHandler() );
		}
	}
}