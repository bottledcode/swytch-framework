<?php

use Bottledcode\SwytchFramework\Cache\AbstractCache;
use Bottledcode\SwytchFramework\Cache\CachePublic;
use Bottledcode\SwytchFramework\Cache\Control\Tokenizer;
use Bottledcode\SwytchFramework\Cache\MaxAge;
use Bottledcode\SwytchFramework\Cache\NeverCache;
use Bottledcode\SwytchFramework\Cache\NeverChanges;
use Bottledcode\SwytchFramework\Cache\Revalidate;
use Bottledcode\SwytchFramework\Cache\RevalidationEnum;
use Bottledcode\SwytchFramework\Cache\UserSpecific;

function render(Tokenizer $tokenizer, AbstractCache ...$directives): string
{
	foreach ($directives as $directive) {
		$tokenizer = $directive->tokenize($tokenizer);
	}

	return $tokenizer->render();
}

it('can describe a simple public cache', function () {
	$tokenizer = new Tokenizer();
	expect(render($tokenizer))->toBe('public');
});

it('can handle max age rules', function () {
	$tokenizer = new Tokenizer();

	$directives[] = new NeverChanges();

	$directives[] = new MaxAge(3000);
	expect(render($tokenizer, ...$directives))->toBe("public max-age=3000");

	$directives[] = new MaxAge(300);
	expect(render($tokenizer, ...$directives))->toBe("public max-age=300");

	$directives[] = new MaxAge(600);
	expect(render($tokenizer, ...$directives))->toBe("public max-age=300");

	$directives[] = new MaxAge(200, true);
	expect(render($tokenizer, ...$directives))->toBe("public max-age=300 s-maxage=200");

	$directives[] = new NeverCache();
	$directives[] = new NeverChanges();
	expect(render($tokenizer, ...$directives))->toBe("private no-store");
});

it('can handle revalidation rules', function () {
	$tokenizer = new Tokenizer();

	$directives[] = new NeverChanges();
	expect(render($tokenizer, ...$directives))->toBe("public max-age=604800 immutable");
	$directives[] = new Revalidate(RevalidationEnum::AfterError, 300);
	expect(render($tokenizer, ...$directives))->toBe("public stale-if-error=300");
	$directives[] = new Revalidate(RevalidationEnum::AfterStale, 300);
	expect(render($tokenizer, ...$directives))->toBe("public stale-while-revalidate=300 stale-if-error=300");
	$directives[] = new Revalidate(RevalidationEnum::WhenStaleProxies);
	expect(render($tokenizer, ...$directives))->toBe(
		"public proxy-revalidate stale-while-revalidate=300 stale-if-error=300"
	);
	$directives[] = new Revalidate(RevalidationEnum::WhenStale);
	expect(render($tokenizer, ...$directives))->toBe("public must-revalidate proxy-revalidate");
	$directives[] = new Revalidate(RevalidationEnum::EveryRequest);
	expect(render($tokenizer, ...$directives))->toBe("public no-cache");

	$directives[] = new Revalidate(RevalidationEnum::WhenStale);
	$directives[] = new Revalidate(RevalidationEnum::WhenStaleProxies);
	$directives[] = new Revalidate(RevalidationEnum::AfterStale, 300);
	$directives[] = new Revalidate(RevalidationEnum::AfterError, 300);
	expect(render($tokenizer, ...$directives))->toBe("public no-cache");

	$directives[] = new MaxAge(300);
	expect(render($tokenizer, ...$directives))->toBe("public max-age=300 no-cache");

	$directives[] = new NeverCache();
	expect(render($tokenizer, ...$directives))->toBe("private no-store");

	$directives[] = new Revalidate(RevalidationEnum::EveryRequest);
	expect(render($tokenizer, ...$directives))->toBe("private no-store");
});

it('can handle public/private', function () {
	$tokenizer = new Tokenizer();

	$directives[] = new CachePublic();
	expect(render($tokenizer, ...$directives))->toBe("public");

	$directives[] = new UserSpecific();
	expect(render($tokenizer, ...$directives))->toBe("private");

	$directives[] = new CachePublic();
	expect(render($tokenizer, ...$directives))->toBe("private");
});
