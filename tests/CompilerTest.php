<?php

use Bottledcode\SwytchFramework\Template\Compiler;

it('compiles a simple template', function () {
	$compiler = new Compiler(__DIR__ . '/Templates/simple-template.htm');
	expect($compiler->template)->toBe(__DIR__ . '/Templates/simple-template.htm');
	expect($compiler->compile())->toOutput(__DIR__ . '/Templates/simple-template-compiled.php');
});

it('compiles a simple component', function () {
	$compiler = new Compiler(__DIR__ . '/Templates/title-component.htm');
	require_once __DIR__ . '/Templates/Title.php';
	$compiler->registerComponent(Title::class);
	expect($compiler->compile())->toOutput(__DIR__ . '/Templates/title-component-compiled.php');
});

it('compiles a nested component', function () {
	$compiler = new Compiler(__DIR__ . '/Templates/nested-template.htm');
	require_once __DIR__ . '/Templates/Title.php';
	require_once __DIR__ . '/Templates/Nested.php';
	$compiler->registerComponent(Title::class);
	$compiler->registerComponent(Nested::class);
	expect($compiler->compile())->toOutput(__DIR__ . '/Templates/nested-template-compiled.php');
});

it('compiles a component with variables', function () {
	$compiler = new Compiler(__DIR__ . '/Templates/variable-template.htm');
	require_once __DIR__ . '/Templates/VariableTemplate.php';
	$compiler->registerComponent(VariableTemplate::class);
	expect($compiler->compile())->toOutput(__DIR__ . '/Templates/variable-template-compiled.php');
});
