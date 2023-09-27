<?php

namespace Bottledcode\SwytchFramework\Template\Parser;

class Document
{
	public function __construct(public readonly string $code, private int $position = 0, private array $listeners = [])
	{
	}

	public function consume(int $amount = 1): string
	{
		$this->position += $amount;
		// note: we only consume more than one in very special circumstances so we're not handling that special case.
		if (array_key_exists($this->position, $this->listeners)) {
			foreach ($this->listeners[$this->position] as $listener) {
				$listener();
			}
			unset($this->listeners[$this->position]);
		}
		return $this->code[$this->position - $amount];
	}

	public function isEof(): bool
	{
		return $this->position >= strlen($this->code);
	}

	public function reconsume(\Closure $what): Document
	{
		$this->position -= 1;
		return $what($this);
	}

	public function peek(int $amount): string
	{
		return substr($this->code, $this->position, $amount);
	}

	public function __debugInfo(): ?array
	{
		// add a cursor to the code to show where we are in the code
		$code = substr($this->code, 0, $this->position) . '|' . substr($this->code, $this->position);
		// add a cursor to the listeners to show where we are in the listeners
		foreach ($this->listeners as $listener => $_) {
			$position = $listener > $this->position ? $listener + 1 : $listener;
			$code[$position] = '+';
		}
		return ['code' => $code];
	}

	public function mark(): int
	{
		return $this->position;
	}

	public function snip(int $start, int $end, string &$output = null): Document
	{
		$output ??= '';
		if ($start === $end) {
			return $this;
		}
		//$code = s($this->code, $start, $end - $start);
		$output = substr($this->code, $start, $end - $start);
		$code = substr($this->code, 0, $start) . substr($this->code, $end);
		$position = $this->position - ($end - $start);
		if ($position < $start) {
			$position = $start;
		}

		$listeners = [];
		foreach ($this->listeners as $index => $actions) {
			$listeners[$index >= $end ? $index - ($end - $start) : $index] = $actions;
		}

		return new Document($code, $position, $listeners);
	}

	public function insert(string $code, int $at, bool $keepPosition = false): Document
	{
		if ($code === '') {
			return $this;
		}
		$newCode = substr($this->code, 0, $at) . $code . substr($this->code, $at);
		$position = $this->position + strlen($code);
		$listeners = [];
		foreach ($this->listeners as $index => $actions) {
			$listeners[$index >= $at ? $index + strlen($code) : $index] = $actions;
		}
		return new Document($newCode, $position, $listeners);
	}

	public function onPosition(int $position, \Closure $what): void
	{
		$this->listeners[$position][] = $what;
	}

	public function seek(int $newPosition): self
	{
		$this->position = $newPosition;
		return $this;
	}
}
