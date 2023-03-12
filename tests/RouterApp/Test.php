<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Traits\Htmx;
use Bottledcode\SwytchFramework\Template\Traits\RegularPHP;

#[Component('Test')]
class Test
{
	use Htmx;
	use RegularPHP;

	public function onKeyUp(string $event): void
	{
	}

	public function render(int $stuff = 5, int $nesting = 0)
	{
		if ($nesting === 0) {
			$this->begin();
			?>
            <form hx-post="/somewhere">
                <input type="text" name="test" value="test">
            </form>
			<?php
			return $this->end();
		}

		$this->begin();
		?>

        <div>
            <h1>{<?= $stuff ?>}</h1>
            <test stuff="{<?= $stuff + 1 ?>}" nesting="{<?= $nesting - 1 ?>}"></test>
        </div>

		<?php
		return $this->end();
	}
}
