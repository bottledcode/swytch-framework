<!DOCTYPE html>
<html lang="en"><body>
<script><test></script>
<nested><?= new Bottledcode\SwytchFramework\Template\CompiledComponent(array (
  'Nested' => 
  array (
    'compiled' => '
<div>
This is a title:
<title><?= new Bottledcode\\SwytchFramework\\Template\\CompiledComponent(array (
), "Title") ?>
</</title></div>',
    'original' => '
<div>
This is a title:
<title/>
</',
  ),
), "Nested") ?>
</nested>
</body>

</html>
