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
	$container = getContainer();
	$compiler = new Compiler(container: $container);
	$compiler->registerComponent(RouterAppIndex::class);
	$compiler->registerComponent(Route::class);
	$compiler->registerComponent(DefaultRoute::class);
	$_SERVER['REQUEST_METHOD'] = Method::GET->value;
	$_SERVER['REQUEST_URI'] = '/';
	$container->set(Route::class, new Route());

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	assertMatchesHtmlSnapshot($app->renderToString());

	$container->set(Route::class, null);
});

it('can render variables', function () {
	routerProvider();
	$container = getContainer();
	$compiler = new Compiler(container: $container);
	require_once __DIR__ . '/RouterApp/Index.php';
	$compiler->registerComponent(RouterAppIndex::class);
	$compiler->registerComponent(Test::class);
	$compiler->registerComponent(Route::class);
	$compiler->registerComponent(DefaultRoute::class);
	$_SERVER['REQUEST_METHOD'] = Method::GET->value;
	$_SERVER['REQUEST_URI'] = '/test/123';
	$container->set(Route::class, new Route());
	Route::reset();

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	assertMatchesHtmlSnapshot($app->renderToString());

	$container->set(Route::class, null);
});

it('can render default route', function () {
	$container = getContainer();
	$compiler = new Compiler(container: $container);
	require_once __DIR__ . '/RouterApp/Index.php';
	$compiler->registerComponent(RouterAppIndex::class);
	$compiler->registerComponent(Route::class);
	$compiler->registerComponent(DefaultRoute::class);
	$_SERVER['REQUEST_METHOD'] = Method::GET->value;
	$_SERVER['REQUEST_URI'] = '/nowhere';
	$container->set(Route::class, new Route());
	Route::reset();

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	assertMatchesHtmlSnapshot($app->renderToString());

	$container->set(Route::class, null);
});

it('can render an htmx trigger', function () {
	$container = getContainer();
	$compiler = new Compiler(container: $container);
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
	$_SERVER['REQUEST_METHOD'] = Method::GET->value;
	$_SERVER['REQUEST_URI'] = '/test/123';
	Route::reset();

	$app = $compiler->compileComponent(RouterAppIndex::class);
	expect($app)->toBeInstanceOf(CompiledComponent::class);
	assertMatchesHtmlSnapshot($app->renderToString());
});
