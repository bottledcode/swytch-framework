<?php

namespace Bottledcode\SwytchFramework\Template;

final readonly class CompiledHtml
{
	public function __construct(public \DOMDocument|\DOMDocumentFragment $document, public array $blobs)
	{
	}
}
