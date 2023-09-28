<?php

use Bottledcode\SwytchFramework\Template\Parser\StreamingCompiler;

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

it('can render nested components', function () {
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

it('can handle providers', function () {
	$request = new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost/test/fancy-id');
	$container = containerWithComponents([
		'route' => new \Bottledcode\SwytchFramework\Template\Functional\Route($request),
		'user' => new class {
			public function render(string $id): string
			{
				return "<div>User {{$id}}</div>";
			}
		}
	]);
	$container->set(\Psr\Http\Message\ServerRequestInterface::class, $request);
	$streamer = $container->get(StreamingCompiler::class);

	$document = <<<HTML
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

it('can render a full html page', function () {
	$container = containerWithComponents([
		'test' => new class {
			public function render(bool $yay = false, string $name = '&world'): string
			{
				if ($yay) {
					return "<script>console.log('{{$name}}')</script>";
				}
				return "<div>Hello {{$name}}</div>";
			}
		}
	]);
	$streamer = $container->get(StreamingCompiler::class);

	$document = <<<HTML
<!DOCTYPE html>
<html lang="{en}" class="h-full">
<head>
	<title>{Test}</title>
	<script>console.log("<test>")</script>
	<style> .con {{text-after-overflow: none; display: {%%^#@#$};}} </style>
</head>
<body>
<a href="{http://localhost}"></a>
<a href="http://localhost/{a test thing}"></a>
<noscript>{this is a message}</noscript>
<test />
<test name="world" />
<test name="ted" yay />
</body>
HTML;


	expect($streamer->compile($document))->toMatchHtmlSnapshot();
});

it('does not render non-rendered components', function () {
	$request = new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost/');
	$container = containerWithComponents([
		'route' => new \Bottledcode\SwytchFramework\Template\Functional\Route($request),
		'user' => new class {
			public function render(string $id): string
			{
				throw new Exception('This should not be called');
			}
		}
	]);
	$container->set(\Psr\Http\Message\ServerRequestInterface::class, $request);
	$streamer = $container->get(StreamingCompiler::class);

	$document = <<<HTML
<div>
<Route
				method="GET"
				path="/dashboard"
		>
	<User id="{{:id}}"></User>
</route>
<route path="/">test</route>
</div>
HTML;

	$result = $streamer->compile($document);

	expect($result)->toMatchHtmlSnapshot();
});

it('does not overwrite variables if the same variables are used in a child', function () {
	$container = containerWithComponents([
		'test' => new class {
			public function render(string $name = 'world'): string
			{
				return "<div>Hello {{$name}} and <children/></div>";
			}
		}
	]);
	$streamer = $container->get(StreamingCompiler::class);

	$document = <<<HTML
<test name="Zavier"><test name="Rob" /></test>
HTML;

	$result = $streamer->compile($document);

	expect($result)->toMatchHtmlSnapshot();
});

it('can render a csfr token', function () {
	$container = containerWithComponents([
		'csrf' => new class {
			public function render(): string
			{
				return '<input type="hidden" name="csrf" value="1234" />';
			}
		},
		'form,1' => new \Bottledcode\SwytchFramework\Template\Functional\Form(),
	]);
	$streamer = $container->get(StreamingCompiler::class);

	$document = <<<HTML
<form hx-post="/" id="tester">
<input type="hidden" name="hello" value="world" />
</form>
HTML;

	$result = $streamer->compile($document);
	$start = strpos($result, "value='") + 7;
	$end = strpos($result, "'", $start);
	$result = substr_replace($result, '1234', $start, $end - $start);
	expect($result)->toMatchHtmlSnapshot()
		->and(
			$container->get(\Bottledcode\SwytchFramework\Hooks\Common\Headers::class)->postprocess(
				new \Nyholm\Psr7\Response()
			)->getHeader('Set-Cookie')[0]
		)->toStartWith('csrf_token=');
});
