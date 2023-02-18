<?php

use Bottledcode\SwytchFramework\Router\Attributes\Route;
use Bottledcode\SwytchFramework\Router\Method;
use Bottledcode\SwytchFramework\Template\Attributes\Component;

#[Component('Index')]
class RouterAppIndex {
	public function render() {
		/** @lang HTML */
		return <<<HTML
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Index</title>
	</head>
<body>
	<Route path="/" method="GET" render="<Test />" />
	<Route path="/test/:stuff" method="GET" render="<Test stuff='{:stuff}' />" />
	<DefaultRoute render="<Test />" />
</body>
</html>
HTML;
	}

	public function __construct() {}
}
