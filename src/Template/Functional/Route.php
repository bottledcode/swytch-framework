<?php

namespace Bottledcode\SwytchFramework\Template\Functional;

use Bottledcode\SwytchFramework\Router\Method;
use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Compiler;

#[Component('Route')]
class Route
{
	private static bool $foundRoute = false;

	public function __construct()
	{
	}

	protected function foundRoute(): bool {
		return self::$foundRoute;
	}

	public function render(string $render, string $path, string $method): string
	{
		$method = Method::tryFrom(strtoupper($method)) ?? throw new \LogicException('Invalid method: ' . $method);
		$actualMethod = Method::tryFrom($_SERVER['REQUEST_METHOD']);
		if ($actualMethod !== $method) {
			return '';
		}

		$actualPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$actualParts = array_values(array_filter(explode('/', $actualPath)));
		$parts = array_values(array_filter(explode('/', $path)));
		if (($numParts = count($actualParts)) !== count($parts)) {
			return '';
		}

		for ($i = 0; $i < $numParts; $i++) {
			if (str_starts_with($parts[$i],':')) {
				$render = str_replace("{{$parts[$i]}}", $actualParts[$i], $render);
			} else {
				if ($parts[$i] !== $actualParts[$i]) {
					return '';
				}
			}
		}

		self::$foundRoute = true;

		return $render;
	}
}
