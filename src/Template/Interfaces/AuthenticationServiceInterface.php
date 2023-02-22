<?php

namespace Bottledcode\SwytchFramework\Template\Interfaces;

interface AuthenticationServiceInterface {
	public function isAuthenticated(): bool;

	public function isAuthorizedVia(\BackedEnum ...$role): bool;
}
