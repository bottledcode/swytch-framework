<?php

namespace Bottledcode\SwytchFramework\Template\Parser;

use Bottledcode\SwytchFramework\Cache\AbstractCache;
use Bottledcode\SwytchFramework\Cache\Control\Tokenizer;
use Bottledcode\SwytchFramework\Template\Functional\DataProvider;
use Bottledcode\SwytchFramework\Template\Functional\RewritingTag;
use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;
use Closure;
use DI\FactoryInterface;
use Laminas\Escaper\Escaper;
use LogicException;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetClass;

/**
 */
class StreamingCompiler
{
	private array $components = [];
	private bool $quirks = false;
	private array $docTypeName = [];
	private string|null $renderingTag = null;
	private bool|null $isClosing = null;
	private string|null $nameBuffer = null;
	private int $childrenStart = 0;
	private int $childrenEnd = 0;
	private int $nesting = 0;
	private array $attributes = [];
	private string $attributeName = '';
	private string $attributeValue = '';
	private bool $selfClosing = false;
	private int $cutStart = 0;
	private int $cutEnd = 0;
	private array $providers = [];
	private string|null $mustMatch = null;
	private int $lastTagOpenOpen = 0;
	private int $lastTagCloseOpen = 0;
	/**
	 * @var bool Set to true when rendering children and we want to prevent the capturing of attributes
	 */
	private $blockAttributes = false;
	private array $containers = [];
	private string|null $fragmentId = null;
	private int $fragmentStart = 0;
	private int $fragmentLength = 0;
	private bool $shallow = false;

	public function __construct(
		public FactoryInterface $factory,
		private EscaperInterface $blobber,
		private Escaper $escaper,
		public Tokenizer $tokenizer,
	) {
	}

	public function registerComponent(TargetClass $component): void
	{
		$this->components[mb_strtolower($component->attribute->name)] = $component->name;
		if ($component->attribute->isContainer) {
			$this->containers[mb_strtolower($component->attribute->name)] = true;
		}
	}

	public function compileFragment(string $id, string $code): string
	{
		$this->fragmentId = $id;
		$compiled = $this->compile($code);
		return substr($compiled, $this->fragmentStart, $this->fragmentLength);
	}

	/**
	 * This is meant to be called from the output of a root component.
	 *
	 * @param string $code
	 * @return string
	 */
	public function compile(string $code): string
	{
		$code = $this->blobber->makeBlobs($code);
		$document = new Document($code . "\n\n");
		return $this->renderData($document)->code;
	}

	private function renderData(Document $document): Document
	{
		$selectionStart = $document->mark();
		restartData:
		$result = match ($document->consume()) {
			'&' => $this->renderCharacterReference($document),
			'<' => $this->escapeData($selectionStart, $document)($this->renderOpenTag(...)),
			default => null,
		};
		if ($result !== null) {
			$document = $result;
			$selectionStart = $document->mark();
			$this->resetState();
			goto restartData;
		}
		if ($document->isEof()) {
			return $this->escapeData($selectionStart, $document)(fn(Document $x) => $x);
		}
		goto restartData;
	}

	private function renderCharacterReference($document): Document
	{
		while ($document->consume() !== ';') {
			if ($document->isEof()) {
				throw new LogicException('Unexpected end of file');
			}
		}
		return $document;
	}

	private function escapeData(int $selectionStart, Document $document): Closure
	{
		if ($this->blockAttributes) {
			return static fn(Closure $x) => $x($document);
		}
		$end = $document->mark() - 1;
		$originalData = substr($document->code, $selectionStart, $end - $selectionStart);
		$data = $this->blobber->replaceBlobs($originalData, $this->escaper->escapeHtml(...));
		if ($data === $originalData) {
			return static fn(Closure $x) => $x($document);
		}
		$document = $document->snip($selectionStart, $end)->insert($data, $selectionStart)->seek(
			$selectionStart + strlen($data) + 1
		);

		return static fn(Closure $x) => $x($document);
	}

	private function resetState(): void
	{
		$this->selfClosing = false;
		$this->attributeValue = '';
		$this->attributeName = '';
		$this->originalAttributeName = '';
		$this->nameBuffer = '';
		$this->isClosing = false;
	}

	public function compileShallow(string $code): string
	{
		$this->shallow = true;
		$compiled = $this->compile($code);
		$this->shallow = false;
		return $compiled;
	}

