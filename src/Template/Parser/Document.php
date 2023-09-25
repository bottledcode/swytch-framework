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

	public function peek(int $amount): string {
		return substr($this->code, $this->position, $amount);
	}

	public function mark(): int
	{
		return $this->position;
	}
}
