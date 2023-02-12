<?php

namespace Bottledcode\SwytchFramework\CacheControl;

class PrivateBuilder extends Builder
{
    public function render(string $etag): void
    {
        header('Cache-Control: ' . implode(',', $this->values));
        if ($this->etagRequired) {
            header('ETag: ' . $etag);
        }
    }
}
