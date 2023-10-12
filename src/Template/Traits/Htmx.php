<?php

namespace Bottledcode\SwytchFramework\Template\Traits;

use Bottledcode\SwytchFramework\Hooks\Common\Headers;
use Bottledcode\SwytchFramework\Template\Enum\HtmxSwap;
use Bottledcode\SwytchFramework\Template\Parser\StreamingCompiler;
use JsonException;
use LogicException;
use Masterminds\HTML5\Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Serializer\Serializer;

trait Htmx
{
	private readonly Serializer $serializer;
	private readonly StreamingCompiler $compiler;
	private readonly Headers $headers;

	/**
	 * Don't perform any escaping or rendering on the given HTML
	 * @param string $html The HTML to render
	 * @return string
	 */
	private function dangerous(string $html): string
	{
		static $boundary = null;
		if ($boundary === null) {
			$boundary = "\0";
		}

		return $boundary . $html . $boundary;
	}

	/**
	 * Render the given HTML and any components it contains
	 * @param string $html
	 * @return string
	 * @throws Exception
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	private function html(string $html): string
	{
		if (empty($this->compiler)) {
			throw new LogicException('Can not render HTML without a compiler in ' . static::class);
		}

		return $this->compiler->compile($html);
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
	 * @throws JsonException
	 */
	private function redirectClient(
		string $url,
		string|null $handler = null,
		string|null $target = null,
		HtmxSwap|null $swap = null,
		array|null $values = null,
		array|null $headers = null
	): void {
		if (empty($this->headers)) {
			throw new LogicException('Can not redirect without Headers in ' . static::class);
		}
		if (!empty(array_filter([$handler, $target, $swap, $values, $headers]))) {
			$attributes = json_encode(
				array_filter(compact('url', 'handler', 'target', 'swap', 'values', 'headers')),
				JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
			);
			$this->headers->setHeader('HX-Location', $attributes);
			return;
		}
		header("HX-Redirect: {$url}");
	}

	/**
	 * the client side will do a full refresh of the page
	 *
	 * @param bool $value
	 * @return void
	 */
	private function refreshClient(bool $value = true): void
	{
		if (empty($this->headers)) {
			throw new LogicException('Can not redirect without Headers in ' . static::class);
		}
		$this->headers->setHeader('HX-Refresh', $value ? 'true' : 'false', true);
	}

	/**
	 * Replace the URL in the browser's address bar. This will NOT push into the history stack.
	 * @param non-empty-string|false $url
	 * @return void
	 */
	private function replaceUrl(string|false $url): void
	{
		if (empty($this->headers)) {
			throw new LogicException('Can not redirect without Headers in ' . static::class);
		}
		$url = $url ?: "false";
		$this->headers->setHeader('HX-Replace-Url', $url, true);
	}

	/**
	 * Allows you to specify how the response will be swapped. See hx-swap for possible values
	 *
	 * @param HtmxSwap $swap
	 * @return void
	 */
	private function reswap(HtmxSwap $swap): void
	{
		if (empty($this->headers)) {
			throw new LogicException('Can not redirect without Headers in ' . static::class);
		}
		$this->headers->setHeader('HX-Reswap', $swap->value, true);
	}

	/**
	 * @param array<string> $events
	 * @return void
	 * @throws JsonException
	 */
	private function trigger(array $events): void
	{
		if (empty($this->headers)) {
			throw new LogicException('Can not redirect without Headers in ' . static::class);
		}
		$encoded = json_encode($events, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
		$this->headers->setHeader('HX-Trigger', $encoded, true);
	}

	/**
	 * @param non-empty-string|false $url
	 * @return void
	 */
	private function historyPush(string|false $url): void
	{
		if (empty($this->headers)) {
			throw new LogicException('Can not redirect without Headers in ' . static::class);
		}
		$url = $url ?: "false";
		$this->headers->setHeader('HX-Push-Url', $url);
	}

	private function renderFragment(string $id, string $fragment): string
	{
		return $this->compiler->compileFragment($id, $fragment);
	}

	/**
	 * A CSS selector that updates the target of the content update to a different element on the page
	 *
	 * @param non-empty-string $target
	 * @return void
	 */
	private function retarget(string $target): void
	{
		if (empty($this->headers)) {
			throw new LogicException('Can not redirect without Headers in ' . static::class);
		}
		$this->headers->setHeader('HX-Retarget', $target, true);
	}
}
