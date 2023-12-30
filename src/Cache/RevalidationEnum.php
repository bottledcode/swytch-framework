<?php

namespace Bottledcode\SwytchFramework\Cache;

enum RevalidationEnum
{
	case EveryRequest;
	case WhenStale;
	case WhenStaleProxies;
	case AfterStale;
	case AfterError;
}
