<?php

namespace Bottledcode\SwytchFramework\Template\Functional;

use Bottledcode\SwytchFramework\Router\Method;
use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Interfaces\HxInterface;

#[Component('Route')]
class Route implements HxInterface
{
	private static bool $foundRoute = false;

	public function __construct()
	{
	}

	public static function reset(): void
	{
		self::$foundRoute = false;
	}

	public static function skipHxProcessing(): bool
	{
		return true;
	}

	public function render(string $render, string $path, string|null $method = null): string
	{
		// no point in rendering anything if we already found a route
		if ($this->foundRoute()) {
			return '';
		}

		// if no method is specified, assume the route should handle all methods
		if ($method === null) {
			$method = $_SERVER['REQUEST_METHOD'];
		}

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
			if (str_starts_with($parts[$i], ':')) {
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

	protected function foundRoute(): bool
	{
		return self::$foundRoute;
	}
}
