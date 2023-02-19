<?php

namespace Bottledcode\SwytchFramework\Template\Traits;

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Compiler;
use Bottledcode\SwytchFramework\Template\Enum\HtmxSwap;
use olvlvl\ComposerAttributeCollector\Attributes;
use Symfony\Component\Serializer\Serializer;

trait Htmx
{
	private Serializer $serializer;
	private Compiler $compiler;

	private function html(string $html): string
	{
		$dom = $this->compiler->compile($html);
		return $this->compiler->renderCompiledHtml($dom);
	}

	/**
	 * Allows you to do a client-side redirect that does not do a full page reload
	 *
	 * @param string $url The url to redirect to
	 * @param string|null $handler
	 * @param string|null $target
	 * @param HtmxSwap|null $swap
	 * @param array|null $values
	 * @param array|null $headers
	 * @return void
	 * @throws \JsonException
	 */
	private function redirectClient(
		string $url,
		string|null $handler = null,
		string|null $target = null,
		HtmxSwap|null $swap = null,
		array|null $values = null,
		array|null $headers = null
	): void {
		if (!empty(array_filter([$handler, $target, $swap, $values, $headers]))) {
			$attributes = json_encode(
				array_filter(compact('url', 'handler', 'target', 'swap', 'values', 'headers')),
				JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
			);
			header("HX-Location: $attributes");
			return;
		}
		header("HX-Redirect: $url");
	}

	/**
	 * the client side will do a full refresh of the page
	 *
	 * @return void
	 */
	private function refreshClient(): void
	{
		header('HX-Refresh: true');
	}

	/**
	 * @param non-empty-string|false $url
	 * @return void
	 */
	private function replaceUrl(string|false $url): void
	{
		$url = $url ?: "false";
		header("HX-Replace-Url: $url");
	}

	/**
	 * Allows you to specify how the response will be swapped. See hx-swap for possible values
	 *
	 * @param HtmxSwap $swap
	 * @return void
	 */
	private function reswap(HtmxSwap $swap): void
	{
		header("HX-Reswap: {$swap->value}");
	}

	/**
	 * A CSS selector that updates the target of the content update to a different element on the page
	 *
	 * @param non-empty-string $target
	 * @return void
	 */
	private function retarget(string $target): void
	{
		header("HX-Retarget: $target");
	}

	/**
	 * @param array<string> $events
	 * @return void
	 */
	private function trigger(array $events): void
	{
		$encoded = json_encode($events, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
		header("Hx-Trigger: $encoded");
	}

	/**
	 * @param non-empty-string|false $url
	 * @return void
	 */
	private function historyPush(string|false $url): void
	{
		$url = $url ?: "false";
		header("HX-Push-Url: $url");
	}

	private function rerender(string $target_id, array $withState = [], string $prependHtml = ''): string
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

		header('hx-retarget: #' . $target_id);
		header('hx-reswap: outerHTML');
		$dom = $this->compiler->compile("$prependHtml\n<{$attribute->name} id='{{$target_id}}' {$state}></{$attribute->name}>");
		return $this->compiler->renderCompiledHtml($dom);
	}
}
