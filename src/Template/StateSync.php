<?php

namespace Bottledcode\SwytchFramework\Template;

use JetBrains\PhpStorm\ArrayShape;

abstract class StateSync {
	/**
	 * @param array<string> $state
	 * @return array
	 */
	#[ArrayShape(['state' => 'string', 'hash' => 'string'])]
	public static function serializeState(string $stateSecret, array $state): array {
		$serialized = json_encode($state, JSON_THROW_ON_ERROR);
		return [
			'state' => base64_encode($serialized),
			'hash' => hash_hmac('sha256', $serialized, $stateSecret)
		];
	}

	public static function verifyState(string $stateSecret, string $state, string $hash): bool {
		return hash_equals(hash_hmac('sha256', base64_decode($state), $stateSecret), $hash);
	}
}
