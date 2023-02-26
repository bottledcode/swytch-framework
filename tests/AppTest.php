<?php

use Bottledcode\SwytchFramework\Template\Compiler;

it('renders correctly', function () {
	$container = getContainer([
	]);
	\olvlvl\ComposerAttributeCollector\Attributes::with(fn() => new \olvlvl\ComposerAttributeCollector\Collection(
		targetClasses: [
			\Bottledcode\SwytchFramework\Template\Attributes\Component::class => [
				[['SimpleAppIndex'], \Bottledcode\SwytchFramework\Tests\SimpleApp\Index::class],
				[['TestApp'], \Bottledcode\SwytchFramework\Tests\SimpleApp\App::class],
				[['TodoItem'], \Bottledcode\SwytchFramework\Tests\SimpleApp\TodoItem::class],
			],
		],
		targetMethods: []
	));
	$compiler = new Compiler($container);
	$compiler->registerComponent(\Bottledcode\SwytchFramework\Tests\SimpleApp\App::class);
	$compiler->registerComponent(\Bottledcode\SwytchFramework\Tests\SimpleApp\TodoItem::class);
	$compiled = $compiler->compileComponent(\Bottledcode\SwytchFramework\Tests\SimpleApp\Index::class);
	\Spatie\Snapshots\assertMatchesHtmlSnapshot($compiled->renderToString());
});
