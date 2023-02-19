<?php

namespace Bottledcode\SwytchFramework\Template\Interfaces;

/**
 * Provides references in the DOM that can be accessed by children of the component
 */
interface RefProviderInterface {
	/**
	 * Create a reference to an item
	 *
	 * @param mixed $item The item to reference
	 * @return string The reference
	 */
	public function createRef(mixed $item): string;

	/**
	 * Get the item referenced by the given reference
	 * @param string $ref The reference
	 * @return mixed The item
	 */
	public function getRef(string $ref): mixed;

	/**
	 * Delete a reference
	 *
	 * @param string $ref The reference to delete
	 * @return void
	 */
	public function deleteRef(string $ref): void;
}
