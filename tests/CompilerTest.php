<?php

use Bottledcode\SwytchFramework\Template\Compiler;

test('simple template results in no changes', function () {
	$compiler = new Compiler(__DIR__ . '/Templates/simple-template.htm');
	expect($compiler->template)->toBe(__DIR__ . '/Templates/simple-template.htm');
	expect($compiler->compile())->toOutput(__DIR__ . '/Templates/simple-template-compiled.php');
});

test('a title component is rendered', function () {
	$compiler = new Compiler(__DIR__ . '/Templates/title-component.htm');
	require_once __DIR__ . '/Templates/Title.php';
	$compiler->registerComponent(Title::class);
	expect($compiler->compile())->toOutput(__DIR__ . '/Templates/title-component-compiled.php');
});

test('a nested component', function () {
	$compiler = new Compiler(__DIR__ . '/Templates/nested-template.htm');
	require_once __DIR__ . '/Templates/Title.php';
	require_once __DIR__ . '/Templates/Nested.php';
	$compiler->registerComponent(Title::class);
	$compiler->registerComponent(Nested::class);
	expect($compiler->compile())->toOutput(__DIR__ . '/Templates/nested-template-compiled.php');
});
