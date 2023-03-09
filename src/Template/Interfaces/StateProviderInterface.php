<?php

namespace Bottledcode\SwytchFramework\Template\Interfaces;

/**
 * Provides state serialization and signing for state transmission to rerender components.
 */
interface StateProviderInterface
{
	/**
	 * Serialize state for embedding in the DOM
	 *
	 * @param array<mixed> $state The state to serialize
	 * @return string The serialized state
	 */
	public function serializeState(array $state): string;

	/**
	 * Sign state to prevent tampering
	 *
	 * @param string $serializedState The serialized state to sign
	 * @return string The signature
	 */
	public function signState(string $serializedState): string;

	/**
	 * Verify the signature of a serialized state
	 *
	 * @param string $serializedState The serialized state to verify
	 * @param string $signature The signature to verify
	 * @return bool True if the signature is valid
	 */
	public function verifyState(string $serializedState, string $signature): bool;

	/**
	 * Unserialize the state from the DOM
	 *
	 * @param string $serializedState The serialized state to unserialize
	 * @return array<mixed> The unserialized state
	 */
	public function unserializeState(string $serializedState): array;
}
