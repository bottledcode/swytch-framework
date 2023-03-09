<?php

namespace Bottledcode\SwytchFramework\Template\ReferenceImplementation;

use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use JsonException;

readonly class UnvalidatedStateProvider implements StateProviderInterface
{

	/**
	 * @throws JsonException
	 */
	public function serializeState(array $state): string
	{
		return base64_encode((string)json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
	}

	public function signState(string $serializedState): string
	{
		return 'A';
	}

	public function verifyState(string $serializedState, string $signature): bool
	{
		return true;
	}

	/**
	 * @throws JsonException
	 */
	public function unserializeState(string $serializedState): array
	{
		return json_decode(base64_decode($serializedState), true, flags: JSON_THROW_ON_ERROR);
	}
}
