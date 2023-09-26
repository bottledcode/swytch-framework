<?php

namespace Bottledcode\SwytchFramework\Template\Parser;

use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;
use Closure;
use DI\FactoryInterface;
use Laminas\Escaper\Escaper;
use LogicException;
use olvlvl\ComposerAttributeCollector\TargetClass;

/**
 */
class Streaming
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

	public function __construct(
		public FactoryInterface $factory,
		private EscaperInterface $blobber,
		private Escaper $escaper,
	) {
	}

	public function registerComponent(TargetClass $component): void
	{
		$this->components[mb_strtolower($component->attribute->name)] = $component->name;
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
		$this->nameBuffer = '';
		$this->isClosing = false;
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

		$document = $this->renderTagName($document);

		// at this point, we know everything about the tag, so we can see if we need to render it.
		if (array_key_exists($this->nameBuffer, $this->components)) {
			// we can render it, but check to see if we already are rendering a tag

			// prevent us from rendering a nested tag
			if ($this->renderingTag === $this->nameBuffer) {
				$this->nesting++;
				return $document;
			}

			if (empty($this->renderingTag)) {
				// let's render the tag...
				$this->renderingTag = $this->nameBuffer;
				$this->cutStart = $starting - 1;
				$this->childrenStart = $document->mark();
			}
		}

		// everything is now configured to render the tag, but we only do that now iff the tag is self-closing
		if ($this->selfClosing) {
			$this->renderComponent($document);
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
			$this->attributeName = $c;
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

	private function renderComponent(Document $document): Document
	{
		$children = substr($document->code, $this->childrenStart, $this->childrenEnd - $this->childrenStart);
		$componentName = $this->components[$this->renderingTag];

		/**
		 * @var $component CompiledComponent
		 */
		$component = $this->factory->make(
			CompiledComponent::class,
			['name' => $this->renderingTag, 'type' => $componentName]
		);
		$rendering = $component->renderToString(['children' => $children, ...$this->attributes]);
		$rendering = $this->blobber->makeBlobs($rendering);
		$document = $document->snip($this->cutStart, $this->cutEnd)->insert($rendering, $this->cutStart)->seek(
			$this->cutStart
		);


		return $document;
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
		if ($this->renderingTag !== $this->nameBuffer) {
			return $document;
		}

		// check nesting
		if (--$this->nesting > 0) {
			return $document;
		}

		// everything is in place... render the tag
		$this->cutEnd = $document->mark();
		$this->childrenEnd = $childrenEnd - 2;

		return $this->renderComponent($document);
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
		goto renderAttributeName;
	}

	private function renderBeforeAttributeValue(Document $document): Document
	{
		return match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeAttributeValue($document),
			'"' => $this->renderAttributeValueDoubleQuoted($document),
			"'" => $this->renderAttributeValueSingleQuoted($document),
			'>' => (function () use ($document) {
				$this->attributes[$this->attributeName] = true;
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
		$this->attributeValue = $this->blobber->replaceBlobs(
			$this->attributeValue,
			$this->escaper->escapeHtmlAttr(...)
		);
		// escaper doesn't escape single quotes, so we do that here.
		$this->attributeValue = str_replace("'", '&#39;', $this->attributeValue);
		$this->attributes[$this->attributeName] = $this->attributeValue;

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
		if ($this->attributes[$this->attributeName] === true) {
			$this->attributes[$this->attributeName] = '';
		}
		$this->attributes[$this->attributeName] .= $char;
		goto renderAttributeValueUnquoted;
	}

	private function renderAfterAttributeName(Document $document): Document
	{
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
		return $document->reconsume($this->renderAttributeName(...));
	}
}
