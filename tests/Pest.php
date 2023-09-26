<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Escapers\Variables;
use Bottledcode\SwytchFramework\Template\Interfaces\AuthenticationServiceInterface;
use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use Bottledcode\SwytchFramework\Template\ReferenceImplementation\UnvalidatedStateProvider;
use Symfony\Component\Serializer\Serializer;

expect()->extend('toBeOne', function () {
	return $this->toBe(1);
});

expect()->extend('toOutput', function ($expectedFile) {
	if (file_exists($expectedFile)) {
		$expected = file_get_contents($expectedFile);
		$this->toBe($expected);
		return;
	}

	file_put_contents($expectedFile, $this->value);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function getContainer(array $definitionOverride = []): \DI\Container
{
	$builder = new \DI\ContainerBuilder();
	$builder->addDefinitions(
		array_merge([
			StateProviderInterface::class => fn() => new UnvalidatedStateProvider()
		], $definitionOverride)
	);
	return $builder->build();
}

function containerWithComponents(array $components, bool|Closure $authenticated = true): \DI\Container
{
	$container = getContainer([
		AuthenticationServiceInterface::class => new class($authenticated) implements AuthenticationServiceInterface {

			public function __construct(private bool|Closure $authenticated)
			{
			}

			public function isAuthenticated(): bool
			{
				return is_bool($this->authenticated) ? $this->authenticated : ($this->authenticated)();
			}

			public function isAuthorizedVia(BackedEnum ...$role): bool
			{
				return is_bool($this->authenticated) ? $this->authenticated : ($this->authenticated)();
			}
		},
		\Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface::class => new Variables(),
	]);

	$streamer = $container->get(\Bottledcode\SwytchFramework\Template\Parser\StreamingCompiler::class);

	$targetClasses = [];
	foreach($components as $name => $component) {
		$targetClasses[Component::class][] = [[$name], get_class($component)];
	}
	$collection = new \olvlvl\ComposerAttributeCollector\Collection(
		targetClasses: $targetClasses,
		targetMethods: [],
		targetProperties: []
	);

	\olvlvl\ComposerAttributeCollector\Attributes::with(static fn() => $collection);

	foreach($collection->findTargetClasses(Component::class) as $targetClass) {
		$streamer->registerComponent($targetClass);
	}

	return $container;
}
