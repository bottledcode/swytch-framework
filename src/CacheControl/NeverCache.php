<?php

namespace Bottledcode\SwytchFramework\CacheControl;

class NeverCache extends Builder
{
    public function render(string $etag): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Pragma: no-cache');
    }
}
