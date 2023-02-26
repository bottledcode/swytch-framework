<?php

it('turns a string into a blob', function() {
	$simple = 'this is {a blob}';
	$blobber = new \Bottledcode\SwytchFramework\Template\Escapers\Variables();
	$blob = $blobber->makeBlobs($simple);
	$this->assertEquals('this is __BLOB__0__', $blob);
	$this->assertEquals('this is a blob', $blobber->replaceBlobs($blob, fn($blob) => $blob));
});

it('handles nested blobs', function() {
	$simple = 'this is a {nested {{blob}}}';
	$blobber = new \Bottledcode\SwytchFramework\Template\Escapers\Variables();
	$blob = $blobber->makeBlobs($simple);
	$this->assertEquals('this is a __BLOB__0__', $blob);
	$this->assertEquals('this is a nested {blob}', $blobber->replaceBlobs($blob, fn($blob) => $blob));
});

it('handles starting nests', function() {
	$simple = 'this is a {{{nested}} blob}}} of things';
	$blobber = new \Bottledcode\SwytchFramework\Template\Escapers\Variables();
	$blob = $blobber->makeBlobs($simple);
	$this->assertEquals('this is a __BLOB__0__ of things', $blob);
	$this->assertEquals('this is a {nested} blob} of things', $blobber->replaceBlobs($blob, fn($blob) => $blob));
});

it('handles non-matching braces', function() {
	$simple = 'this is a {nested {{blob {{} of things';
	$blobber = new \Bottledcode\SwytchFramework\Template\Escapers\Variables();
	$blob = $blobber->makeBlobs($simple);
	$this->assertEquals('this is a __BLOB__0__ of things', $blob);
	$this->assertEquals('this is a nested {blob { of things', $blobber->replaceBlobs($blob, fn($blob) => $blob));
});

it('can handle a json encoding', function () {
	$json = \Bottledcode\SwytchFramework\Template\Escapers\Variables::escape(json_encode(['a' => 'b']));
	$blobber = new \Bottledcode\SwytchFramework\Template\Escapers\Variables();
	$blob = $blobber->makeBlobs("this is a {{$json}} object");
	$this->assertEquals('this is a __BLOB__0__ object', $blob);
	$this->assertEquals('this is a {"a":"b"} object', $blobber->replaceBlobs($blob, fn($blob) => $blob));
});

it('can handle complete string', function () {
	$string = '{{{{{fancy}';
	$blobber = new \Bottledcode\SwytchFramework\Template\Escapers\Variables();
	$blob = $blobber->makeBlobs($string);
	$this->assertEquals('__BLOB__0__', $blob);
	$this->assertEquals('{{fancy', $blobber->replaceBlobs($blob, fn($blob) => $blob));
});

it('can handle being at the start', function () {
	$string = '{some }}}}} text';
	$blobber = new \Bottledcode\SwytchFramework\Template\Escapers\Variables();
	$blob = $blobber->makeBlobs($string);
	$this->assertEquals('__BLOB__0__ text', $blob);
	$this->assertEquals('some }} text', $blobber->replaceBlobs($blob, fn($blob) => $blob));
});

it('can handle being at the end', function() {
	$string = 'some {{{{{ text}';
	$blobber = new \Bottledcode\SwytchFramework\Template\Escapers\Variables();
	$blob = $blobber->makeBlobs($string);
	$this->assertEquals('some __BLOB__0__', $blob);
	$this->assertEquals('some {{ text', $blobber->replaceBlobs($blob, fn($blob) => $blob));
});
