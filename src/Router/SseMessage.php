<?php

namespace Bottledcode\SwytchFramework\Router;

readonly class SseMessage
{
	public function __construct(
		public string $event,
		public string $data,
		public string|null $id = null,
		public int|null $retryMs = null,
	) {
	}
}
