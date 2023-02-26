<?php

namespace Bottledcode\SwytchFramework\Template;

use Composer\Autoload\ClassLoader;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use PhpParser\ParserFactory;
use ReflectionClass;

class StringExtractor {
	public function __construct() {}

	public function getStringsFrom(string $filename): array {
		$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
		$ast = $parser->parse(file_get_contents($filename));
		
		return [];
	}
}
