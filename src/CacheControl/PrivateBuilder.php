<?php

namespace Bottledcode\SwytchFramework\CacheControl;

class PrivateBuilder extends Builder
{
	public function render(string $etag): void
	{
		$header = self::$header;
		$header('Cache-Control: ' . implode(',', $this->values));
		if ($this->etagRequired) {
			$header('ETag: ' . $etag);
		}
	}
}
