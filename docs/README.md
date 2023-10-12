# The Swytch Framework

The Swytch Framework is a storage-agnostic framework inspired by JSX, Vue, and other Javascript frameworks. It is
designed to accelerate the development of web applications and APIs by providing a simple, declarative, and powerful API
while writing regular HTML and PHP, powered by [htmx](https://htmx.org/).

## Getting Started

The easiest way to get started is to use the [Swytch Template](https://github.com/bottledcode/swytch-template/generate).
This is a ready-to-go template that you can use to get started with the Swytch Framework.

The template includes a `Dockerfile` that you can use to build and run your application or as a template for how an
environment could be configured.

### The Index Component

Every Swytch project requires a 'root component' and in this template, the root component is the `index` component:

```php
<?php
#[Component('index')]
readonly class Index
{
    use RegularPHP;

    public function __construct(private LanguageAcceptor $language, private HeadTagFilter $htmlHead)
    {
    }

    public function render()
    {
        $this->htmlHead->setTitle(__('Hello World'));

        $this->begin();
        ?>
        <!DOCTYPE html>
        <html lang="{<?= $this->language->currentLanguage ?>}">
        <head>
        </head>
        <body>
        <h1>{<?= __('Hello world') ?>}</h1>
        <swytch:route path="/" method="GET">
            <counter></counter>
        </swytch:route>
        <swytch:defaultRoute>
            <h1>{<?= __('404') ?>}</h1>
        </swytch:defaultRoute>
        </body>
        </html>
        <?php
        return $this->end();
    }
}
```

This component is the entry point for the web-application and contains the initial HTML that is sent to the browser.

### The Counter Component

The example `counter` component is a simple component that allows 'counting' a variable:

```php
<?php
#[Component('counter')]
readonly class Counter
{
    use RegularPHP;
    use Htmx;

    public function __construct(private Headers $headers, private StreamingCompiler $compiler)
    {
    }

    /**
     * In real life, you would probably do a lot more in here. But this just show how it works.
     *
     * @param int $count The current count
     * @return string The rendered HTML
     */
    #[Route(Method::POST, '/api/count/add')]
    public function add(int $count): string
    {
        // we want to place the fragment in the #count div
        $this->retarget('#count');
        // now rerender the component but only the fragment with the id count-from
        return $this->renderFragment('count-form', $this->render($count + 1));
    }

    public function render(int $count = 0)
    {
        $this->begin();
        ?>
        <div id="count"
             xmlns:swytch="file://../vendor/bottledcode/swytch-framework/swytch.xsd">
            <!-- note: the fragment tag is NOT rendered in the client -->
            <swytch:fragment id="count-form">
                <form hx-post="/api/count">
                    <input type="hidden" name="count" value="<?= $count ?>">
                    <h1>{<?= n__('Current count:', 'Current count:', $count) ?>} {<?= $count ?>}</h1>
                    <button type="submit" hx-post="/api/count/add"> +</button>
                    <button type="submit" hx-post="/api/count/sub"> -</button>
                </form>
            </swytch:fragment>
        </div>
        <?php
        return $this->end();
    }

    #[Route(Method::POST, '/api/count/sub')]
    public function sub(int $count): string
    {
        $this->retarget('#count');
        return $this->renderFragment('count-form', $this->render($count - 1));
    }
}
```

## What is this?

The Swytch Framework was created out of frustration with having to create a front-end and back-end for every project.
Why not write it once in the same language? This framework will allow you to write you front-end and back-end in not
Javascript, provide an API for non-browser clients, and allow you to deliver value faster than ever.

## How does it work?

Unlike other PHP frameworks with a dedicated templating language, the Swytch Framework's templatating language is HTML5.
Thus, you can take advantage of PHP's built-in templating functionality to create your app. The browser side is powered
by [htmx](https://htmx.org/).

## Escaping, CSRF, and Security

Looking at the example above, you may be wondering how to prevent XSS attacks. The Swytch Framework automatically

- escapes all output inside `{` brackets `}` automatically.
- provides a CSRF token on all `<form>` tags.

# Example Applications

The Swytch Application is not yet released, but we felt that this framework is just too awesome to wait for. So, we
built an example application to show how it works in 'real life'. Check out [once](https://once.getswytch.com) to see
the framework in action or [view the source](https://github.com/bottledcode/once) to see how it works.

# Features

## Routing

For components, routing is performed very similar to how you might expect it to work in a Javascript framework with JSX.
The Swytch Framework uses a built-in `swytch:Route` and `swytch:DefaultRoute` component to perform routing.

```html

<swytch:Route path="/" method="GET">
    <Home></Home>
</swytch:Route>
<swytch:DefaultRoute>
    <NotFound></NotFound>
</swytch:DefaultRoute>
```

The built-in `Route` component is also a `DataProvider` component that allows you to inject the value of route
parameters:

```html

<swytch:Route path="/user/{:id}" method="GET">
    <User id="{{:id}}}"></User>
</swytch:Route>
```

For API endpoints, routing is performed using the `#[Route]` attribute:

```php
<?php
class Example {
  #[Route(Method::GET, '/api/example/:id')]
  public function example(string $id) {}
}
```

API endpoints also use the Symfony serializer, so you can accept complex types as parameters that are composed via route
parameters and body parameters. Note that API endpoints are expected to begin with `/api/`. This is currently a
hard-coded requirement; please open an issue if you have a use-case that requires this to be configurable.

## Context-Aware Escaping

The Swytch Framework automatically escapes all output inside `{` brackets `}` in your output. It is fully context-aware,
so it automatically knows to escape Javascript inside `<script>` tags, HTML inside `<div>` tags, CSS inside `<style>`,
etc. Unlike other frameworks, you don't have to remember to use the correct escape function inside your HTML.

```php
<script>console.log('{<?= $userInput ?>}')</script>
```

## Render fragments

The Swytch Framework allows you to render fragments of HTML and components, which is useful when you're processing a
form. See this essay for more information: https://htmx.org/essays/template-fragments/

```php
<?ph

#[Component('example')]
class Example {
  use \Bottledcode\SwytchFramework\Template\Traits\RegularPHP;
  use \Bottledcode\SwytchFramework\Template\Traits\Htmx;
  
  #[Route(Method::POST, '/api/example')]
  public function example(string $name): string {
    $this->retarget('#output');
    return $this->renderFragment('complex-fragment', $this->render($name, 'goodbye'));
  }
  
  public function render(string $name, string $say = 'hello') {
    $this->begin();
    ?>
    <div id="#output">
        <h1>Hello world</h1>
        <swytch:fragment hx-post="/api/example" hx-vals='{name: "{<?= $name ?>}"}' id="complex fragment">
        <p>{<?= $say ?>} {<?= $name ?>}</p>
        </swytch:fragment>
    </div>
    <?php
    return $this->end();
  }
}
```

In this example, the `rerenderFragment` function will rerender the component with the given state, but only return the
fragment to the browser. This prevents cluttering up the source code with a bunch of components that are only used once.

## Easy to Reason About

Unlike most frameworks, where you have to dig through layers of directories and files to find the code that is called by
an API endpoint, you can locate the API endpoint right beside the HTML that calls it. This makes it easy to reason about
your code and verify correctness during code reviews.

## Performance

Unlike other frameworks and template languages that have to parse and run all the code in your template, the Swytch
Framework only runs the code that is actually called. This means that your app will likely be faster than conventional
frameworks. For example, given the following template:

```html

<swytch:route path="/">
    <HugeComponent></HugeComponent>
</swytch:route>
<swytch:DefaultRoute>
    <NotFound></NotFound>
</swytch:DefaultRoute>
```

When the user goes to a non-existent page, the Swytch Framework will only run the code inside the `NotFound` component.

## Tamper Protection

The Swytch Framework provides a built-in CSRF token that is automatically added to all `<form>` tags, signed and
validated via a secret key. This is fully pluggable, so you can use your own CSRF tokens.

## Customizable

The Swytch Framework is fully customizable for your needs. However, it is **not** compatible with PSR middlewares as
this framework does **not** handle requests like traditional frameworks. Instead, a request goes through several phases:

1. The request is parsed, and it determines which type of request it is.
2. Preprocessing: The request is transformed (such as extracting request parameters of API requests).
3. Processing: The request is transformed into a response (rendering templates or calling API handlers).
4. Postprocessing: The response is transformed (such as adding headers).

## Authentication/Authorization

The Swytch Framework comes ready for any kind of authentication or authorization you need. For API requests, you can use
the `Authorized` attribute and for components, there is the `Authorized` and `Authenticated` attributes, allow you to
show/hide components based on the user's authentication status.

## Translation Aware

The Swytch Framework is translation-aware. You can use the `__` function (and friends) to translate strings and define
translations in a standard `.mo` file. The Swytch Framework will automatically detect the user's language and use the
correct translation.

# Writing Components

In the Swytch Framework, components are just regular classes decorated by the `Component` attribute and containing
a `render` function that returns a string. There are several helper traits that you can use to make your life easier,
such as `RegularPHP` and `Htmx`. These traits provide helper functions for rendering HTML and interfacing with htmx. For
example, the `RegularPHP` trait provides the `begin` and `end` functions, which allow you to write HTML in a PHP file
using output buffering.

Your constructor is called via dependency injection just before rendering, so you can inject any services you need, or
perform any other initialization. You can also inject the `ServerRequestInterface` to get access to the current request.

The component attribute takes a single argument, which is the name of the component used in HTML.

## Helper Traits for Components

### RegularPHP

The `RegularPHP` trait provides helper functions for rendering HTML via output buffering.

#### `$this->begin()`

Call this function to start output buffering.

#### `$this->end()`

This function returns the contents of the output buffer and stops output buffering.

#### example:

```php
<?php
#[Component('example')]
class Example {

  use RegularPHP;

  public function render() {
    $this->begin();
    ?>
    <h1>Hello world</h1>
    <?php
    return $this->end();
  }
}
```

### Htmx

The `Htmx` trait provides helper functions for interfacing with htmx in the browser. It also provides a `rerender`
function that allows you to rerender a component with the current [state](/state-and-forms).

This trait requires the `Compiler` and `Headers` services to be injected into the component's constructor.

#### `$this->dangerous(string $html)`

This function will dangerously output HTML without escaping it. This is useful for rendering HTML that you know is safe,
but may contain `{` brackets `}`.

#### `$this->html(string $html)`

This function will render arbitrary HTML and any components.
Requires the `StreamingCompiler $compiler` to be injected into the component constructor.

#### `$this->redirectClient(string $url)`

This function will redirect the client to the given URL. This is useful for redirecting the client after a form is
submitted.

#### `$this->refreshClient()`

This function will refresh the client's page.

#### `$this->replaceUrl(string $url)`

This function will replace the client's URL with the given URL without affecting the user's history.

#### `$this->reswap(HtmxSwap $swap)`

Change the swapping method for the current request.

#### `$this->retarget(string $target_id)`

Change the target element for the swap

#### `$this->trigger(array $events)`

Trigger the given events on the client.

#### `$this->historyPush(string $url)`

Push the given URL to the client's history.

#### `$this->renderFragment(string $fragmentId, string $html)`

Render a single fragment with from the given html.

#### `$this->retarget(string $target_id)`

Output the html in the given target element.

# Guides

## Configuration

There are some key configuration elements configured through environment variables (or overridden through DI):

| environment variable       | default value | description                                          | required |
|----------------------------|---------------|------------------------------------------------------|----------|
| SWYTCH_STATE_SECRET        | [NOT SET]     | The secret used to sign the state                    | yes      |
| SWYTCH_DEFAULT_LANGUAGE    | en            | The default language                                 | no       |
| SWYTCH_SUPPORTED_LANGUAGES | en            | A comma-separated list of supported languages        | no       |
| SWYTCH_LANGUAGE_DIR        | [NOT SET]     | The directory where the translation files are stored | no       |

With the exception the internal components of the Component Compiler, all aspects of the Swytch Framework are configured
through dependency injection which can be configured when constructing an `App` object.

Note that it is **required** to provide an authentication provider (even if it only returns `true`).

## Attributes

Attributes are handled via `olvlvl/composer-attribute-collector`. This allows attributes to be read from
a `vendor/attributes.php` file instead of parsing the source code of each class during runtime. While this significantly
improves runtime performance, you **_must_** remember to run `composer dump` after annotating a new component/class.
This is not ideal but a necessary evil. If you have a better solution, please let us know. :)

In the meantime, you can use phpstorm file watchers, or some other method to watch `.php` files and run `composer dump`
when files change.

## State and Forms

Every request in the Swytch Framework is 'stateless,' meaning that the state of the application is not stored on the
webserver.
Here's a full list of every field attached to a form.
If you include fields with the same names, things will probably break.
If, however, you want to change this,
we recommend creating a custom `DataProvider` to handle tracking state and delivering it to components.

| field name | description                                 |
|------------|---------------------------------------------|
| csrf_token | A CSRF token to prevent CSRF attacks        |
| target_id  | The id of the currently rendering component |

## Authentication/Authorization

Since much of the world uses various means to detect whether the current user is authenticated or not, if
you wish to use the `Authorized` and `Authenticated` attributes, you must implement the `AuthenticationServiceInterface`
interface and provide it during the construction of the `App` object.

```php
<?php

$app = new App(false, [
    AuthenticationServiceInterface::class => autowire(MyAuthenticationService::class)
]);

$app->run();
```

## Headers

PHP provides a `header()` function that allows you to set HTTP headers. However, this function is not very useful for
unit testing and the Swytch Framework uses PSR Request/Response objects under the hood. To solve this problem, the
framework provides a `Headers` service that allows you to set headers in a more testable way.

## HTML Head

The `HeadTagFilter` service allows you to hook into the rendering of the `<head>` tag and add your own tags. This is
extremely useful for adding `<meta>` tags, `<link>` tags, and `<script>` tags, or setting the OpenGraph or Twitter
meta-tags.

## Internationalization

The Swytch Framework considers internationalization as a first-class citizen. The framework uses `gettext/translator`
under the hood to provide a simple interface for translating strings. The framework also provides a `NumberHelper` class
to help when dealing with numbers and currencies (such as translating between `0.25` and `0,25`).

Global functions are provided to make it easy to translate strings:

| function name | description                                                    |
|---------------|----------------------------------------------------------------|
| __            | Translate a string                                             |
| noop_         | marks the string for translation but returns it unchanged.     |
| n__           | Translate a string with a plural form                          |
| p__           | Translate a string with a context                              |
| d__           | Translate a string with a domain                               |
| dp__          | Translate a string with a context and a domain                 |
| dn__          | Translate a string with a plural form and a domain             |
| np__          | Translate a string with a plural form and a context            |
| dnp__         | Translate a string with a plural form, a context, and a domain |

If you are using a translation service, it is recommended to escape the translated string using `{` brackets `}` to
prevent malicious translators from injecting arbitrary HTML into your application.

## Lifecycle Hooks

If required, you can inject arbitrary behavior via Lifecycle Hooks. These are interfaces and attributes that allow
fine-grained control over the lifecycle of a request. Every implementing class requires a `#[Handler($priority)]`
attribute to determine the order in which the handlers are called.

| interfaces                                    | description                                             |
|-----------------------------------------------|---------------------------------------------------------|
| `HandleRequestInterface&PreProcessInterface`  | Transform a request before it is sent to a processor    |
| `HandleRequestInterface&ProcessInterface`     | Process a request and return a response                 |
| `HandleRequestInterface&PostProcessInterface` | Transform a response before it is sent to the client    |
| `RequestDeterminatorInterface`                | Determine if the request is an API/Htmx/Browser request |
| `ExceptionHandlerInterface`                   | Handle unhandled exceptions                             |

## Children

There is a special `Children` component that will inject the children of the current component into the component. This
can allow rich components, such as modals and dropdowns. Currently, the only way to pass complex objects to these
children (such as callbacks) is to implement the `DataProvider` interface. (This is how the `Route` component works.)

Example:

```php
#[Component('example')]
readonly class ExampleComponent {
  public function render() {
    return <<<HTML
      <div class='i-have-children'>
        <children></children>
      </div>
    HTML;
  }
}
```

## Container components

When rendering, the component name and information are completely stripped from the HTML, thus the following

```html

<swytch:route path="/">
    <div>hello world</div>
</swytch:route>
```

produces the following output:

```html

<div>hello world</div>
```

When navigating to `/`.
However, there are times when you don't want this to happen, and in this case you probably want a container component.
This is how forms work internally.
There is a `Form` component that renders the children and adds additional hidden inputs for CSRF protection.

# Best Practices

As you may have noticed, the Swytch Framework does not allow you to pass complex objects to child components. This is a
deliberate design decision to keep components pure. If a component has too much logic that depends on the hierarchy of
the HTML, then it becomes difficult to test, maintain, and provide a good developer experience. Instead, we recommend
using stateless components and query caching to improve performance.

For example, if you have a `UserProfile` component that renders a user's profile, you should not pass the user's model
directly to the component, instead, you should pass the user's ID and perform the query in the component's constructor
or in the `render()` method. This allows you to easily rerender the component outside of the tree and easily test the
component.

# Unit Testing

Unit testing components is a first-class citizen in the Swytch Framework. 
We suggest using `pestphp/pest` along with `spatie/pest-plugin-snapshots` to use snapshot testing.
The template repository has some excellent examples of how to set this up using the Streaming Compiler.
