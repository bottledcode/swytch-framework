<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;
use DOMCharacterData;
use DOMNode;
use Laminas\Escaper\Escaper;
use Masterminds\HTML5\Serializer\OutputRules;

class Output extends OutputRules
{
	private Escaper $escaper;

	private EscaperInterface $blobs;

	/**
	 * @param $output
	 * @param array<mixed> $options
	 */
	public function __construct($output, $options = array())
	{
		parent::__construct($output, $options);
	}

	public function setBlobber(EscaperInterface $blobs): void
	{
		$this->blobs = $blobs;
	}

	public function setEscaper(Escaper $escaper): void
	{
		$this->escaper = $escaper;
	}

	/**
	 * @param DOMNode $ele
	 * @return void
	 */
	public function element($ele): void
	{
		if ($ele->nodeName === 'script') {
			foreach ($ele->childNodes as $child) {
				if ($child instanceof DOMCharacterData) {
					$child->data = $this->script($child->data);
				}
			}
		}
		if ($ele->nodeName === 'style') {
			foreach ($ele->childNodes as $child) {
				if ($child instanceof DOMCharacterData) {
					$child->data = $this->style($child->data);
				}
			}
		}

		parent::element($ele);
	}

	private function script(string $text): string
	{
		return (string)$this->blobs->replaceBlobs($text, fn($blob) => $this->escaper->escapeJs($blob));
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function style(string $text): string
	{
		return (string)$this->blobs->replaceBlobs($text, fn($blob) => $this->escaper->escapeCss($blob));
	}

	/**
	 * @param string $text
	 * @param bool $attribute
	 * @return string
	 */
	public function enc($text, $attribute = false): string
	{
		return (string)$this->blobs->replaceBlobs(
			$text,
			fn($blob) => $attribute ? $this->escaper->escapeHtmlAttr($blob) : $this->escaper->escapeHtml($blob)
		);
	}
}
