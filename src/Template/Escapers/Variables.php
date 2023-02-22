<?php

namespace Bottledcode\SwytchFramework\Template\Escapers;

use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;

use function Bottledcode\SwytchFramework\Template\dangerous;

class Variables implements EscaperInterface {
	public const LEFT = '{';
	public const RIGHT = '}';

	/**
	 * @var array<string, string>
	 */
	private array $blobs = [];


	private function createBlobWithBoundaries(string $html, string $left, string $right): string
	{
		$html = str_replace($left . $right, '', $html);
		$next = strtok($html, $left);
		if($next === $html || $next === false) {
			return $html;
		}

		$future = $next;

		while(true) {
			$blob = strtok($right);
			if($blob === false) {
				return $future;
			}
			$key = '__BLOB__' . count($this->blobs) . '__';
			$this->blobs[$key] = $blob;
			$future .= $key;
			$next = strtok($left);
			$future .= $next;
			if($next === false) {
				return $future;
			}
		}
	}

	public function makeBlobs(string $html): string
	{
		$boundaries = substr(dangerous(''), 0, 8);
		$html = $this->createBlobWithBoundaries($html, $boundaries, $boundaries);
		return $this->createBlobWithBoundaries($html, self::LEFT, self::RIGHT);
	}

	public function replaceBlobs(string $html, callable $processor): string
	{
		foreach($this->blobs as $key => $blob) {
			if(str_contains($html, $key)) {
				$blob = $processor($blob);
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
