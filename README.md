# Slim UX Twig Component

A small integration to make building and using **[Twig Components](https://symfony.com/bundles/ux-twig-component/current/index.html)** easy in Slim Framework. It provides a custom Runtime and allows configuration of component paths as well as passing in more Twig namespaces/paths. As a caveat, installing this package, does pull in a number of Symfony packages.
It also adds `vardumper/extended-htmldocument` which adds improved HTML5 support to PHP - used for HTML5 attribute validation via Immutable Attribute Enums when using the Twig Components included in this Slim package (for example `<twig:A href="#" role="button">`). 
It also adds `vardumper/html5-twig-component-bundle` which holds these pre-made Twig Components for all HTML5 elements.   

## Features

 - ✅ Works with Slim 4+
 - ✅ Pre-made, fully type-safe Twig Components for all HTML5 elements included
 - ✅ Supports class-based as well as anonymous Twig Components
 - ✅ Supports `<twig:Alert type="success" />` syntax
 - ✅ Supports `{{ component('Alert', { type: 'success'}) }}` syntax
 - ✅ Supports `{{ component 'Alert' with { type: 'success'} }}` syntax


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

Besides from strings, you can also pass objects and arrays to Twig Components. Learn more [here](https://symfony.com/bundles/ux-twig-component/current/index.html#component-html-syntax).
```twig
<twig:Alert :object='{ "key": "value" }' />
```