<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;

#[Component('Index')]
class RouterAppIndex
{
	use \Bottledcode\SwytchFramework\Template\Traits\RegularPHP;

	public function __construct()
	{
	}

	public function render()
	{
		$this->begin();
		?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <title>Index</title>
        </head>
        <body>
        <Route path="/test/:stuff" method="GET">
            <Test stuff="{{:stuff}}" nesting="3"></Test>
        </Route>
        <Route path="/" method="GET">
            <p>Test</p>
            <Test></Test>
        </Route>
        <Route path="/script">
            <script>
                console.log({{document.location.href}})
            </script>
        </Route>
        <route path="/form">
            <test nesting="0"></test>
        </route>
        <DefaultRoute>
            <Test></Test>
        </DefaultRoute>
        </body>
        </html>

		<?php
		return $this->end();
	}
}
