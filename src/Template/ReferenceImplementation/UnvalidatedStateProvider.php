<?php

namespace Bottledcode\SwytchFramework\Template\ReferenceImplementation;

use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;

readonly class UnvalidatedStateProvider implements StateProviderInterface
{

	public function serializeState(array $state): string
	{
		return base64_encode(json_encode($state));
	}

	public function signState(string $serializedState): string
	{
		return 'A';
	}

	public function verifyState(string $serializedState, string $signature): bool
	{
		return true;
	}

	public function unserializeState(string $serializedState): array
	{
		return json_decode(base64_decode($serializedState), true);
	}
}
