<?php

namespace Bottledcode\SwytchFramework\Template\Interfaces;

use BackedEnum;

interface AuthenticationServiceInterface
{
	public function isAuthenticated(): bool;

	public function isAuthorizedVia(BackedEnum ...$role): bool;
}
