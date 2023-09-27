<?php

use Bottledcode\SwytchFramework\Template\Escapers\Variables;
use Bottledcode\SwytchFramework\Template\Interfaces\AuthenticationServiceInterface;
use Bottledcode\SwytchFramework\Template\Parser\StreamingCompiler;
use Psr\Log\NullLogger;

it('can parse some basic html', function () {
	$container = containerWithComponents([]);
	$streamer = $container->get(StreamingCompiler::class);
	$document = <<<HTML
<!DOCTYPE html>
<html>
<body>
<!-- this is a comment -->
<p class="active" data-platform='fake'>
	Hello world?!
</p>
</body>
</html>
HTML;
	$result = $streamer->compile($document);
	expect(trim($result))->toBe(trim($document));
});

it('can render a component', function () {
	$class = new class {
		public function render(string $arg): string
		{
			return "<div>Hello {{$arg}} <children/></div>";
		}
	};
	$container = containerWithComponents(['test' => $class]);
	$streamer = $container->get(StreamingCompiler::class);

	$document = <<<HTML
<test arg="a">person</test>
HTML;

	$result = $streamer->compile($document);
	expect(trim($result))->toBe(
		<<<HTML
<div>Hello a person</div>
HTML
	);
});

it('can render nested components', function() {
	$parent = new class {
		public function render(): string
		{
			return "<div><child/></div>";
		}
	};

	$child = new class {
		public function render(): string
		{
			return "<div>child</div>";
		}
	};

	$container = containerWithComponents(['parent' => $parent, 'child' => $child]);
	$streamer = $container->get(StreamingCompiler::class);

	$document = <<<HTML
<div>I am a <parent/></div>
HTML;

	$result = $streamer->compile($document);

	expect($result)->toMatchHtmlSnapshot();
});

it('can handle providers', function() {
	$request = new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost/test/fancy-id');
	$container = containerWithComponents(['route' => new \Bottledcode\SwytchFramework\Template\Functional\Route($request), 'user' => new class {
		public function render(string $id): string
		{
			return "<div>User {{$id}}</div>";
		}
	}]);
	$container->set(\Psr\Http\Message\ServerRequestInterface::class, $request);
	$streamer = $container->get(StreamingCompiler::class);

	$document= <<<HTML
<div>
<route path="/test/:id">
	<User id="{{:id}}"></User>
</route>
<route path="/">test</route>
</div>
HTML;

	$result = $streamer->compile($document);

	expect($result)->toMatchHtmlSnapshot();

});

it('can render a full html page', function() {
	$container = containerWithComponents(['test' => new class {
		public function render(bool $yay = false, string $name = '&world'): string
		{
			if($yay) {
				return "<script>console.log('{{$name}}')</script>";
			}
			return "<div>Hello {{$name}}</div>";
		}
	}]);
	$streamer = $container->get(StreamingCompiler::class);

	$document = <<<HTML
<!DOCTYPE html>
<html>
<head>
	<title>Test</title>
	<script>console.log("<test>")</script>
	<style> .con {{text-after-overflow: none; display: {%%^#@#$}}} </style>
</head>
<body>
<test />
<test name="world" />
<test name="ted" yay />
</body>
HTML;


	expect($streamer->compile($document))->toMatchHtmlSnapshot();
});
