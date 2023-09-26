<?php

namespace Bottledcode\SwytchFramework\Template\Parser;

class Document
{
	public function __construct(public readonly string $code, private int $position = 0)
	{
	}

	public function consume(int $amount = 1): string
	{
		$this->position += $amount;
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

	public function mark(): int
	{
		return $this->position;
	}

	public function snip(int $start, int $end): Document
	{
		//$code = s($this->code, $start, $end - $start);
		$code = substr($this->code, 0, $start) . substr($this->code, $end);
		$position = $this->position - $start;
		if ($position < $start) {
			$position = $start;
		}
		return new Document($code, $position);
	}

	public function insert(string $code, int $at): Document
	{
		$code = substr($this->code, 0, $at) . $code . substr($this->code, $at);
		$position = $this->position + strlen($code);
		return new Document($code, $position);
	}

	public function seek(int $newPosition): void
	{
		$this->position = $newPosition;
	}
}
