<?php

namespace Bottledcode\SwytchFramework\Template\Traits;

use Bottledcode\SwytchFramework\Template\Compiler;

trait Refs {
	private Compiler $compiler;
	private array $_myRefs = [];

	private function ref(mixed $ref): string {
		if(!isset($this->compiler)) {
			throw new \LogicException('Can not render refs without a compiler');
		}
		try {
			return $this->_myRefs[] = $this->compiler->createRef($ref);
		} catch	(\LogicException) {
			return $this->compiler->getRef($ref);
		}
	}

	public function __destruct()
	{
		if(isset($this->compiler)) {
			foreach($this->_myRefs as $ref) {
				$this->compiler->deleteRef($ref);
			}
		}
	}
}
