<?php

namespace Bottledcode\SwytchFramework\Template\Escapers;

use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;

class Variables implements EscaperInterface
{
	public const LEFT = '{';
	public const RIGHT = '}';

	/**
	 * @var array<string, string>
	 */
	private array $blobs = [];

	public function makeBlobs(string $html): string
	{
		$html = $this->createBlobWithBoundaries($html, "\0", "\0", 'DANG');
		$html = str_replace([self::LEFT.self::LEFT.self::LEFT, self::LEFT.self::LEFT, self::RIGHT.self::RIGHT], ["{\0LEFT\0", "\0LEFT\0", "\0RIGHT\0"], $html);
		$html = $this->createBlobWithBoundaries($html, self::LEFT, self::RIGHT, 'BLOB');
		return $html;
	}

	public static function escape(string $string): string {
		return str_replace(['{', '}'], ['{{', '}}'], $string);
	}

	protected function createBlobWithBoundaries(string $html, string $left, string $right, string $type): string
	{
		$html = str_replace($left . $right, '', $html);
		$next = strtok($html, $left);
		if ($next === $html || $next === false) {
			return $html;
		}

		$future = $next;

		while (true) {
			$blob = strtok($right);
			if ($blob === false) {
				return $future;
			}
			$key = '__' . $type . '__' . count($this->blobs) . '__';
			$this->blobs[$key] = $blob;
			$future .= $key;
			$next = strtok($left);
			$future .= $next;
			if ($next === false) {
				return $future;
			}
		}
	}

	public function replaceBlobs(string $html, callable $processor): string
	{
		foreach ($this->blobs as $key => $blob) {
			if (str_contains($html, $key)) {
				if (str_starts_with($key, '__BLOB__')) {
					$blob = $processor($blob);
				}
				$html = str_replace($key, $blob, $html);
			}
		}

		$html = str_replace(["\0LEFT\0", "\0RIGHT\0"], [self::LEFT, self::RIGHT], $html);

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
