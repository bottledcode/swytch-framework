<?php

namespace Bottledcode\SwytchFramework\Hooks\Common;

use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\PostprocessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Psr\Http\Message\ResponseInterface;

#[Handler(10)]
class Headers implements HandleRequestInterface, PostprocessInterface
{

	/**
	 * @var array<string, array<string>> $headers
	 */
	private array $headers = [];

	public function handles(RequestType $requestType): bool
	{
		return true;
	}

	/**
	 * Set a HTTP Header.
	 *
	 * @param string $name
	 * @param string $value
	 * @param bool $overwrite
	 * @return void
	 */
	public function setHeader(string $name, string $value, bool $overwrite = false): void
	{
		if ($overwrite) {
			$this->headers[$name] = [$value];
			return;
		}
		$this->headers[$name][] = $value;
	}

	public function setCookie(string $name, string $value, \DateTimeInterface $expiresAt = new \DateTimeImmutable()) {
		$value = urlencode($value);
		$headerValue = "{$name}={$value}; Path=/; Expires={$expiresAt->format(\DateTimeInterface::RFC7231)}; HttpOnly; SameSite=Strict";
		$this->setHeader('Set-Cookie', $headerValue, false);
	}

	public function deleteCookie(string $name) {
		$headerValue = "{$name}=deleted; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; SameSite=Strict";
		$this->setHeader('Set-Cookie', $headerValue, false);
	}

	public function postprocess(ResponseInterface $response): ResponseInterface
	{
		foreach ($this->headers as $header => $values) {
			$response = $response->withHeader($header, $values);
		}
		return $response;
	}
}
