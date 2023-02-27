<?php

namespace Bottledcode\SwytchFramework\Tests;

use Bottledcode\SwytchFramework\CacheControl\Builder;
use Bottledcode\SwytchFramework\CacheControl\NeverCache;

use function Spatie\Snapshots\assertMatchesObjectSnapshot;

test('no-store', function () {
	$cache = Builder::neverCache('test');
	expect($cache)->toBeInstanceOf(NeverCache::class);
	assertMatchesObjectSnapshot((object)$cache->getHeaders('test'));
});

test('immutable', function(){
	$builder = Builder::willChange('test')->never();
	assertMatchesObjectSnapshot((object)$builder->notShared()->getHeaders('test'));
	assertMatchesObjectSnapshot((object)$builder->shared());
	headers_list()
});
