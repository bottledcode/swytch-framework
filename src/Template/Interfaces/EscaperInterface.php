<?php

namespace Bottledcode\SwytchFramework\Template\Interfaces;

interface EscaperInterface {
	/**
	 * Given a string, replaces escaped information with __BLOB__N__ where N is the id of the blob
	 *
	 * @param string $html
	 * @return string
	 */
	public function makeBlobs(string $html): string;

	/**
	 * Given a string, replaces __BLOB__N__ with the blob at index N
	 *
	 * @param string $html
	 * @param callable $processor
	 * @return string
	 */
	public function replaceBlobs(string $html, callable $processor): string;

	public function getBlobs(): array;

	public function getBlob(int $index): string;
}
