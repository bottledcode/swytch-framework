<?php

namespace Bottledcode\SwytchFramework\Template\Functional;

use Bottledcode\SwytchFramework\Router\Method;
use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Interfaces\HxInterface;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

#[Component('Route')]
class Route implements HxInterface, DataProvider
{
	/**
	 * @var array<string, string>
	 */
	public array $variables = [];

	public function __construct(private readonly ServerRequestInterface $request)
	{
	}

	public static function skipHxProcessing(): bool
	{
		return true;
	}

	public function render(string $path, string|null $method = null): string
	{
		// if no method is specified, assume the route should handle all methods
		if ($method === null) {
			$method = $this->request->getMethod();
		}

		$method = Method::tryFrom(strtoupper($method)) ?? throw new LogicException('Invalid method: ' . $method);
		$actualMethod = Method::tryFrom($this->request->getMethod());
		if ($actualMethod !== $method) {
			return '';
		}

		$actualPath = $this->request->getUri()->getPath();
		$actualParts = array_values(array_filter(explode('/', $actualPath)));
		$parts = array_values(array_filter(explode('/', $path)));
		if (($numParts = count($actualParts)) !== count($parts)) {
			return '';
		}

		for ($i = 0; $i < $numParts; $i++) {
			if (str_starts_with($parts[$i], ':')) {
				$this->variables["{{$parts[$i]}}"] = $actualParts[$i];
			} elseif ($parts[$i] !== $actualParts[$i]) {
				return '';
			}
		}

		return "<children></children>";
	}

	public function provideValues(string $value): string
	{
		return str_replace(array_keys($this->variables), array_values($this->variables), $value);
	}

	public function provideAttributes(): array
	{
		return [];
	}
}
