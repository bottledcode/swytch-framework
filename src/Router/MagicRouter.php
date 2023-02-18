<?php

namespace Bottledcode\SwytchFramework\Router;

use Bottledcode\SwytchFramework\Router\Attributes\Route;
use Bottledcode\SwytchFramework\Router\Exceptions\InvalidRequest;
use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Compiler;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetClass;
use Psr\Container\ContainerInterface;
use Symfony\Component\Serializer\Serializer;

class MagicRouter
{
	private array $middleware = [];

	public function __construct(private ContainerInterface $container, private string $appRoot)
	{
	}

	public function withMiddleware(string $middleware): self
	{
		$this->middleware[] = $middleware;
		return $this;
	}

	public function go(): string|null
	{
		$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		if (str_starts_with($currentPath, '/api')) {
			// handle api routes
			$apiRoutes = Attributes::findTargetMethods(Route::class);
			$currentPathParts = array_values(array_filter(explode('/', $currentPath)));
			$currentMethod = Method::tryFrom($_SERVER['REQUEST_METHOD']);
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
				if (($count = count($matchPathParts)) !== count($currentPathParts)) {
					continue;
				}
				$pathArgs = [];
				for ($i = 0; $i < $count; $i++) {
					if (str_starts_with($matchPathParts[$i], ':')) {
						// replace the placeholder with the actual value
						$pathArgs[substr($matchPathParts[$i], 1)] = $currentPathParts[$i];
					} else {
						if ($matchPathParts[$i] !== $currentPathParts[$i]) {
							continue 2;
						}
					}
				}

				if ($currentMethod !== Method::GET) {
					// determine if the payload is json or form data
					$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
					$payload = match ($contentType) {
						'application/json' => json_decode(
							file_get_contents('php://input'),
							true,
							flags: JSON_THROW_ON_ERROR
						),
						'application/x-www-form-urlencoded' => $_POST,
						default => null
					};
					if ($payload === null) {
						throw new InvalidRequest('Invalid content type: ' . $contentType);
					}
					// determine if the request is valid
					$expectedToken = $_COOKIE['csrf_token'] ?? throw new InvalidRequest('Missing CSRF token');
					$actualToken = $payload['csrf_token'] ?? throw new InvalidRequest('Missing CSRF token');
					if (!hash_equals($expectedToken, $actualToken)) {
						throw new InvalidRequest('Missing CSRF token');
					}
					unset($payload['csrf_token']);

					// capture the state
					$state = $payload['state'] ?? null;
					$state_signature = $payload['state_hash'] ?? null;
					if (empty($state_signature) && !empty($state)) {
						throw new InvalidRequest('Invalid state');
					}
					if(!empty($state)) {
						$state = base64_decode($state);
						if (!hash_equals(
							hash_hmac('sha256', $state, $this->container->get('state_secret')),
							$state_signature
						)) {
							throw new InvalidRequest('Invalid state');
						}

						unset($payload['state_hash']);
						unset($payload['state']);
					}
				} else {
					$payload = [];
				}

				$payload = array_merge($payload, $pathArgs);

				// everything looks correct (hopefully), so execute the route
				$middleware = array_map(
					fn(string $middleware) => $this->container->get($middleware),
					$this->middleware
				);
				foreach ($middleware as $m) {
					$state = $m($attribute->path, $attribute->method, $state, $payload);
				}

				// look up the method on the component to see what it expects
				$componentReflection = new \ReflectionClass($route->class);
				$componentMethod = $componentReflection->getMethod($route->name);
				$componentMethodParameters = $componentMethod->getParameters();

				$arguments = [];
				foreach ($componentMethodParameters as $parameter) {
					$parameterName = $parameter->getName();
					if ($parameterName === 'state') {
						$arguments[] = $state;
					} else {
						if ($parameter->getType() instanceof \ReflectionNamedType && $parameter->getType()->getName(
							) === 'string') {
							$arguments[$parameter->getName()] = $payload[$parameter->getName(
							)] ?? throw new InvalidRequest('Missing parameter: ' . $parameter->getName());
						} elseif (class_exists($parameter->getType()->getName())) {
							/** @var Serializer $serializer */
							$serializer = $this->container->get(Serializer::class);
							$arguments[$parameter->getName()] = $serializer->denormalize(
								$payload,
								$parameter->getType()->getName()
							);
						}
					}
				}
				$component = $this->container->get($route->class);
				return $componentMethod->invokeArgs($component, $arguments);
			}
		} else {
			// handle web routes
			$compiler = $this->container->get(Compiler::class);
			array_map(static fn(TargetClass $class) => $compiler->registerComponent($class),
				Attributes::findTargetClasses(Component::class));
			return $compiler->compileComponent($this->appRoot)->renderToString();
		}

		return null;
	}
}
