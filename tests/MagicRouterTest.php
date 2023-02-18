<?php

use Bottledcode\SwytchFramework\Router\Attributes\Route;
use Bottledcode\SwytchFramework\Router\Method;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\Collection;

readonly class RandomType {
	public function __construct(public string $todo, public DateTimeInterface $time, public int $woah) {}
}

class HandlesRoutes {
	#[Route(Method::POST, '/api/test/:todo')]
	public function complexRoute(RandomType $type, string $mysteriousBookEntity): string {
		return "$type->todo/$type->woah/$mysteriousBookEntity/".$type->time->format('Y-m-d');
	}

	#[Route(Method::GET, '/api/test')]
	public function simpleRoute(): string {
		return 'simple';
	}

	#[Route(Method::GET, '/api/test/:todo')]
	public function simpleRouteWithParam(string $todo): string {
		return $todo;
	}
}

beforeAll(function() {
	Attributes::with(fn() => new Collection(targetClasses: [], targetMethods: [
		Route::class => [
			[[Method::POST, '/api/test/:todo'],HandlesRoutes::class,'complexRoute'],
			[[Method::GET, '/api/test'],HandlesRoutes::class,'simpleRoute'],
			[[Method::GET, '/api/test/:todo'],HandlesRoutes::class,'simpleRouteWithParam'],
		]
	]));
});

it('should match a simple route', function() {
	global $container;
	$_SERVER['REQUEST_METHOD'] = Method::GET->value;
	$_SERVER['REQUEST_URI'] = '/api/test';
	$route = new \Bottledcode\SwytchFramework\Router\MagicRouter($container, 'null');
	$result = $route->go();
	expect($result)->toBe('simple');
});

it('should handle simple parameters', function () {
	global $container;
	$_SERVER['REQUEST_METHOD'] = Method::GET->value;
	$_SERVER['REQUEST_URI'] = '/api/test/tada';
	$route = new \Bottledcode\SwytchFramework\Router\MagicRouter($container, 'null');
	$result = $route->go();
	expect($result)->toBe('tada');
});

it('can handle complex parameters', function () {
	global $container;
	$_SERVER['REQUEST_METHOD'] = Method::POST->value;
	$_SERVER['REQUEST_URI'] = '/api/test/tada';
	$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
	$_POST = ['time' => '2021-08-01', 'woah' => 2021, 'mysteriousBookEntity' => 'bookentity yo', 'misc' => 'misc'];
	$_COOKIE = ['csrf_token' => 'token'];
	$_POST['csrf_token'] = 'token';
	$container->set(\Symfony\Component\Serializer\Serializer::class, new \Symfony\Component\Serializer\Serializer([new \Symfony\Component\Serializer\Normalizer\ObjectNormalizer(), new \Symfony\Component\Serializer\Normalizer\DateTimeNormalizer()]));
	$route = new \Bottledcode\SwytchFramework\Router\MagicRouter($container, 'null');
	$result = $route->go();
	expect($result)->toBe('tada/2021/bookentity yo/2021-08-01');
});
