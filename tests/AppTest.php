<?php

use Bottledcode\SwytchFramework\Template\CompiledComponent;
use Bottledcode\SwytchFramework\Template\Compiler;

it('renders correctly', function () {
	global $container;
	$compiler = new Compiler(container: $container);
	$container->set('state_secret', 'secret');
	require_once __DIR__ . '/SimpleApp/App.php';
	require_once __DIR__ . '/SimpleApp/Index.php';
	require_once __DIR__ . '/SimpleApp/TodoItem.php';
	$compiler->registerComponent(Index::class);
	$compiler->registerComponent(App::class);
	$compiler->registerComponent(TodoItem::class);
	$container->set(App::class, new App($compiler));

	$app = $compiler->compileComponent(Index::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	expect($app->renderToString())->toOutput(__DIR__.'/SimpleApp/expected-output.html');
});