	private function renderOpenTag(Document $document): Document
	{
		$result = match (strtolower($document->consume())) {
			'!' => $this->renderDeclarationOpen($document),
			'/' => $this->renderEndTagOpen($document),
			'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z' => $document->reconsume(
				$this->renderOpenTagName(...)
			),
			'?' => $document->reconsume($this->renderBogusComment(...)),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		return $document;
	}

	private function renderDeclarationOpen(Document $document): Document
	{
		$buffer = $document->peek(7);
		if (strtolower($buffer) === 'doctype') {
			$document->consume(7);
			return $this->renderDocType($document);
		}
		if ($buffer === '[CDATA[') {
			$document->consume(7);
			return $this->renderCData($document);
		}
		if (str_starts_with($buffer, '--')) {
			$document->consume(2);
			return $this->renderCommentStart($document);
		}
		return $this->renderBogusComment($document);
	}

	private function renderDocType(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeDocTypeName($document),
			'>' => $document->reconsume($this->renderBeforeDocTypeName(...)),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		return $document->reconsume($this->renderBeforeDocTypeName(...));
	}

	private function renderBeforeDocTypeName(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeDocTypeName($document),
			'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z' => $document->reconsume(
				$this->renderDocTypeName(...)
			),
			'>' => $this->setQuirksMode(true, fn() => $document),
			default => null,
		};
		return $result ?? $document->reconsume($this->renderDocTypeName(...));
	}

	private function setQuirksMode(bool $mode, Closure $next): Document
	{
		$this->quirks = $mode;
		return $next();
	}

