<?php

namespace Bottledcode\SwytchFramework\Template\ReferenceImplementation;

use Bottledcode\SwytchFramework\Template\Interfaces\RefProviderInterface;

class SimpleRefProvider implements RefProviderInterface {
	private array $refs = [];

	public function createRef(mixed $item): string
	{
		if(is_numeric($item) || is_string($item) || is_bool($item) || is_null($item)) {
			// can't create a scalar ref
			return (string) $item;
		}

		if(is_callable($item)) {
			throw new \LogicException('Cannot create a ref to a callable');
		}

		// this is terrible, but it works for now
		retry:
		$id = base64_encode(random_bytes(9));
		if(isset($this->refs[$id])) {
			goto retry;
		}

		$this->refs[$id] = $item;

		return $id;
	}

	public function getRef(string $ref): mixed
	{
		return $this->refs[$ref] ?? throw new \RuntimeException('Ref not found');
	}

	public function deleteRef(string $ref): void
	{
		unset($this->refs[$ref]);
	}
}
