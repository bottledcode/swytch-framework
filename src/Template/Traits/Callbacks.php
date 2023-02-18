<?php

namespace Bottledcode\SwytchFramework\Template\Traits;

trait Callbacks {
	public function __get(string $name)
	{
		if (method_exists($this, $name)) {
			return '__TRIGGER__' . $name;
		}
		throw new \Exception("Undefined property: " . static::class . "::$name");
	}

	public function __set(string $name, mixed $value)
	{
		throw new \Exception("Undefined property: " . static::class . "::$name");
	}

	public function __isset(string $name): bool
	{
		return method_exists($this, $name);
	}
}
