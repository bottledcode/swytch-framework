<?php

use Bottledcode\SwytchFramework\Router\Method;
use Bottledcode\SwytchFramework\Template\CompiledComponent;
use Bottledcode\SwytchFramework\Template\Compiler;
use Bottledcode\SwytchFramework\Template\Functional\DefaultRoute;
use Bottledcode\SwytchFramework\Template\Functional\Route;

use function Spatie\Snapshots\assertMatchesHtmlSnapshot;
use function Spatie\Snapshots\assertMatchesTextSnapshot;

require_once __DIR__ . '/RouterApp/Index.php';
require_once __DIR__ . '/RouterApp/Test.php';

function routerProvider() {
	\olvlvl\ComposerAttributeCollector\Attributes::with(fn() => new \olvlvl\ComposerAttributeCollector\Collection(
		targetClasses: [
			\Bottledcode\SwytchFramework\Template\Attributes\Component::class => [
				[['Index'], RouterAppIndex::class],
				[['Test'], Test::class],
				[['Route'], Route::class],
				[['DefaultRoute'], DefaultRoute::class],
			],
		],
		targetMethods: []
	));
}

it('can be used to route to other components based on request', function () {
	routerProvider();
	$container = getContainer();
	$compiler = new Compiler(container: $container);
	$container->set(Compiler::class, $compiler);
	$compiler->registerComponent(RouterAppIndex::class);
	$compiler->registerComponent(Route::class);
	$compiler->registerComponent(DefaultRoute::class);
	$request = (new \Nyholm\Psr7\Factory\Psr17Factory())->createServerRequest(Method::GET->value, '/');
	$container->set(\Psr\Http\Message\ServerRequestInterface::class, $request);
	$container->set(Route::class, new Route($request));

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	assertMatchesHtmlSnapshot($app->renderToString());

	$container->set(Route::class, null);
});

it('can render variables', function () {
	routerProvider();
	$container = getContainer();
	$compiler = new Compiler(container: $container);
	$container->set(Compiler::class, $compiler);
	require_once __DIR__ . '/RouterApp/Index.php';
	$compiler->registerComponent(RouterAppIndex::class);
	$compiler->registerComponent(Test::class);
	$compiler->registerComponent(Route::class);
	$compiler->registerComponent(DefaultRoute::class);
	$request = (new \Nyholm\Psr7\Factory\Psr17Factory())->createServerRequest(Method::GET->value, '/test/123');
	$container->set(\Psr\Http\Message\ServerRequestInterface::class, $request);
	$container->set(Route::class, new Route($request));
	Route::reset();

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	assertMatchesHtmlSnapshot($app->renderToString());

	$container->set(Route::class, null);
});

it('can render default route', function () {
	routerProvider();
	$container = getContainer();
	$compiler = new Compiler(container: $container);
	$container->set(Compiler::class, $compiler);
	require_once __DIR__ . '/RouterApp/Index.php';
	$compiler->registerComponent(RouterAppIndex::class);
	$compiler->registerComponent(Route::class);
	$compiler->registerComponent(DefaultRoute::class);
	$request = (new \Nyholm\Psr7\Factory\Psr17Factory())->createServerRequest(Method::GET->value, '/nowhere');
	$container->set(\Psr\Http\Message\ServerRequestInterface::class, $request);
	$container->set(Route::class, new Route($request));
	Route::reset();

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	assertMatchesHtmlSnapshot($app->renderToString());

	$container->set(Route::class, null);
});

it('can render script tags', function () {
	routerProvider();
	$container = getContainer();
	$compiler = new Compiler(container: $container);
	$container->set(Compiler::class, $compiler);
	require_once __DIR__ . '/RouterApp/Index.php';
	$compiler->registerComponent(RouterAppIndex::class);
	$compiler->registerComponent(Route::class);
	$compiler->registerComponent(DefaultRoute::class);
	$request = (new \Nyholm\Psr7\Factory\Psr17Factory())->createServerRequest(Method::GET->value, '/script');
	$container->set(\Psr\Http\Message\ServerRequestInterface::class, $request);
	$container->set(Route::class, new Route($request));
	Route::reset();

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	assertMatchesHtmlSnapshot($app->renderToString());

	$container->set(Route::class, null);
});

it('can render an htmx trigger', function () {
	routerProvider();
	$container = getContainer();
	$compiler = new Compiler(container: $container);
	$container->set(Compiler::class, $compiler);
	require_once __DIR__ . '/RouterApp/Index.php';
	require_once __DIR__ . '/RouterApp/Test.php';
	$container->set(
		\Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface::class,
		new \Bottledcode\SwytchFramework\Template\ReferenceImplementation\ValidatedState(
			'123',
			$container->get(\Symfony\Component\Serializer\Serializer::class)
		)
	);
	$compiler->registerComponent(RouterAppIndex::class);
	$compiler->registerComponent(Route::class);
	$compiler->registerComponent(DefaultRoute::class);
	$compiler->registerComponent(Test::class);
	$request = (new \Nyholm\Psr7\Factory\Psr17Factory())->createServerRequest(Method::GET->value, '/form');
	$container->set(\Psr\Http\Message\ServerRequestInterface::class, $request);
	$container->set(Route::class, new Route($request));
	Route::reset();

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	assertMatchesHtmlSnapshot($app->renderToString());
});
