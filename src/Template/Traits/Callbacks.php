<?php

namespace Bottledcode\SwytchFramework\Template\Traits;

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Compiler;
use olvlvl\ComposerAttributeCollector\Attributes;
use Symfony\Component\Serializer\Serializer;

trait Callbacks
{
	private Serializer $serializer;
	private Compiler $compiler;

	private function rerender(string $target_id, array $withState = []): string
	{
		$attributes = Attributes::forClass(static::class);

		if (isset($this->serializer) && $_SERVER['HTTP_ACCEPT'] === 'application/json') {
			return $this->serializer->serialize($withState, 'json');
		}

		if (isset($this->serializer) && $_SERVER['HTTP_ACCEPT'] === 'application/xml') {
			return $this->serializer->serialize($withState, 'xml');
		}

		// look for the name of the component
		foreach ($attributes->classAttributes as $attribute) {
			if ($attribute instanceof Component) {
				$state = implode(
					' ',
					array_map(fn($key, $value) => "{$key}=\"{$value}\"", array_keys($withState), $withState)
				);
				break;
			}
		}

		if (!is_string($state)) {
			throw new \LogicException('Can not rerender a non-component');
		}

		if (!isset($this->compiler)) {
			throw new \LogicException('Can not rerender without a compiler');
		}

		$dom = $this->compiler->compile("<{$attribute->name} id='{{$target_id}}' {$state}></{$attribute->name}>");
		return $this->compiler->renderCompiledHtml($dom);
	}
}
