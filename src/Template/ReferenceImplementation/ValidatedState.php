<?php

namespace Bottledcode\SwytchFramework\Template\ReferenceImplementation;

use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use JsonException;

readonly class ValidatedState implements StateProviderInterface
{
	/**
	 * @param non-empty-string $secret
	 */
	public function __construct(private string $secret)
	{
	}

	/**
	 * @throws JsonException
	 */
	public function serializeState(array $state): string
	{
		return base64_encode(json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
	}

	public function verifyState(string $serializedState, string $signature): bool
	{
		return hash_equals($this->signState($serializedState), $signature);
	}

	public function signState(string $serializedState): string
	{
		return hash_hmac('sha256', $serializedState, $this->secret);
	}

	/**
	 * @throws JsonException
	 */
	public function unserializeState(string $serializedState): array
	{
		return json_decode(base64_decode($serializedState), true, flags: JSON_THROW_ON_ERROR);
	}
}
