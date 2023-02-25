<?php

namespace Bottledcode\SwytchFramework\Template\Escapers;

use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;

use function Bottledcode\SwytchFramework\Template\dangerous;

class Variables implements EscaperInterface
{
	public const LEFT = '{';
	public const RIGHT = '}';

	/**
	 * @var array<string, string>
	 */
	private array $blobs = [];


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
			if($inners = substr_count($blob, $left)) {
				// we are nesting and we are only interested in the outermost
				for($i = 0; $i < $inners; $i++) {
					$blob .= $right . strtok($right);
				}
			}
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

	public function makeBlobs(string $html): string
	{
		$html = $this->createBlobWithBoundaries($html, "\0", "\0", 'DANG');
		return $this->createBlobWithBoundaries($html, self::LEFT, self::RIGHT, 'BLOB');
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
