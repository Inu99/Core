<?php


namespace exface\Core\CommonLogic\Log\Handlers\limit;


use exface\Core\CommonLogic\Log\LogHandlerInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;

/**
 * Abstract log handler wrapper to implement any kind of log file cleanup according to the implementation function
 * "limit".
 *
 * @package exface\Core\CommonLogic\Log\Handlers\rotation
 */
abstract class LimitingWrapper implements LogHandlerInterface {
	/** @var LogHandlerInterface $handler */
	private $handler;

	/**
	 * LimitingWrapper constructor.
	 *
	 * @param LogHandlerInterface $handler the "real" log handler
	 */
	function __construct(LogHandlerInterface $handler) {
		$this->handler = $handler;
	}

	public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null) {
		// check and possibly rotate
		$this->limit();

		$this->callLogger($this->handler, $level, $message, $context, $sender);
	}

	/**
	 * Create and call the underlying, "real" log handler.
	 *
	 * @param LogHandlerInterface $handler the "real" log handler
	 * @param $level
	 * @param $message
	 * @param array $context
	 * @param iCanGenerateDebugWidgets|null $sender
	 *
	 * @return mixed
	 */
	protected abstract function callLogger(LogHandlerInterface $handler, $level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null);

	/**
	 * Log file cleanup.
	 */
	protected abstract function limit();
}