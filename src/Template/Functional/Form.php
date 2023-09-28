<?php

namespace Bottledcode\SwytchFramework\Template\Functional;

use Bottledcode\SwytchFramework\Hooks\Common\Headers;
use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Traits\RegularPHP;

#[Component('form', isContainer: true)]
class Form
{
	use RegularPHP;

	public function render(Headers $response, string $id = null): string
	{
		$csrf = base64_encode(random_bytes(32));
		$response->setCookie('csrf_token', $csrf, new \DateTimeImmutable('+1 hour'));
		$this->begin();
		?>
        <input type='hidden' name='csrf_token' value='{<?= $csrf ?>}'/>
		<?= $id ? "<input type='hidden' name='target_id' value='{{$id}}' />" : '' ?>
        <children/>
		<?php
		return $this->end();
	}
}
