<?php

use Bottledcode\SwytchFramework\Template\Escapers\Variables;
use Bottledcode\SwytchFramework\Template\Interfaces\AuthenticationServiceInterface;
use Bottledcode\SwytchFramework\Template\Parser\Streaming;
use Psr\Log\NullLogger;

it('can parse some basic html', function () {
	$container = getContainer();
	$streamer = new Streaming($container, new Variables(), new NullLogger());
	$document = <<<HTML
<!DOCTYPE html>
<html>
<body>
<!-- this is a comment -->
<p class="active" data-platform='fake'>
	Hello world?!
</p>
</body>
</html>
HTML;
	$result = $streamer->compile($document);
	expect(trim($result))->toBe(trim($document));
});

#[\Bottledcode\SwytchFramework\Template\Attributes\Component('test')]
class TestComponent
{
	public function render(string $children, string $arg): string
	{
		return "Hello {{$arg}} $children";
	}
}

it('can render a component', function () {
	$container = getContainer([
		AuthenticationServiceInterface::class => new class implements AuthenticationServiceInterface {

			public function isAuthenticated(): bool
			{
				return true;
			}

			public function isAuthorizedVia(BackedEnum ...$role): bool
			{
				return true;
			}
		},
		\Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface::class => new Variables(),
	]);
	$streamer = $container->get(Streaming::class);

	$class = new class {
		public function render(string $children, string $arg): string
		{
			return "<div>Hello {{$arg}} $children</div>";
		}
	};

	\olvlvl\ComposerAttributeCollector\Attributes::with(fn() => new \olvlvl\ComposerAttributeCollector\Collection(
		targetClasses: [
			\Bottledcode\SwytchFramework\Template\Attributes\Component::class => [
				[['test'], get_class($class)],
			],
		],
		targetMethods: [],targetProperties: []
	));

	$streamer->registerComponent(
		new \olvlvl\ComposerAttributeCollector\TargetClass(
			new \Bottledcode\SwytchFramework\Template\Attributes\Component('test'),
			get_class($class),
		)
	);
	$document = <<<HTML
<test arg="a">person</test>
HTML;

	$result = $streamer->compile($document);
	expect(trim($result))->toBe(
		<<<HTML
<div>Hello a person</div>
HTML
	);
});
