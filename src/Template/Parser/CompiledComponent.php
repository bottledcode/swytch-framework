<?php

namespace Bottledcode\SwytchFramework\Template\Parser;

use Bottledcode\SwytchFramework\Template\Attributes\Authenticated;
use Bottledcode\SwytchFramework\Template\Attributes\Authorized;
use Bottledcode\SwytchFramework\Template\Interfaces\AuthenticationServiceInterface;
use DI\Container;
use olvlvl\ComposerAttributeCollector\Attributes;

readonly class CompiledComponent
{
	public function __construct(
		public string $name,
		public string $type,
		public AuthenticationServiceInterface $authenticationService,
		public Container $container
	) {
	}

	public function isAuthorized(): bool
	{
		$classAttributes = Attributes::forClass($this->type);
		foreach ($classAttributes->classAttributes as $attr) {
			if ($attr instanceof Authenticated) {
				$userAuthenticated = $this->authenticationService->isAuthenticated();
				switch ([$userAuthenticated, $attr->visible]) {
					// set to visible and user is authenticated
					case [true, true]:
					case [false, false]:
						break;
					case [false, true]: // set to visible and user is not authenticated
					case [true, false]: // set to not visible and user is not authenticated
						return false;
				}
			}
			if ($attr instanceof Authorized) {
				$userAuthorized = $this->authenticationService->isAuthorizedVia(...$attr->roles);
				switch ([$userAuthorized, $attr->visible]) {
					case [true, true]:
					case [false, false]:
						break;
					case [false, true]:
					case [true, false]:
						return false;
				}
			}
		}
		return true;
	}

	public function renderToString(array $parameters): string {
		if(!$this->isAuthorized()) {
			return '';
		}

		$component = $this->container->make($this->type);
		$rendered = $this->container->call($component->render(...), $parameters);
		return $rendered;
	}
}
