<?php

namespace Bottledcode\SwytchFramework\Hooks\Html;

use Bottledcode\SwytchFramework\Hooks\PostprocessInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

class HeadTagFilter extends HtmlHandler implements PostprocessInterface
{
	/**
	 * @var array<string>
	 */
	private array $lines = [];

	public function __construct(private readonly Psr17Factory $psr17Factory)
	{
	}

	public function addLines(string $line): void
	{
		$this->lines[] = $line;
	}

	public function postprocess(ResponseInterface $response): ResponseInterface
	{
		if (!count($this->lines)) {
			return $response;
		}
		$response->getBody()->rewind();
		$body = $response->getBody()->getContents();
		$head = implode("\n", $this->lines);
		return $response->withBody($this->psr17Factory->createStream(str_replace('</head>', $head . '</head>', $body)));
	}
}
