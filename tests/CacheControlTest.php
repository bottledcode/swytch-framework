<?php

namespace Bottledcode\SwytchFramework\Tests;

use Bottledcode\SwytchFramework\CacheControl\Builder;
use Bottledcode\SwytchFramework\CacheControl\NeverCache;

use Bottledcode\SwytchFramework\CacheControl\Queue;

use function Spatie\Snapshots\assertMatchesObjectSnapshot;

$headers = [];

function resetHeader(): void {
	global $headers;
	$headers = [];
}

expect()->extend('toHaveMethod', function ($method) {
	expect(method_exists($this->value, $method))->toBeTrue();
});

expect()->extend('toMatchHeader', function () {
	global $headers;
	expect($this->value)->toHaveMethod('render');
	$this->value->render('test');
	assertMatchesObjectSnapshot((object)$headers);
	resetHeader();
});

beforeEach(function () {
	global $headers;
	$headers = [];
	Builder::setHeaderFunc(function (...$args) use (&$headers) {
		$headers[] = $args;
	});
});

test('no-store', function () {
	$cache = Builder::neverCache('test');
	expect($cache)->toMatchHeader();
});

test('immutable', function() {
	$cache = Builder::willChange('test')->never();
	expect($cache->notShared())->toMatchHeader();
	expect($cache->shared())->toMatchHeader();
	expect($cache->shared()->differentSharedAge(60))->toMatchHeader();
});

test('periodically-often', function() {
	$cache = Builder::willChange('test')->often();
	expect($cache->notShared())->toMatchHeader();
	expect($cache->shared())->toMatchHeader();
	expect($cache->shared()->differentSharedAge(60))->toMatchHeader();
});

test('periodically-check-always', function() {
	$cache = Builder::willChange('test')->periodically(60)->alwaysCheck();
	expect($cache->notShared())->toMatchHeader();
	expect($cache->shared())->toMatchHeader();
	expect($cache->shared()->differentSharedAge(60))->toMatchHeader();
});

test('periodically-check-stale', function() {
	$cache = Builder::willChange('test')->periodically(60)->ifStale();
	expect($cache->notShared())->toMatchHeader();
	expect($cache->shared())->toMatchHeader();
	expect($cache->shared()->differentSharedAge(60))->toMatchHeader();
});

test('periodically-never-check',function() {
	$cache = Builder::willChange('test')->periodically(60)->neverCheck();
	expect($cache->notShared())->toMatchHeader();
	expect($cache->shared())->toMatchHeader();
	expect($cache->shared()->differentSharedAge(60))->toMatchHeader();
});

it('always chooses the least strict', function() {
	$controller = new Queue();
	$controller->enqueue(Builder::neverCache('never cache'));
	$controller->enqueue(Builder::willChange('immutable not shared')->never()->notShared());
	$controller->enqueue(Builder::willChange('immutable shared')->never()->shared());
	$controller->enqueue(Builder::willChange('immutable shared different age')->never()->shared()->differentSharedAge(60));
	$controller->enqueue(Builder::willChange('often changes: not shared')->often()->notShared());
	$controller->enqueue(Builder::willChange('often changes: shared')->often()->shared());
	$controller->enqueue(Builder::willChange('often changes: shared different age')->often()->shared()->differentSharedAge(60));
	$controller->enqueue(Builder::willChange('periodically changes but never check: not shared')->periodically(60)->neverCheck()->notShared());
	$controller->enqueue(Builder::willChange('periodically changes but never check: shared')->periodically(60)->neverCheck()->shared());
	$controller->enqueue(Builder::willChange('periodically changes but never check: shared different age')->periodically(60)->neverCheck()->shared()->differentSharedAge(60));
	$controller->enqueue(Builder::willChange('periodically changes but always check: not shared')->periodically(60)->alwaysCheck()->notShared());
	$controller->enqueue(Builder::willChange('periodically changes but always check: shared')->periodically(60)->alwaysCheck()->shared());
	$controller->enqueue(Builder::willChange('periodically changes but always check: shared different age')->periodically(60)->alwaysCheck()->shared()->differentSharedAge(60));
	$controller->enqueue(Builder::willChange('periodically changes but if stale: not shared')->periodically(60)->ifStale()->notShared());
	$controller->enqueue(Builder::willChange('periodically changes but if stale: shared')->periodically(60)->ifStale()->shared());
	$controller->enqueue(Builder::willChange('periodically changes but if stale: shared different age')->periodically(60)->ifStale()->shared()->differentSharedAge(60));
	assertMatchesObjectSnapshot((object)$controller->getSortedQueue());
});
