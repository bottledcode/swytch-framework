<?php

namespace Bottledcode\SwytchFramework\Hooks\Html;

use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\PostprocessInterface;
use Laminas\Escaper\Escaper;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

#[Handler(11)]
class HeadTagFilter extends HtmlHandler implements PostprocessInterface
{
	/**
	 * @var array<string>
	 */
	private array $lines = [];

	public function __construct(private readonly Psr17Factory $psr17Factory, private readonly Escaper $escaper)
	{
	}

	public function setTitle(string $title): void
	{
		$title = $this->escaper->escapeHtml($title);
		$this->addLines('title', "<title>$title</title>");
	}

	public function addLines(string $tag, string $line): void
	{
		$this->lines[$tag] = $line;
	}

	public function addScript(
		string $tag,
		string $src,
		bool $async = false,
		bool $defer = false,
		string|null $priority = null,
		string|null $nonce = null,
		string $referrerPolicy = 'no-referrer'
	): void {
		$src = $this->escaper->escapeHtmlAttr($src);
		$attributes = implode(
			' ',
			array_filter(
				[
					$async ? 'async' : null,
					$defer ? 'defer' : null,
					$nonce ? "nonce=\"{$this->escaper->escapeHtmlAttr($nonce)}\"" : null,
					$priority ? "priority=\"{$this->escaper->escapeHtmlAttr($priority)}\"" : null,
					$referrerPolicy ? "referrerPolicy=\"{$this->escaper->escapeHtmlAttr($referrerPolicy)}\"" : null,
				]
			)
		);

		$this->addLines($tag, "<script $attributes src=\"$src\"></script>");
	}

	public function addCss(string $tag, string $href): void
	{
		$href = $this->escaper->escapeHtmlAttr($href);
		$this->addLines($tag, "<link rel=\"stylesheet\" type='text/css' href=\"$href\" />");
	}

	public function setMeta(string $property, string $value): void
	{
		$value = $this->escaper->escapeHtmlAttr($value);
		$property = $this->escaper->escapeHtmlAttr($property);
		$this->addLines($property, "<meta property=\"$property\" content=\"$value\" />");
	}

	/**
	 * @param string $pageUrl The canonical URL for your page. This should be the undecorated URL, without session variables, user identifying parameters, or counters. Likes and Shares for this URL will aggregate at this URL.
	 * @param string $title The title of your article without any branding such as your site name.
	 * @param string $description A brief description of the content, usually between 2 and 4 sentences. This will be displayed below the title of the post on Facebook.
	 * @param string $imageUrl The URL of the image that appears when someone shares the content to Facebook. See below for more info, and check out our best practices guide to learn how to specify a high quality preview image.
	 * @param string|null $locale The locale of the resource. Defaults to en_US.
	 * @return void
	 */
	public function setOpenGraph(
		string $pageUrl,
		string $title,
		string $description,
		string $imageUrl,
		string|null $locale = null,
	): void {
		$pageUrl = $this->escaper->escapeHtmlAttr($pageUrl);
		$title = $this->escaper->escapeHtmlAttr($title);
		$description = $this->escaper->escapeHtmlAttr($description);
		$imageUrl = $this->escaper->escapeHtmlAttr($imageUrl);
		$this->addLines('og:url', "<meta property=\"og:url\" content=\"$pageUrl\" />");
		$this->addLines('og:title', "<meta property=\"og:title\" content=\"$title\" />");
		$this->addLines('og:description', "<meta property=\"og:description\" content=\"$description\" />");
		$this->addLines('og:image', "<meta property=\"og:image\" content=\"$imageUrl\" />");
		if ($locale !== null) {
			$locale = $this->escaper->escapeHtmlAttr($locale);
			$this->addLines('og:locale', "<meta property=\"og:locale\" content=\"$locale\" />");
		}
	}

	/**
	 * @param string $type The card type, which will be one of “summary”, “summary_large_image”, “app”, or “player”.
	 * @param string|null $usernameFooter @username for the website used in the card footer.
	 * @param string|null $author @username for the content creator / author.
	 * @return void
	 */
	public function setTwitterCard(string $type, string|null $usernameFooter = null, string|null $author = null): void
	{
		$type = $this->escaper->escapeHtmlAttr($type);
		$this->addLines('twitter:card', "<meta property=\"twitter:card\" content=\"$type\" />");
		if ($usernameFooter !== null) {
			$usernameFooter = $this->escaper->escapeHtmlAttr($usernameFooter);
			$this->addLines('twitter:site', "<meta property=\"twitter:site\" content=\"$usernameFooter\" />");
		}
		if ($author !== null) {
			$author = $this->escaper->escapeHtmlAttr($author);
			$this->addLines('twitter:creator', "<meta property=\"twitter:creator\" content=\"$author\" />");
		}
	}

	public function postprocess(ResponseInterface $response): ResponseInterface
	{
		if (!count($this->lines)) {
			return $response;
		}
		$response->getBody()->rewind();
		$body = $response->getBody()->getContents();
		$head = implode("\n", $this->lines);
		return $response->withBody($this->psr17Factory->createStream(str_replace('</head>', $head . '</head>', $body)));
	}
}
