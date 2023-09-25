<?php

namespace Bottledcode\SwytchFramework\Template\Parser;

use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;
use DI\FactoryInterface;
use LogicException;
use olvlvl\ComposerAttributeCollector\TargetClass;
use Psr\Log\LoggerInterface;

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

	public function __construct(
		public FactoryInterface $factory,
		private EscaperInterface $escaper,
		private LoggerInterface $logger
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
		$code = $this->escaper->makeBlobs($code);
		$document = new Document($code);
		return $this->renderData($document)->code;
	}

	private function renderData(Document $document): Document
	{
		restartData:
		$result = match ($document->consume()) {
			'&' => $this->renderData($this->renderCharacterReference($document)),
			'<' => $this->renderOpenTag($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			return $document;
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

	private function renderOpenTag(Document $document): Document
	{
		$result = match (strtolower($document->consume())) {
			'!' => $this->renderDeclarationOpen($document),
			'/' => $this->renderEndTagOpen($document),
			'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z' =>
			$document->reconsume($this->renderTagName(...)),
			'?' => $document->reconsume($this->renderBogusComment(...)),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		return $this->renderData($document);
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
			'>' => $this->setQuirksMode(true, fn() => $this->renderData($document)),
			default => null,
		};
		return $result ?? $document->reconsume($this->renderDocTypeName(...));
	}

	private function setQuirksMode(bool $mode, \Closure $next): Document
	{
		$this->quirks = $mode;
		return $next();
	}

	private function renderCData(Document $document): Document
	{
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
		return $this->renderCData($document);
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
			'>' => $this->renderData($document),
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
			'>' => $this->renderData($document),
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
			'>' => $this->renderData($document),
			default => $this->renderComment($document),
		};
	}

	private function renderCommentEnd(Document $document): Document
	{
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}

		$result = match ($document->consume()) {
			'>' => $this->renderData($document),
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
			'>' => $this->renderData($document),
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
		$result = match ($document->consume()) {
			'>' => $this->renderData($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			return $document;
		}
		return $this->renderBogusComment($document);
	}

	private function renderEndTagOpen(Document $document): Document
	{
		if (ctype_alpha($char = $document->consume())) {
			$this->nameBuffer = null;
			$this->isClosing = true;
			return $document->reconsume($this->renderTagName(...));
		}
		if ($char === '>') {
			return $this->renderData($document);
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}
		return $this->renderBogusComment($document);
	}

	private function renderDocTypeName(Document $document): Document
	{
		renderDocTypeName:
		$result = match ($char = $document->consume()) {
			"\t", "\n", "\f", " " => $this->renderAfterDocTypeName($document),
			'>' => $this->renderData($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		$this->docTypeName[] = $char;
		goto renderDocTypeName;
	}

	private function renderAfterDocTypeName(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderAfterDocTypeName($document),
			'>' => $this->renderData($document),
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
			'>' => $this->setQuirksMode(true, fn() => $this->renderData($document)),
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
			'>' => $this->setQuirksMode(true, fn() => $this->renderData($document)),
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
		$result = match ($document->consume()) {
			'"' => $this->renderAfterDocTypePublicIdentifier($document),
			'>' => $this->setQuirksMode(true, fn() => $this->renderData($document)),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		return $this->renderDocTypePublicIdentifierDoubleQuoted($document);
	}

	private function renderAfterDocTypePublicIdentifier(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBetweenDocTypePublicAndSystemIdentifiers($document),
			'>' => $this->renderData($document),
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
			'>' => $this->renderData($document),
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
		$result = match ($document->consume()) {
			'"' => $this->renderAfterDocTypeSystemIdentifier($document),
			'>' => $this->setQuirksMode(true, fn() => $this->renderData($document)),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		return $this->renderDocTypeSystemIdentifierDoubleQuoted($document);
	}

	private function renderAfterDocTypeSystemIdentifier(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderAfterDocTypeSystemIdentifier($document),
			'>' => $this->renderData($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		return $document->reconsume($this->renderBogusDocType(...));
	}

	private function renderDocTypeSystemIdentifierSingleQuoted(Document $document): Document
	{
		$result = match ($document->consume()) {
			"'" => $this->renderAfterDocTypeSystemIdentifier($document),
			'>' => $this->setQuirksMode(true, fn() => $this->renderData($document)),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		return $this->renderDocTypeSystemIdentifierSingleQuoted($document);
	}

	private function renderDocTypePublicIdentifierSingleQuoted(Document $document): Document
	{
		$result = match ($document->consume()) {
			"'" => $this->renderAfterDocTypePublicIdentifier($document),
			'>' => $this->setQuirksMode(true, fn() => $this->renderData($document)),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			$this->quirks = true;
			return $document;
		}
		return $this->renderDocTypePublicIdentifierSingleQuoted($document);
	}

	private function renderAfterDocTypeSystemKeyword(Document $document): Document
	{
		$result = match ($document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeDocTypeSystemIdentifier($document),
			'"' => $this->renderDocTypeSystemIdentifierDoubleQuoted($document),
			"'" => $this->renderDocTypeSystemIdentifierSingleQuoted($document),
			'>' => $this->setQuirksMode(true, fn() => $this->renderData($document)),
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
			'>' => $this->setQuirksMode(true, fn() => $this->renderData($document)),
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
			'>' => $this->renderData($document),
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

	private function renderTagName(Document $document): Document
	{
		renderTagName:
		$result = match ($char = $document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeAttributeName($document),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		if ($document->isEof()) {
			throw new LogicException('Unexpected end of file');
		}

		if ($char === '>' && $this->renderingTag === $this->nameBuffer) {
			$this->childrenRendered($document);
			return $this->renderData($document);
		}

		if (($char === '>' || $char === '/') && $this->renderingTag === null && $this->components[$this->nameBuffer] ?? false) {
			$this->renderingTag = $this->nameBuffer;
			$this->prepareRender($document);
			if ($char === '>') {
				return $this->renderData($document);
			}

			$this->childrenRendered($document);
			return $this->renderSelfClosingStartTag($document);
		}

		if($char === '/') {
			return $this->renderSelfClosingStartTag($document);
		}

		if ($char === '>') {
			return $this->renderData($document);
		}

		$this->nameBuffer .= $char;
		goto renderTagName;
	}

	private function childrenRendered(Document $document): void
	{
		$this->childrenEnd = max($this->childrenStart, $document->mark() - 2 - strlen($this->renderingTag));
		$component = $this->renderingTag;
		$attributes = $this->attributes;
		// todo: render component
	}

	private function prepareRender(Document $document): void
	{
		$this->childrenStart = $document->mark();
	}

	private array $attributes = [];
	private string $attributeName = '';
	private string|bool $attributeValue = false;

	private function renderBeforeAttributeName(Document $document): Document
	{
		$oops = function(string $c, \Closure $next) use ($document) {
			$this->attributeName = $c;
			$this->attributeValue = '';
			return $next($document);
		};

		$result = match($char = $document->consume()) {
			"\t", "\n", "\f", " " => $this->renderBeforeAttributeName($document),
			'/' => $document->reconsume($this->renderAfterAttributeName(...)),
			'=' => $oops($char, $this->renderAttributeName(...)),
			default => null,
		};
		if ($result !== null) {
			return $result;
		}
		$this->attributeName = '';
		$this->attributeValue = true;
		return $document->reconsume($this->renderAttributeName(...));
	}

	private function renderAttributeName(Document $document): Document {
		try {
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
		} finally {
			$this->attributes[$this->attributeName] = true;
		}
	}

	private function renderAfterAttributeName(Document $document): Document {
		// todo: next is https://html.spec.whatwg.org/#after-attribute-name-state
	}
}
