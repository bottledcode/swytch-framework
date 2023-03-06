<?php

namespace Bottledcode\SwytchFramework\Hooks\Html;

use Bottledcode\SwytchFramework\Hooks\PostprocessInterface;
use Gettext\Languages\Exporter\Html;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

class HeadTagFilter extends Html implements PostprocessInterface
{
	private array $lines;
	private bool $added = false;

	public function __construct(private readonly Psr17Factory $psr17Factory)
	{
	}

	public function addLines(string $line): void
	{
		$this->added = true;
		$this->lines[] = $line;
	}

	public function postprocess(ResponseInterface $response): ResponseInterface
	{
		if (!$this->added) {
			return $response;
		}
		$response->getBody()->rewind();
		$body = $response->getBody()->getContents();
		$head = implode("\n", $this->lines);
		return $response->withBody($this->psr17Factory->createStream(str_replace('</head>', $head . '</head>', $body)));
	}
}
