<?php

namespace Bottledcode\SwytchFramework\Hooks\Api;

use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\PreprocessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\Router\Attributes\Route;
use Bottledcode\SwytchFramework\Router\Exceptions\InvalidRequest;
use Bottledcode\SwytchFramework\Router\Exceptions\NotFound;
use Bottledcode\SwytchFramework\Router\Method;
use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use JsonException;
use Nyholm\Psr7\Uri;
use olvlvl\ComposerAttributeCollector\Attributes;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Handler(priority: 10)]
class Router extends ApiHandler implements PreprocessInterface
{
	public const ATTRIBUTE_STATE = 'router-state';
	public const ATTRIBUTE_HANDLER = 'router-handler';
	public const ATTRIBUTE_PATH_ARGS = 'router-path-args';
	public const ATTRIBUTE_ORIGINAL_URI = 'router-original-uri';

	public function __construct(
		private readonly StateProviderInterface $stateProvider,
		private readonly LoggerInterface $logger
	) {
	}

	public function preprocess(ServerRequestInterface $request, RequestType $type): ServerRequestInterface
	{
		$apiRoutes = Attributes::findTargetMethods(Route::class);
		$currentPathParts = array_values(
			array_filter(explode('/', $request->getUri()->getPath()), fn($part) => $part !== '')
		);
		$currentMethod = Method::tryFrom($request->getMethod());
		foreach ($apiRoutes as $route) {
			/**
			 * @var Route $attribute
			 */
			$attribute = $route->attribute;
			if ($attribute->method !== $currentMethod) {
				continue;
			}
			// determine if the route matches the current path
			$matchPathParts = array_values(array_filter(explode('/', $attribute->path)));

			// skip if the path parts don't match what we're searching for
			if (($count = count($matchPathParts)) !== count($currentPathParts)) {
				continue;
			}

			// try and extract path arguments
			$pathArgs = [];
			for ($i = 0; $i < $count; $i++) {
				if (str_starts_with($matchPathParts[$i], ':')) {
					// replace the placeholder with the actual value
					$pathArgs[substr($matchPathParts[$i], 1)] = $currentPathParts[$i];
				} elseif ($matchPathParts[$i] !== $currentPathParts[$i]) {
					continue 2;
				}
			}

			$body = $request->getBody();
			$body = $body->getContents();
			switch ($request->getHeaderLine('Content-Type')) {
				case 'application/json':
					try {
						$parsed = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
					} catch (JsonException $exception) {
						$this->logger->notice('Invalid JSON body', compact('exception', 'body'));
						$parsed = null;
					}
					break;
				case 'application/x-www-form-urlencoded':
					$parsed = [];
					parse_str($body, $parsed);
					break;
				default:
					$parsed = null;
					break;
			}
			if (is_array($parsed) && !empty($parsed)) {
				$expectedToken = $request->getCookieParams()['csrf_token'] ?? throw new InvalidRequest(
					'Missing CSRF token'
				);
				$actualToken = $parsed['csrf_token'] ?? throw new InvalidRequest('Missing CSRF token');
				if (!hash_equals($expectedToken, $actualToken)) {
					throw new InvalidRequest('Invalid CSRF token');
				}
				unset($parsed['csrf_token']);

				$state = $parsed['state'] ?? null;
				$stateSignature = $parsed['state_hash'] ?? null;
				if (!empty($state) && empty($stateSignature)) {
					throw new InvalidRequest('Missing state signature');
				}
				if (!empty($state)) {
					if (!$this->stateProvider->verifyState($state, $stateSignature)) {
						throw new InvalidRequest('Invalid state signature');
					}
					unset($parsed['state_hash']);
					unset($parsed['state']);
					$request = $request->withAttribute(self::ATTRIBUTE_STATE, $state);
				}

				$request = $request->withParsedBody($this->sanitize($parsed));
			}

			// if we made it this far, we have a path match
			// replace the path with the matching path, and store the path args
			return $request
				->withUri(new Uri(implode('/', $matchPathParts)))
				->withAttribute(self::ATTRIBUTE_HANDLER, $route)
				->withAttribute(self::ATTRIBUTE_ORIGINAL_URI, $request->getUri())
				->withAttribute(self::ATTRIBUTE_PATH_ARGS, $this->sanitize($pathArgs));
		}

		throw new NotFound();
	}

	/**
	 * @param array<mixed> $values
	 * @return array<mixed>
	 */
	private function sanitize(array $values): array
	{
		foreach ($values as &$value) {
			if (is_array($value)) {
				$value = $this->sanitize($value);
			} elseif (is_string($value)) {
				$value = str_replace(['{', '}'], ['{{', '}}'], $value);
			}
		}
		return $values;
	}
}
