<?php

use Bottledcode\SwytchFramework\Router\Method;
use Bottledcode\SwytchFramework\Template\CompiledComponent;
use Bottledcode\SwytchFramework\Template\Compiler;
use Bottledcode\SwytchFramework\Template\Functional\DefaultRoute;
use Bottledcode\SwytchFramework\Template\Functional\Route;

it('can be used to route to other components based on request', function () {
	global $container;
	$compiler = new Compiler(container: $container);
	require_once __DIR__ .'/RouterApp/Index.php';
	$compiler->registerComponent(RouterAppIndex::class);
	$compiler->registerComponent(Route::class);
	$_SERVER['REQUEST_METHOD'] = Method::GET->value;
	$_SERVER['REQUEST_URI'] = '/';
	$container->set(Route::class, new Route($compiler));

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	expect($app->renderToString())->toOutput(__DIR__.'/RouterApp/expected-output-1.html');

	$container->set(Route::class, null);
});

it('can render variables', function () {
	global $container;
	$compiler = new Compiler(container: $container);
	require_once __DIR__ .'/RouterApp/Index.php';
	$compiler->registerComponent(RouterAppIndex::class);
	$compiler->registerComponent(Route::class);
	$_SERVER['REQUEST_METHOD'] = Method::GET->value;
	$_SERVER['REQUEST_URI'] = '/test/123';
	$container->set(Route::class, new Route($compiler));

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	expect($app->renderToString())->toOutput(__DIR__.'/RouterApp/expected-output-2.html');

	$container->set(Route::class, null);
});

it('can render default route', function () {
	global $container;
	$compiler = new Compiler(container: $container);
	require_once __DIR__ .'/RouterApp/Index.php';
	$compiler->registerComponent(RouterAppIndex::class);
	$compiler->registerComponent(Route::class);
	$compiler->registerComponent(DefaultRoute::class);
	$_SERVER['REQUEST_METHOD'] = Method::GET->value;
	$_SERVER['REQUEST_URI'] = '/nowhere';
	$container->set(Route::class, new Route());

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	expect($app->renderToString())->toOutput(__DIR__.'/RouterApp/expected-output-3.html');

	$container->set(Route::class, null);
});
