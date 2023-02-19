<?php

namespace Bottledcode\SwytchFramework\Template\ReferenceImplementation;

use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use Symfony\Component\Serializer\Serializer;

readonly class EncryptedStateProvider implements StateProviderInterface
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
		$serialized = $this->serializer->serialize($state, 'json');
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$cipher = base64_encode($nonce.sodium_crypto_box($serialized, $nonce, $this->secret));
		return $cipher;
	}

	public function signState(string $serializedState): string
	{
		return hash_hmac('sha256', $serializedState, $this->secret);
	}

	public function verifyState(string $serializedState, string $signature): bool
	{
		return hash_equals($this->signState($serializedState), $signature);
	}

	public function unserializeState(string $serializedState): array
	{
		$decoded = base64_decode($serializedState);
		if($decoded === false) throw new \InvalidArgumentException('Invalid state');
		$nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
		$cipher = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
		$serialized = sodium_crypto_box_open($cipher, $nonce, $this->secret);
		return $this->serializer->deserialize($serialized, 'array', 'json');
	}
}
