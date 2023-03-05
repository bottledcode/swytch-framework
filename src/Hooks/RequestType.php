<?php

namespace Bottledcode\SwytchFramework\Hooks;

enum RequestType
{
	case Unknown;
	case Api;
	case Htmx;
	case Browser;
}
