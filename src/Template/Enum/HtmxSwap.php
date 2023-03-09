<?php

namespace Bottledcode\SwytchFramework\Template\Enum;

/**
 * @see https://htmx.org/docs/#swapping
 */
enum HtmxSwap: string
{
	/**
	 * the default, puts the content inside the target element
	 */
	case InnerHtml = 'innerHTML';

	/**
	 * replaces the entire target element with the returned content
	 */
	case OuterHtml = 'outerHTML';

	/**
	 * prepends the content before the first child inside the target
	 */
	case AfterBegin = 'afterbegin';

	/**
	 * prepends the content before the target in the targets parent element
	 */
	case BeforeBegin = 'beforebegin';

	/**
	 * appends the content after the target in the targets parent element
	 */
	case AfterEnd = 'afterend';

	/**
	 * appends the content after the last child inside the target
	 */
	case BeforeEnd = 'beforeend';

	/**
	 * does not append content from response (Out of Band Swaps and Response Headers will still be processed)
	 */
	case None = 'none';
}
