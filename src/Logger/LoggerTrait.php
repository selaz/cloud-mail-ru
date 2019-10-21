<?php

namespace Selaz\Logs;

use Bramus\Monolog\Formatter\ColoredLineFormatter,
	Bramus\Monolog\Formatter\ColorSchemes\TrafficLight,
	Monolog\Handler\StreamHandler,
	Monolog\Logger;

trait LoggerTrait {

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	protected function emergency($message, array $context = []) {
		$this->log(Logger::EMERGENCY, $message, $context);
	}

	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	protected function alert($message, array $context = []) {
		$this->log(Logger::ALERT, $message, $context);
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	protected function critical($message, array $context = []) {
		$this->log(Logger::CRITICAL, $message, $context);
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	protected function error($message, array $context = []) {
		$this->log(Logger::ERROR, $message, $context);
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	protected function warning($message, array $context = []) {
		$this->log(Logger::WARNING, $message, $context);
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	protected function notice($message, array $context = []) {
		$this->log(Logger::NOTICE, $message, $context);
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	protected function info($message, array $context = []) {
		$this->log(Logger::INFO, $message, $context);
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	protected function debug($message, array $context = []) {
		$this->log(Logger::DEBUG, $message, $context);
	}

	protected function log($level, $message, array $context = []) {
		
		$handler = new StreamHandler('php://stdout', Logger::DEBUG);
		$handler->setFormatter(
				new ColoredLineFormatter(
					new TrafficLight(), 
					'[%datetime%] %channel%.%level_name%: %message% %context%'
				)
			);
		
		$stack = debug_backtrace()[1] ?? ['file'=>null,'line'=>null];
		
		$message = sprintf('%s:%s %s', $stack['file'], $stack['line'], $message);
		
		$logger = new Logger('Selaz', [$handler]);
		$logger->log($level, $message, $context);
	}
	
}
