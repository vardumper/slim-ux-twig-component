[![Latest Stable Version](https://poser.pugx.org/vardumper/slim-ux-twig-component/v/stable)](https://packagist.org/packages/vardumper/slim-ux-twig-component)
[![Total Downloads](https://poser.pugx.org/vardumper/slim-ux-twig-component/downloads)](https://packagist.org/packages/vardumper/slim-ux-twig-component) 
[![Vulnerabilities for slim-ux-twig-component](https://dtrack.erikpoehler.us/api/v1/badge/vulns/project/4d0cd6c8-6c59-4d4a-8096-b503045a06b9?apiKey=odt_J5OKz9JcWpKAnqz80whxTvwA3oQjGBGy)](https://dtrack.erikpoehler.us/projects/4d0cd6c8-6c59-4d4a-8096-b503045a06b9)

# Slim UX Twig Component

A small integration to make building and using **[Twig Components](https://symfony.com/bundles/ux-twig-component/current/index.html)** easy in Slim Framework. It provides a custom Runtime and allows configuration of component paths as well as passing in more Twig namespaces/paths. As a caveat, installing this package, does pull in a number of Symfony packages.
Makes use of [`vardumper/extended-htmldocument`](https://github.com/vardumper/extended-htmldocument) which adds improved HTML5 support to PHP - used for HTML5 attribute validation via Immutable Attribute Enums when using the pre-built Twig Components that are included in this package (for example `<twig:A href="#" role="button">Inline Button</twig:A>`). 
Also uses [`vardumper/html5-twig-component-bundle`](https://github.com/vardumper/html5-twig-component-bundle) which holds these pre-built Twig Components for all HTML5 elements.   

## Features

 - Works with Slim 4+ with and without DI Container
 - Includes pre-made Twig Components for all HTML5 elements that are fully typesafe and support Alpine.js, ARIA, WCAG and HTML Living Standards.
 - Supports class-based as well as anonymous Twig Components
 - Supports `<twig:Alert type="success" />`, `{{ component('Alert', { type: 'success'}) }}` and
 `{{ component 'Alert' with { type: 'success'} }}` syntaxes


## Requirements
 - PHP 8.4+
 - `slim/twig-view` for Twig Rendering

## Installation
```bash
composer require vardumper/slim-ux-twig-component
```

## Slim Setup

After you register Twig (`slimphp/twig-view`) in your application bootstrap, call the register method of `SlimTwigComponent` like so:

```php
use Slim\Views\Twig;
use Vardumper\SlimTwigComponent\SlimTwigComponent;

$twig = Twig::create(__DIR__ . '/../templates', [
    'cache' =>  __DIR__ . '/../var/cache/twig',
]);

SlimTwigComponent::register(
    twig: $twig,
    // optional:
    namespacePaths: [
        'AdditionalNamespace' => __DIR__ . '../path/to/twig-component',
    ],
    // optional:
    componentPaths: [
        'App\\Twig\\Component\\' => __DIR__ . '/../src/Twig/Component',
    ],
);
```

By default, only Twig's main namepsace (usually a `templates/` or `view/` folder) is registered by the SlimTwigComponent class. Internally, the paths for the pre-made HTML5 Twig Components are registered. 
Use the optional `$namespacePaths` and `$componentPaths` to provide an array of additional folders and namespaces.

You can now add Anonymous components inside `templates/components`. For example: add an `Alert.html.twig` file:
```twig
<twig:Span class="alert alert-{{ type|default('success') }}">{{ message }}</twig:Span>
```

Now inside your Twig templates you can render components:

```twig
{% component 'Alert' with { type: 'warning', message: 'Something happened!' } %}
{% component('Alert', { type: 'success', message: 'All good!' }) %}
<twig:Alert type="success" message="Super duper!" />
```

Besides from strings, you can also pass objects and arrays to Twig Components. Learn more about Twig Components in the [Symfony Documentation](https://symfony.com/bundles/ux-twig-component/current/index.html#component-html-syntax).
```twig
<twig:Alert :object='{ "key": "value" }' />
```
