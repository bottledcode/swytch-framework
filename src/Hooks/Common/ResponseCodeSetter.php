<?php

namespace Bottledcode\SwytchFramework\Hooks\Common;

use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\PostprocessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Psr\Http\Message\ResponseInterface;
use Withinboredom\ResponseCode\HttpResponseCode;

#[Handler(1000)]
class ResponseCodeSetter implements HandleRequestInterface, PostprocessInterface
{
	private HttpResponseCode|null $code = null;

	/**
	 * Set the HTTP response code to be used for the response.
	 *
	 * @param int|HttpResponseCode $code
	 * @return void
	 */
	public function setResponseCode(int|HttpResponseCode $code): void
	{
		if (is_int($code)) {
			$code = HttpResponseCode::from($code);
		}
		$this->code = $code;
	}


	public function handles(RequestType $requestType): bool
	{
		return true;
	}

	public function postprocess(ResponseInterface $response): ResponseInterface
	{
		if ($this->code !== null) {
			return $response->withStatus($this->code->value);
		}

		return $response;
	}
}
