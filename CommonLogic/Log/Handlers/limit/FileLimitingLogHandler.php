<?php

namespace exface\Core\CommonLogic\Log\Handlers\limit;


use exface\Core\CommonLogic\Log\Helpers\LogHelper;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;

/**
 * Log handler that uses the given createCallback to instantiate an underlying log handler that logs to a specific log
 * file and limits the number of daily versions of a this log file to the given value of maxDays.
 *
 * @package exface\Core\CommonLogic\Log\Handlers
 */
class FileLimitingLogHandler extends LimitingWrapper {
	private $filename;
	private $filenameFormat;
	private $dateFormat;
	private $maxDays;

	/**
	 * DailyRotatingLogHandler constructor.
	 *
	 * @param Callable $createCallback callback function that create the underlying, "real" log handler
	 * @param string $filename base file name of the log file (date string is added to)
	 * @param int $maxDays maximum number of daily versions of a log file
	 */
	function __construct(Callable $createCallback, $filename, $maxDays = 0) {
		parent::__construct($createCallback);

		$this->filename = $filename;
		$this->maxDays  = $maxDays;

		$this->filenameFormat = '{filename}-{variable}';
		$this->dateFormat = 'Y-m-d';
	}

	protected function callLogger(Callable $createLoggerCall, $level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null) {
		$createLoggerCall(LogHelper::getFilename($this->filename, $this->dateFormat, $this->filenameFormat))->handle($level, $message, $context, $sender);
	}

	/**
	 * Log file cleanup.
	 */
	protected function limit()
	{
		// skip GC of old logs if files are unlimited
		if (0 === $this->maxDays) {
			return;
		}

		$logFiles = glob(LogHelper::getPattern($this->filename, $this->filenameFormat));
		if ($this->maxDays >= count($logFiles)) {
			// no files to remove
			return;
		}

		// Sorting the files by name to remove the older ones
		usort($logFiles, function ($a, $b) {
			return strcmp($b, $a);
		});

		foreach (array_slice($logFiles, $this->maxDays) as $file) {
			if (is_writable($file)) {
				// suppress errors here as unlink() might fail if two processes
				// are cleaning up/rotating at the same time
				set_error_handler(function ($errno, $errstr, $errfile, $errline) {});
				unlink($file);
				restore_error_handler();
			}
		}
	}
}