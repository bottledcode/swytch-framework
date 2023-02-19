<?php

namespace Bottledcode\SwytchFramework\Template\ReferenceImplementation;

use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use Symfony\Component\Serializer\Serializer;

readonly class ValidatedState implements StateProviderInterface
{
	/**
	 * @param non-empty-string $secret
	 * @param Serializer $serializer
	 */
	public function __construct(private string $secret, private Serializer $serializer)
	{
	}

	public function serializeState(array $state): string
	{
		return json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
	}

	public function verifyState(string $serializedState, string $signature): bool
	{
		return hash_equals($this->signState($serializedState), $signature);
	}

	public function signState(string $serializedState): string
	{
		return hash_hmac('sha256', $serializedState, $this->secret);
	}

	public function unserializeState(string $serializedState): array
	{
		return json_decode($serializedState, true, flags: JSON_THROW_ON_ERROR);
	}
}
