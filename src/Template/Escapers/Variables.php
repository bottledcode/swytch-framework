<?php

namespace Bottledcode\SwytchFramework\Template\Escapers;

use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;

class Variables implements EscaperInterface {
	public const LEFT = '{';
	public const RIGHT = '}';

	/**
	 * @var array<string, string>
	 */
	private array $blobs = [];


	public function makeBlobs(string $html): string
	{
		$html = str_replace(self::LEFT . self::RIGHT, '', $html);
		$next = strtok($html, self::LEFT);
		if($next === $html || $next === false) {
			return $html;
		}

		$future = $next;

		while(true) {
			$blob = strtok(self::RIGHT);
			if($blob === false) {
				return $future;
			}
			$key = '__BLOB__' . count($this->blobs) . '__';
			$this->blobs[$key] = $blob;
			$future .= $key;
			$next = strtok(self::LEFT);
			$future .= $next;
			if($next === false) {
				return $future;
			}
		}
	}

	public function replaceBlobs(string $html): string
	{
		foreach($this->blobs as $key => $blob) {
			$html = str_replace($key, $blob, $html);
		}

		return $html;
	}

	/**
	 * @return string[]
	 */
	public function getBlobs(): array
	{
		return $this->blobs;
	}

	public function getBlob(int $index): string
	{
		return $this->blobs['__BLOB__' . $index . '__'];
	}
}
