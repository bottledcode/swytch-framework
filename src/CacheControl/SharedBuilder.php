<?php

namespace Bottledcode\SwytchFramework\CacheControl;

class SharedBuilder extends Builder
{
    public function shared(): PublicBuilder
    {
        return new PublicBuilder(
            array_merge($this->values, ['public']),
            $this->etagRequired,
            $this->score + 2,
            $this->tag
        );
    }

    public function notShared(): PrivateBuilder
    {
        return new PrivateBuilder(array_merge($this->values, ['private']), $this->etagRequired, 0, $this->tag);
    }
}