	private function renderCData(Document $document): Document
	{
		renderCData:
		$result = match ($document->consume()) {
			']' => $this->renderCDataSectionBracket($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		goto renderCData;
	}

	private function renderCDataSectionBracket(Document $document): Document
	{
		$result = match ($document->consume()) {
			']' => $this->renderCDataSectionEnd($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		return $document->reconsume($this->renderCData(...));
	}

	private function renderCDataSectionEnd(Document $document): Document
	{
		$result = match ($document->consume()) {
			']' => $this->renderCDataSectionEnd($document),
			'>' => $document,
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		return $document->reconsume($this->renderCData(...));
	}

	private function renderCommentStart(Document $document): Document
	{
		return match ($document->consume()) {
			'-' => $this->renderCommentStartDash($document),
			'>' => $document,
			default => $this->renderComment($document),
		};
	}

	private function renderCommentStartDash(Document $document): Document
	{
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}

		return match ($document->consume()) {
			'-' => $this->renderCommentEnd($document),
			'>' => $document,
			default => $this->renderComment($document),
		};
	}

	private function renderCommentEnd(Document $document): Document
	{
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}

		$result = match ($document->consume()) {
			'>' => $document,
			'!' => $this->renderCommentEndBang($document),
			default => null,
		};

		if ($result !== null) {
			return $result;
		}

		return $this->renderComment($document);
	}

	private function renderCommentEndBang(Document $document): Document
	{
		$result = match ($document->consume()) {
			'-' => $this->renderCommentEndDash($document),
			'>' => $document,
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		return $this->renderComment($document);
	}

	private function renderCommentEndDash(Document $document): Document
	{
		$result = match ($document->consume()) {
			'-' => $this->renderCommentEnd($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		return $this->renderComment($document);
	}

	private function renderComment(Document $document): Document
	{
		renderComment:
		$result = match ($document->consume()) {
			'<' => $this->renderCommentLessThanSign($document),
			'-' => $this->renderCommentEndDash($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		goto renderComment;
	}

	private function renderCommentLessThanSign(Document $document): Document
	{
		$result = match ($document->consume()) {
			'!' => $this->renderCommentLessThanSignBang($document),
			'<' => $this->renderCommentLessThanSign($document),
			default => null,
		};
		return $result ?? $document->reconsume($this->renderComment(...));
	}

	private function renderCommentLessThanSignBang(Document $document): Document
	{
		$result = match ($document->consume()) {
			'-' => $this->renderCommentLessThanSignBangDash($document),
			default => null,
		};
		return $result ?? $document->reconsume($this->renderComment(...));
	}

	private function renderCommentLessThanSignBangDash(Document $document): Document
	{
		$result = match ($document->consume()) {
			'-' => $this->renderCommentLessThanSignBangDashDash($document),
			default => null,
		};
		return $result ?? $document->reconsume($this->renderComment(...));
	}

	private function renderCommentLessThanSignBangDashDash(Document $document): Document
	{
		return $document->reconsume($this->renderCommentEnd(...));
	}

	private function renderBogusComment(Document $document): Document
	{
		renderBogusComment:
		$result = match ($document->consume()) {
			'>' => $document,
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			return $document;
		}
		goto renderBogusComment;
	}

	private function renderEndTagOpen(Document $document): Document
	{
		if (ctype_alpha($char = $document->consume())) {
			$this->nameBuffer = null;
			$this->isClosing = true;
			$this->lastTagCloseOpen = $document->mark();
			return $document->reconsume($this->renderTagClosingName(...));
		}
		if ($char === '>') {
			return $document;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		return $this->renderBogusComment($document);
	}

	/**
	 * This is an alias for renderTagName, but only called when opening a tag
	 *
	 * @param Document $document
	 * @return Document
	 */
	private function renderOpenTagName(Document $document): Document
	{
		$starting = $document->mark();
		$this->lastTagOpenOpen = $starting - 1;

		if (!$this->blockAttributes) {
			$this->attributes = [];
		}

		$document = $this->renderTagName($document);

		switch ($tag = mb_strtolower($this->nameBuffer)) {
			case 'title':
			case 'textarea':
				$this->mustMatch = $tag;
				if ($this->blockAttributes) {
					return $this->renderRCData($document);
				}
				$now = $document->mark();
				return $this->renderRCData($document)
					->snip($now, $this->lastTagCloseOpen, $output)
					->insert($this->blobber->replaceBlobs($output, $this->escaper->escapeHtml(...)), $now);
			case 'style':
				$this->mustMatch = $tag;
				if ($this->blockAttributes) {
					return $this->renderRawText($document);
				}
				$now = $document->mark();
				return $this
					->renderRawText($document)
					->snip($now, $this->lastTagCloseOpen, $output)
					->insert($this->blobber->replaceBlobs($output, $this->escaper->escapeCss(...)), $now);
			case 'xmp':
			case 'iframe':
			case 'noembed':
			case 'noscript':
			case 'plaintext':
			case 'noframes':
				$this->mustMatch = $tag;
				if ($this->blockAttributes) {
					return $this->renderRawText($document);
				}
				$now = $document->mark();
				return $this->renderRawText($document)
					->snip($now, $this->lastTagCloseOpen, $output)
					->insert($this->blobber->replaceBlobs($output, $this->escaper->escapeHtml(...)), $now);
			case 'script':
				$this->mustMatch = $tag;
				if ($this->blockAttributes) {
					return $this->renderScriptData($document);
				}
				$now = $document->mark();
				return $this
					->renderScriptData($document)
					->snip($now, $this->lastTagCloseOpen, $output)
					->insert($this->blobber->replaceBlobs($output, $this->escaper->escapeJs(...)), $now);
			default:
				// do nothing
				break;
		}

		// at this point, we know everything about the tag, so we can see if we need to render it.
		if (array_key_exists($tag, $this->components)) {
			// we can render it, but check to see if we already are rendering a tag

			// prevent us from rendering a nested tag
			if ($this->renderingTag === $tag && !$this->selfClosing) {
				$this->nesting++;
				return $document;
			}

			if ($this->renderingTag === $tag && $this->selfClosing) {
				return $document;
			}

			if (empty($this->renderingTag)) {
				// let's render the tag...
				$this->nesting = 0;
				$this->renderingTag = $tag;
				$this->cutStart = empty($this->containers[$tag]) ? $starting - 1 : $document->mark();
				$this->blockAttributes = true;
				$this->childrenStart = $document->mark();
			}

			// everything is now configured to render the tag, but we only do that now iff the tag is self-closing
			if ($this->selfClosing && $this->renderingTag === $tag) {
				$this->cutEnd = $this->childrenEnd = $document->mark();
				if ($this->shallow) {
					$document->onPosition($this->cutEnd, fn() => $this->renderingTag = null);
				}
				$document = $this->renderComponent($document);
				$this->blockAttributes = false;
				if (!$this->shallow) {
					$this->renderingTag = null;
				}
			}
		}

		return $document;
	}

	private function renderTagName(Document $document): Document
	{
		renderTagName:
		$result = match ($char = $document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeAttributeName($document),
			'>' => $document,
			'/' => $this->renderSelfClosingStartTag($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}

		$this->nameBuffer .= $char;
		goto renderTagName;
	}

	private function renderBeforeAttributeName(Document $document): Document
	{
		$oops = function (string $c, Closure $next) use ($document) {
			$this->attributeName = strtolower($c);
			$this->originalAttributeName = $c;
			$this->attributeValue = '';
			return $next($document);
		};

		$result = match ($char = $document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeAttributeName($document),
			'/' => $document->reconsume($this->renderAfterAttributeName(...)),
			'=' => $oops($char, $this->renderAttributeName(...)),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		$this->attributeName = '';
		$this->originalAttributeName = '';
		$this->attributeValue = '';
		return $document->reconsume($this->renderAttributeName(...));
	}

	private function renderSelfClosingStartTag(Document $document): Document
	{
		$char = $document->consume();
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		if ($char === '>') {
			$this->selfClosing = true;
			return $document;
		}
		return $document->reconsume($this->renderBeforeAttributeName(...));
	}

	private function renderRCData(Document $document): Document
	{
		renderRCData:
		$result = match ($document->consume()) {
			'<' => $this->renderRCDataLessThanSign($document),
			'&' => $this->renderCharacterReference($document) ? null : null,
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			return $document;
		}
		goto renderRCData;
	}

	private function renderRCDataLessThanSign(Document $document): Document
	{
		$this->lastTagCloseOpen = $document->mark() - 1;
		$result = match ($document->consume()) {
			'/' => $this->renderRcDataEndTagOpen($document),
			default => null,
		};
		return $result ?? $document->reconsume($this->renderRcData(...));
	}

	private function renderRcDataEndTagOpen(Document $document): Document
	{
		if (ctype_alpha($document->consume())) {
			$this->nameBuffer = '';
			return $document->reconsume($this->renderRcDataEndTagName(...));
		}

		return $document->reconsume($this->renderRcData(...));
	}

	private function renderRawText(Document $document): Document
	{
		renderRawText:
		$result = match ($document->consume()) {
			'<' => $this->renderRawTextLessThanSign($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		goto renderRawText;
	}

	private function renderRawTextLessThanSign(Document $document): Document
	{
		$this->lastTagCloseOpen = $document->mark() - 1;
		$result = match ($document->consume()) {
			'/' => $this->renderRawTextEndTagOpen($document),
			default => null,
		};
		return $result ?? $document->reconsume($this->renderRawText(...));
	}

	private function renderRawTextEndTagOpen(Document $document): Document
	{
		if (ctype_alpha($document->consume())) {
			$this->nameBuffer = '';
			return $document->reconsume($this->renderRawTextEndTagName(...));
		}

		return $document->reconsume($this->renderRawText(...));
	}

	private function renderScriptData(Document $document): Document
	{
		renderScriptData:
		$result = match ($document->consume()) {
			'<' => $this->renderScriptDataLessThanSign($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			return $document;
		}
		goto renderScriptData;
	}

	private function renderScriptDataLessThanSign(Document $document): Document
	{
		$this->lastTagCloseOpen = $document->mark() - 1;
		$result = match ($document->consume()) {
			'/' => $this->renderScriptDataEndTagOpen($document),
			'!' => $this->renderScriptDataEscapeStart($document),
			default => null,
		};
		return $result ?? $document->reconsume($this->renderScriptData(...));
	}

	private function renderScriptDataEndTagOpen(Document $document): Document
	{
		if (ctype_alpha($document->consume())) {
			$this->nameBuffer = '';
			return $document->reconsume($this->renderScriptDataEndTagName(...));
		}

		return $document->reconsume($this->renderScriptData(...));
	}

	private function renderScriptDataEscapeStart(Document $document): Document
	{
		$result = match ($document->consume()) {
			'-' => $this->renderScriptDataEscapeStartDash($document),
			default => null,
		};
		return $result ?? $document->reconsume($this->renderScriptData(...));
	}

	private function renderScriptDataEscapeStartDash(Document $document): Document
	{
		$result = match ($document->consume()) {
			'-' => $this->renderScriptDataEscapedDashDash($document),
			default => null,
		};
		return $result ?? $document->reconsume($this->renderScriptData(...));
	}

	private function renderScriptDataEscapedDashDash(Document $document): Document
	{
		return match ($document->consume()) {
			'<' => $this->renderScriptDataEscapedLessThanSign($document),
			'>' => $this->renderScriptData($document),
			'-' => $document,
			default => $this->renderScriptDataEscaped($document),
		};
	}

	private function renderScriptDataEscapedLessThanSign(Document $document): Document
	{
		$this->lastTagCloseOpen = $document->mark() - 1;
		return match ($char = $document->consume()) {
			'/' => $this->renderScriptDataEscapedEndTagOpen($document),
			default => ctype_alpha($char) ? $document->reconsume(
				$this->renderScriptDataDoubleEscapeStart(...)
			) : $this->renderScriptDataEscaped($document),
		};
	}

	private function renderScriptDataEscapedEndTagOpen(Document $document): Document
	{
		$char = $document->consume();
		if (ctype_alpha($char)) {
			$this->nameBuffer = '';
			return $document->reconsume($this->renderScriptDataEscapedEndTagName(...));
		}
		return $document->reconsume($this->renderScriptDataEscaped(...));
	}

	private function renderScriptDataEscaped(Document $document): Document
	{
		read:
		$result = match ($document->consume()) {
			'-' => $this->renderScriptDataEscapedDash($document),
			'<' => $this->renderScriptDataEscapedLessThanSign($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			return $document;
		}
		goto read;
	}

	private function renderScriptDataEscapedDash(Document $document): Document
	{
		$result = match ($document->consume()) {
			'-' => $this->renderScriptDataEscapedDashDash($document),
			'<' => $this->renderScriptDataEscapedLessThanSign($document),
			default => null,
		};
		return $result ?? $this->renderScriptDataEscaped(...);
	}

	private function renderComponent(Document $document): Document
	{
		$children = substr($document->code, $this->childrenStart, $this->childrenEnd - $this->childrenStart);
		$componentName = $this->components[mb_strtolower($this->renderingTag)];

		/**
		 * @var $component CompiledComponent
		 */
		$component = $this->factory->make(
			CompiledComponent::class,
			['name' => $this->renderingTag, 'type' => $componentName, 'providers' => $this->providers]
		);

		// configure tokenizer
		$attributes = Attributes::forClass($component->type);
		foreach($attributes->classAttributes as $attribute) {
			if($attribute instanceof AbstractCache) {
				$this->tokenizer = $attribute->tokenize($this->tokenizer);
			}
		}

		$rendering = $component->renderToString($this->attributes);
		$rendering = $this->blobber->makeBlobs($rendering);
		// stupid hack to get around the fact that we are lexigraphically parsing the document instead
		// of creating a tree.
		$rendering = str_replace(['<children></children>', '<children/>', '<children />'], $children, $rendering);

		if (class_implements($component->type, DataProvider::class)) {
			$this->providers[] = $component;
			$idx = count($this->providers) - 1;
			$document->onPosition($this->cutEnd, fn() => $this->providers[$idx] = null);
		}

		if ($component->rawComponent instanceof RewritingTag && $component->rawComponent->isItMe($this->fragmentId)) {
			$this->fragmentStart = $this->cutStart;
			$document->onPosition(
				$this->cutEnd,
				fn(Document $document) => $this->fragmentLength = $document->mark() - $this->fragmentStart
			);
		}

		return $document
			->snip($this->cutStart, $this->cutEnd)
			->insert($rendering, $this->cutStart)
			->seek($this->cutStart);
	}

	private function renderRawTextEndTagName(Document $document): Document
	{
		read:
		$char = $document->consume();

		if (strtolower($this->nameBuffer) === $this->mustMatch) {
			$result = match ($char) {
				"\t", "\n", "\f", " " => $this->renderBeforeAttributeName($document),
				'/' => $this->renderSelfClosingStartTag($document),
				'>' => $document,
				default => null,
			};
			$this->mustMatch = $result ? null : $this->mustMatch;
			return $result ?? $document->reconsume($this->renderRawText(...));
		}

		if (ctype_alpha($char)) {
			$this->nameBuffer .= $char;
			goto read;
		}

		return $document->reconsume($this->renderRawText(...));
	}

	/**
	 * This is an alias for renderTagName, but only called when closing a tag
	 *
	 * @param Document $document
	 * @return Document
	 */
	private function renderTagClosingName(Document $document): Document
	{
		$childrenEnd = $document->mark();

		$document = $this->renderTagName($document);

		// if we aren't rendering a tag, we are done.
		if (empty($this->renderingTag)) {
			return $document;
		}

		// if we are rendering the current tag
		if ($this->renderingTag !== ($tag = mb_strtolower($this->nameBuffer))) {
			return $document;
		}

		// check nesting
		if (--$this->nesting >= 0) {
			return $document;
		}

		// everything is in place... render the tag
		$this->cutEnd = empty($this->containers[$tag]) ? $document->mark() : ($childrenEnd - 2);
		$this->childrenEnd = $childrenEnd - 2;

		// on shallow renders, do not render children
		if ($this->shallow) {
			$document->onPosition($this->cutEnd, fn() => $this->renderingTag = null);
		}

		$document = $this->renderComponent($document);
		$this->blockAttributes = false;

		// do not allow children to be rendered
		if (!$this->shallow) {
			$this->renderingTag = null;
		}

		return $document;
	}

	private function renderDocTypeName(Document $document): Document
	{
		renderDocTypeName:
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderAfterDocTypeName($document),
			'>' => $document,
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		goto renderDocTypeName;
	}

	private function renderAfterDocTypeName(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderAfterDocTypeName($document),
			'>' => $document,
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		$buffer = $document->peek(6);
		if (strtolower($buffer) === 'public') {
			$document->consume(6);
			return $this->renderAfterDocTypePublicKeyword($document);
		}
		if (strtolower($buffer) === 'system') {
			$document->consume(6);
			return $this->renderAfterDocTypeSystemKeyword($document);
		}

		$this->quirks = true;
		return $this->renderBogusDocType($document);
	}

	private function renderAfterDocTypePublicKeyword(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeDocTypePublicIdentifier($document),
			'"' => $this->renderDocTypePublicIdentifierDoubleQuoted($document),
			"'" => $this->renderDocTypePublicIdentifierSingleQuoted($document),
			'>' => $this->setQuirksMode(true, fn() => $document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		$this->quirks = true;
		return $document->reconsume($this->renderBogusDocType(...));
	}

	private function renderBeforeDocTypePublicIdentifier(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeDocTypePublicIdentifier($document),
			'"' => $this->renderDocTypePublicIdentifierDoubleQuoted($document),
			"'" => $this->renderDocTypePublicIdentifierSingleQuoted($document),
			'>' => $this->setQuirksMode(true, fn() => $document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		$this->quirks = true;
		return $document->reconsume($this->renderBogusDocType(...));
	}

	private function renderDocTypePublicIdentifierDoubleQuoted(Document $document): Document
	{
		renderDocTypePublicIdentifierDoubleQuoted:
		$result = match ($document->consume()) {
			'"' => $this->renderAfterDocTypePublicIdentifier($document),
			'>' => $this->setQuirksMode(true, fn() => $document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		goto renderDocTypePublicIdentifierDoubleQuoted;
	}

	private function renderAfterDocTypePublicIdentifier(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBetweenDocTypePublicAndSystemIdentifiers($document),
			'>' => $document,
			'"' => $this->renderDocTypeSystemIdentifierDoubleQuoted($document),
			"'" => $this->renderDocTypeSystemIdentifierSingleQuoted($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		$this->quirks = true;
		return $document->reconsume($this->renderBogusDocType(...));
	}

	private function renderBetweenDocTypePublicAndSystemIdentifiers(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBetweenDocTypePublicAndSystemIdentifiers($document),
			'>' => $document,
			'"' => $this->renderDocTypeSystemIdentifierDoubleQuoted($document),
			"'" => $this->renderDocTypeSystemIdentifierSingleQuoted($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		$this->quirks = true;
		return $document->reconsume($this->renderBogusDocType(...));
	}

	private function renderDocTypeSystemIdentifierDoubleQuoted(Document $document): Document
	{
		again:
		$result = match ($document->consume()) {
			'"' => $this->renderAfterDocTypeSystemIdentifier($document),
			'>' => $this->setQuirksMode(true, fn() => $document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		goto again;
	}

	private function renderAfterDocTypeSystemIdentifier(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderAfterDocTypeSystemIdentifier($document),
			'>' => $document,
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		return $document->reconsume($this->renderBogusDocType(...));
	}

	private function renderDocTypeSystemIdentifierSingleQuoted(Document $document): Document
	{
		again:
		$result = match ($document->consume()) {
			"'" => $this->renderAfterDocTypeSystemIdentifier($document),
			'>' => $this->setQuirksMode(true, fn() => $document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		goto again;
	}

	private function renderDocTypePublicIdentifierSingleQuoted(Document $document): Document
	{
		again:
		$result = match ($document->consume()) {
			"'" => $this->renderAfterDocTypePublicIdentifier($document),
			'>' => $this->setQuirksMode(true, fn() => $document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		goto again;
	}

	private function renderAfterDocTypeSystemKeyword(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeDocTypeSystemIdentifier($document),
			'"' => $this->renderDocTypeSystemIdentifierDoubleQuoted($document),
			"'" => $this->renderDocTypeSystemIdentifierSingleQuoted($document),
			'>' => $this->setQuirksMode(true, fn() => $document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		$this->quirks = true;
		return $document->reconsume($this->renderBogusDocType(...));
	}

	private function renderBeforeDocTypeSystemIdentifier(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeDocTypeSystemIdentifier($document),
			'"' => $this->renderDocTypeSystemIdentifierDoubleQuoted($document),
			"'" => $this->renderDocTypeSystemIdentifierSingleQuoted($document),
			'>' => $this->setQuirksMode(true, fn() => $document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		$this->quirks = true;
		return $document->reconsume($this->renderBogusDocType(...));
	}

	private function renderBogusDocType(Document $document): Document
	{
		$result = match ($document->consume()) {
			'>' => $document,
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			return $document;
		}
		return $this->renderBogusDocType($document);
	}

	private string $originalAttributeName = '';

	private function renderAttributeName(Document $document): Document
	{
		//$selectionStart = $document->mark();
		renderAttributeName:
		$result = match ($char = $document->consume()) {
			"\t", "\n", "\f", " ", "/", ">" => $document->reconsume($this->renderAfterAttributeName(...)),
			'=' => $this->renderBeforeAttributeValue($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		$this->attributeName .= strtolower($char);
		$this->originalAttributeName .= $char;
		goto renderAttributeName;
	}

	private function renderBeforeAttributeValue(Document $document): Document
	{
		return match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeAttributeValue($document),
			'"' => $this->renderAttributeValueDoubleQuoted($document),
			"'" => $this->renderAttributeValueSingleQuoted($document),
			'>' => (function () use ($document) {
				$this->setAttribute($this->originalAttributeName);
				return $document;
			})(),
			default => $this->renderAttributeValueUnquoted($document),
		};
	}

	private function renderAttributeValueDoubleQuoted(Document $document): Document
	{
		renderAttributeValueDoubleQuoted:
		$result = match ($char = $document->consume()) {
			'"' => $this->renderAfterAttributeValueQuoted($document),
			'&' => $this->renderAttributeValueDoubleQuoted($this->renderCharacterReference($document)),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		$this->attributeValue .= $char;
		goto renderAttributeValueDoubleQuoted;
	}

	private function renderAfterAttributeValueQuoted(Document $document): Document
	{
		$document = $this->processAttributes($document);

		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeAttributeName($document),
			'/' => $this->renderSelfClosingStartTag($document),
			'>' => $document,
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		return $document->reconsume($this->renderBeforeAttributeName(...));
	}

	private function processAttributes(Document $document): Document
	{
		if ($this->blockAttributes) {
			return $document;
		}

		$originalValue = $this->blobber->replaceBlobs($this->attributeValue, fn($_) => $_);
		$value = $this->escaper->escapeHtmlAttr($originalValue);
		// escaper doesn't escape single quotes, so we do that here.
		$value = str_replace("'", '&#39;', $value);
		$this->setAttribute($this->originalAttributeName, $originalValue);
		if ($value !== $this->attributeValue) {
			// we need to update the rendered html too...
			$here = $document->mark();
			$start = $here - strlen($this->attributeValue) - 1;
			$document = $document
				->snip($start, $here - 1)
				->insert($value, $start);
		}
		return $document;
	}

	private function setAttribute(string $key, string|true $value = true): void
	{
		if ($this->blockAttributes) {
			return;
		}
		$this->attributes[$key] = $value;
	}

	private function renderAttributeValueSingleQuoted(Document $document): Document
	{
		renderAttributeValueSingleQuoted:
		$result = match ($char = $document->consume()) {
			"'" => $this->renderAfterAttributeValueQuoted($document),
			'&' => $this->renderAttributeValueSingleQuoted($this->renderCharacterReference($document)),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		$this->attributeValue .= $char;
		goto renderAttributeValueSingleQuoted;
	}

	private function renderAttributeValueUnquoted(Document $document): Document
	{
		renderAttributeValueUnquoted:
		$result = match ($char = $document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeAttributeName($document),
			'&' => $this->renderAttributeValueUnquoted($this->renderCharacterReference($document)),
			'>' => $document,
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		$this->attributeValue .= $char;
		goto renderAttributeValueUnquoted;
	}

	private function renderAfterAttributeName(Document $document): Document
	{
		if (!empty($this->attributeName) && !array_key_exists($this->originalAttributeName, $this->attributes)) {
			$this->setAttribute($this->originalAttributeName);
		}
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderAfterAttributeName($document),
			'/' => $this->renderSelfClosingStartTag($document),
			'=' => $this->renderBeforeAttributeValue($document),
			'>' => $document,
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		$this->attributeName = '';
		$this->attributeValue = '';
		$this->originalAttributeName = '';
		return $document->reconsume($this->renderAttributeName(...));
	}

	private function renderRcDataEndTagName(Document $document): Document
	{
		read:
		$char = $document->consume();

		if (strtolower($this->nameBuffer) === $this->mustMatch) {
			$result = match ($char) {
				"\t", "\n", "\f", " " => $this->renderBeforeAttributeName($document),
				'/' => $this->renderSelfClosingStartTag($document),
				'>' => $document,
				default => null,
			};
			$this->mustMatch = $result ? null : $this->mustMatch;
			return $result ?? $document->reconsume($this->renderRCData(...));
		}

		if (ctype_alpha($char)) {
			$this->nameBuffer .= $char;
			goto read;
		}

		return $document->reconsume($this->renderRCData(...));
	}

	private function renderScriptDataDoubleEscapeStart(Document $document): Document
	{
		$this->nameBuffer = '';
		read:
		$result = match ($char = $document->consume()) {
			"\t", "\n", "\f", " ", "/", ">" => strtolower(
				$this->nameBuffer
			) === 'script' ? $this->renderScriptDataDoubleEscaped($document) : $this->renderScriptDataEscaped(
				$document
			),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if (ctype_alpha($char)) {
			$this->nameBuffer .= $char;
			goto read;
		}
		return $document->reconsume($this->renderScriptDataEscaped(...));
	}

	private function renderScriptDataDoubleEscaped(Document $document): Document
	{
		read:
		$result = match ($document->consume()) {
			'-' => $this->renderScriptDataDoubleEscapedDash($document),
			'<' => $this->renderScriptDataDoubleEscapedLessThanSign($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			return $document;
		}
		goto read;
	}

	private function renderScriptDataDoubleEscapedDash(Document $document): Document
	{
		$result = match ($document->consume()) {
			'-' => $this->renderScriptDataDoubleEscapedDashDash($document),
			'<' => $this->renderScriptDataDoubleEscapedLessThanSign($document),
			default => null,
		};
		return $result ?? $this->renderScriptDataDoubleEscaped(...);
	}

	private function renderScriptDataDoubleEscapedDashDash(Document $document): Document
	{
		$result = match ($document->consume()) {
			'<' => $this->renderScriptDataDoubleEscapedLessThanSign($document),
			'>' => $this->renderScriptData($document),
			'-' => $document,
			default => $this->renderScriptDataDoubleEscaped(...),
		};
		return $result ?? $this->renderScriptDataDoubleEscaped(...);
	}

	private function renderScriptDataDoubleEscapedLessThanSign(Document $document): Document
	{
		return match ($char = $document->consume()) {
			'/' => $this->renderScriptDataDoubleEscapeEnd($document),
			default => $this->renderScriptDataDoubleEscaped($document),
		};
	}

	private function renderScriptDataDoubleEscapeEnd(Document $document): Document
	{
		$this->nameBuffer = '';
		read:
		$result = match ($char = $document->consume()) {
			"\t", "\n", "\f", " ", "/", ">" => strtolower(
				$this->nameBuffer
			) === 'script' ? $this->renderScriptDataEscaped($document) : $this->renderScriptDataDoubleEscaped(
				$document
			),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if (ctype_alpha($char)) {
			$this->nameBuffer .= $char;
			goto read;
		}
		return $document->reconsume($this->renderScriptDataDoubleEscaped(...));
	}

	private function renderScriptDataEscapedEndTagName(Document $document): Document
	{
		read:
		$char = $document->consume();

		if (strtolower($this->nameBuffer) === $this->mustMatch) {
			$result = match ($char) {
				"\t", "\n", "\f", " " => $this->renderBeforeAttributeName($document),
				'/' => $this->renderSelfClosingStartTag($document),
				'>' => $document,
				default => null,
			};
			$this->mustMatch = $result ? null : $this->mustMatch;
			return $result ?? $document->reconsume($this->renderScriptDataEscaped(...));
		}

		if (ctype_alpha($char)) {
			$this->nameBuffer .= $char;
			goto read;
		}

		return $document->reconsume($this->renderScriptDataEscaped(...));
	}

	private function renderScriptDataEndTagName(Document $document): Document
	{
		read:
		$char = $document->consume();

		if (strtolower($this->nameBuffer) === $this->mustMatch) {
			$result = match ($char) {
				"\t", "\n", "\f", " " => $this->renderBeforeAttributeName($document),
				'/' => $this->renderSelfClosingStartTag($document),
				'>' => $document,
				default => null,
			};
			$this->mustMatch = $result ? null : $this->mustMatch;
			return $result ?? $document->reconsume($this->renderScriptData(...));
		}

		if (ctype_alpha($char)) {
			$this->nameBuffer .= $char;
			goto read;
		}

		return $document->reconsume($this->renderScriptData(...));
	}
}
