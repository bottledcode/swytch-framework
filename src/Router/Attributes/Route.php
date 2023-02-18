<?php

namespace Bottledcode\SwytchFramework\Router\Attributes;

use Attribute;
use Bottledcode\SwytchFramework\Router\Method;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Withinboredom\BuildingBlocks\Result;
use Withinboredom\BuildingBlocks\Router;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class Route
{
    public function __construct(public Method $method, public string $path)
    {
    }

    public static function RegisterRoutes(ContainerInterface $container, string $class): void
    {
        $reflection = new ReflectionClass($class);
        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(__CLASS__);
            if (count($attributes) === 0) {
                continue;
            }

            $callback = static function (mixed ...$args) use ($container, $method, $class): Result {
                $obj = $container->get($class);
                /**
                 * @var Result $result
                 * @psalm-suppress MixedReturnStatement
                 */
                $result = $method->invoke($obj, ...$args);
                return $result;
            };

            $auth = $method->getAttributes(Authorize::class);
            if (count($auth) > 0) {
                /**
                 * @psalm-suppress UnnecessaryVarAnnotation
                 * @var Authorize $auth
                 */
                $auth = $auth[0]->newInstance();
                $callback = static function (mixed ...$args) use ($callback, $auth): Result {
                    return $auth($callback, $args);
                };
            }

            $route = $attributes[0]->newInstance();
            /**
             * @var Router $router
             */
            $router = $container->get(Router::class);
            $router->RegisterRoute($route->method->value, $route->path, $callback);
        }
    }
}
