<?php

namespace Bottledcode\SwytchFramework\Router\Attributes;

use Attribute;
use Psr\Container\ContainerInterface;
use Withinboredom\BuildingBlocks\Result;

#[Attribute(Attribute::TARGET_METHOD)]
class Authorize
{
    public function __construct(public string $role = 'user')
    {
    }

    /**
     * @param callable $success
     * @param array<string, mixed> $params
     */
    public function __invoke(callable $success, array $params, ContainerInterface $container): Result
    {
        /**
         * @var User|null $user
         */
        $user = $container->get(User::class);
        if ($user === null) {
            setRedirectHeader('/login?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));

            return new Result(HttpResponseCode::TemporaryRedirect, 'Unauthorized');
        }

        /**
         * @psalm-suppress MixedFunctionCall
         * @psalm-suppress MixedReturnStatement
         */
        return $success($user, ...$params);
    }
}
