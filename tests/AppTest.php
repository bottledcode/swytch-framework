<?php

use Bottledcode\SwytchFramework\Template\CompiledComponent;
use Bottledcode\SwytchFramework\Template\Compiler;

it('renders correctly', function () {
	global $container;
	$compiler = new Compiler(container: $container);
	require_once __DIR__ . '/SimpleApp/App.php';
	require_once __DIR__ . '/SimpleApp/Index.php';
	$compiler->registerComponent(Index::class);
	$compiler->registerComponent(App::class);

	$app = $compiler->compileComponent(Index::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	expect($app->renderToString())->toOutput(__DIR__.'/SimpleApp/expected-output.html');
});
