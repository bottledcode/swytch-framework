<?php

namespace Bottledcode\SwytchFramework\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class StdOutputLogger extends AbstractProcessingHandler
{
	/**
	 * @var false|resource $output
	 */
	private $output;

	public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
	{
		parent::__construct($level, $bubble);
		$this->output = fopen('php://stderr', 'wb');
	}

	public function __destruct()
	{
		if ($this->output !== false) {
			@fclose($this->output);
		}
		parent::__destruct();
	}

	protected function write(LogRecord $record): void
	{
		if ($this->output !== false) {
			@fwrite($this->output, $record->formatted);
		}
	}
}
