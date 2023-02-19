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
		return $this->serializer->serialize($state, 'json');
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
		return $this->serializer->deserialize($serializedState, 'array', 'json');
	}
}
