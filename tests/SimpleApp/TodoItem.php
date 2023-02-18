<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Traits\FancyClasses;

#[Component('TodoItem')]
class TodoItem {
	use FancyClasses;
	use AllowCallbacks;
	use State;

	public function __construct() {}

	public function submit(array $fields) {
		$this->state('title', $fields['title']);
	}

	public function render(bool $completed, bool $editing, string $title, Callback $onToggle, Callback $onDestroy) {
		return <<<HTML
<li class="{{$this->classNames(compact('completed', 'editing'))}}">
	<div class="view">
		<input class="toggle" type="checkbox" checked="{{$completed}}" onchange="{{$onToggle}}">
		<label>{{$title}}</label>
		<button class="destroy" onclick="{{$onDestroy}}"></button>
	</div>
	<input class="edit" name="title" value="{{$this->state('title')}} onblur="{{$this->submit}}"">
</li>
HTML;
	}
}
