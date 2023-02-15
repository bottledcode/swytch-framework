<?php

namespace Bottledcode\SwytchFramework\Template\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Component {
    public function __construct(public string $name) {}
}
