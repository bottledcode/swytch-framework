<?php

namespace Bottledcode\SwytchFramework\Tests\SimpleApp;

use Bottledcode\SwytchFramework\Template\Attributes\Component;

#[Component('SimpleAppIndex')]
class Index
{
	public function render(): string
	{
		$language = $props['language'] ?? 'en';
		$title = $props['title'] ?? '<Untitled>';
		$name = "Rob<>is awesome";
		return <<<HTML
<!DOCTYPE html>
<html lang="{{<$language>}}">
<head>
<title>{{$title}}</title>
</head>
<body>
<TestApp id="app" name="{{$name}}"></TestApp>
</body>
</html>
HTML;
	}
}
