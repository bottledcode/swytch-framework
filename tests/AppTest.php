<?php

use Bottledcode\SwytchFramework\Template\Compiler;

it('renders correctly', function () {
	$container = getContainer([
	]);
	\olvlvl\ComposerAttributeCollector\Attributes::with(fn() => new \olvlvl\ComposerAttributeCollector\Collection(
		targetClasses: [
			\Bottledcode\SwytchFramework\Template\Attributes\Component::class => [
				[['todoitem'], \Bottledcode\SwytchFramework\Tests\SimpleApp\Index::class],
				[['TestApp'], \Bottledcode\SwytchFramework\Tests\SimpleApp\App::class],
				[['TodoItem'], \Bottledcode\SwytchFramework\Tests\SimpleApp\TodoItem::class],
				[['test:label'], \Bottledcode\SwytchFramework\Tests\SimpleApp\Label::class],
			],
		],
		targetMethods: []
	));
	$compiler = new Compiler($container);
	$compiler->registerComponent(\Bottledcode\SwytchFramework\Tests\SimpleApp\App::class);
	$compiler->registerComponent(\Bottledcode\SwytchFramework\Tests\SimpleApp\TodoItem::class);
	$compiler->registerComponent(\Bottledcode\SwytchFramework\Tests\SimpleApp\Label::class);
	$compiled = $compiler->compileComponent(\Bottledcode\SwytchFramework\Tests\SimpleApp\Index::class);
	\Spatie\Snapshots\assertMatchesHtmlSnapshot($compiled->renderToString());
});

it('fails when missing env vars', function () {
	expect(fn() =>
	(new \Bottledcode\SwytchFramework\App(
			true,
			\Bottledcode\SwytchFramework\Tests\SimpleApp\App::class,
			registerErrorHandler: false
		))->run()
	)->toThrow(RuntimeException::class);

})->skip();
