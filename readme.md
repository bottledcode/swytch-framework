# Swytch Framework

The Swytch Framework is a new, fledgling, but powerful framework allowing you to write HTML inline with your application
logic, including API endpoints. It is built on top of [htmx](https://htmx.org/) for the browser-side heavy-lifting,
and [html5-php](https://github.com/Masterminds/html5-php) to handle the HTML parsing.

Features:

- Write HTML inline with your PHP code, relying on context-aware escaping.
- Keep you API logic near the HTML that uses it.
- Application routing via HTML (similar to ReactRouter).
- Automatic CSRF protection.
- Context-Aware escaping.
- Automatic HTML5 validation.
- Authorization and authentication aware routing and rendering.
- Browser cache control.
- Builtin support for translations.

> NOTE:
> This is currently pre-production software and is not recommended for production use.

## Example Component

```php
#[\Bottledcode\SwytchFramework\Template\Attributes\Component('example')]
class ExampleComponent {
    use \Bottledcode\SwytchFramework\Template\Traits\RegularPHP;
    use \Bottledcode\SwytchFramework\Template\Traits\Htmx;
    
    #[\Bottledcode\SwytchFramework\Router\Attributes\Route(\Bottledcode\SwytchFramework\Router\Method::POST, '/api/number')]
    public function getNumber(string $target_id, array $state): int {
        return $this->rerender($target_id, [...$state, 'number' => random_int(0, 100)]);
    }
    
    public function render(string $name, int $number = null): string {
        if($number === null) {
            $number = random_int(0, 100);
        }
    
        $this->begin();
        ?>
        <div>
            <h1>Hello, {<?= $name ?>}</h1>
            <form hx-post="/api/number">
                <p>Here is a random number: {<?= $number ?>}</p>
                <button type="submit">Generate a new random number</button>
            </form>
        </div>
        <?php
        return $this->end();
    }
}
```
