<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\ComponentInterface;

#[Component('Index')]
class Index {
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
<app name="{{$name}}" />
</body>
</html>
HTML;
	}
}
