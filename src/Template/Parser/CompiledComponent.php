<?php

namespace Bottledcode\SwytchFramework\Template\Parser;

use Bottledcode\SwytchFramework\Template\Attributes\Authenticated;
use Bottledcode\SwytchFramework\Template\Attributes\Authorized;
use Bottledcode\SwytchFramework\Template\Functional\DataProvider;
use Bottledcode\SwytchFramework\Template\Interfaces\AuthenticationServiceInterface;
use DI\Container;
use olvlvl\ComposerAttributeCollector\Attributes;

class CompiledComponent
{
	public mixed $rawComponent = null;

	/**
	 * @param string $name
	 * @param string $type
	 * @param array<CompiledComponent|null> $providers
	 * @param AuthenticationServiceInterface $authenticationService
	 * @param Container $container
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $type,
		public readonly array $providers,
		public readonly AuthenticationServiceInterface $authenticationService,
		public readonly Container $container
	) {
	}

	public function renderToString(array $parameters): string
	{
		if (!$this->isAuthorized()) {
			return '';
		}

		foreach($this->providers as $compiledComponent) {
			$provider = $compiledComponent?->rawComponent;
			if($provider instanceof DataProvider) {
				$parameters = [...$parameters, ...$provider->provideAttributes()];
				foreach($parameters as $key => &$value) {
					$value = $provider->provideValues($value);
				}
			}
		}

		$this->rawComponent = $component = $this->container->make($this->type);
		return $this->container->call($component->render(...), $parameters);
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
}
