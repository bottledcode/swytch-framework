<?php

namespace Bottledcode\SwytchFramework\Tests\SimpleApp;

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Traits\RegularPHP;

#[Component('blah:label')]
class Label
{
	use RegularPHP;

	public function render(string $for): string
	{
		$this->begin();
		?>
        <label for="<?= $for ?>">
            <children></children>
        </label>
		<?php
		return $this->end();
	}
}
