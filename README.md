<table align="center" style="border-collapse:collapse !important; border:none !important;">
  <tr style="border:0px none; border-top: 0px none !important;">
    <td align="center" valign="middle" style="padding:0 1rem; border:none !important;">
      <a href="https://www.w3.org/TR/2011/WD-html5-20110405/" target="_blank">
        <img src="https://vardumper.github.io/extended-htmldocument/html5_logo-with-wordmark.svg" style="display:block; height:90px; width:auto; max-width:300px;" alt="HTML5 Logo" />
      </a>
    </td>
    <td align="center" valign="middle" style="padding:0 1rem; border:none !important;">
      <a href="https://www.slimframework.com/" target="_blank">
        <img src="https://vardumper.github.io/extended-htmldocument/slim.png" style="display:block; height:100px; width:auto; max-width:220px;" alt="Slim Framework Logo" />
      </a>
    </td>
  </tr>
</table>
<h1 align="center">Slim UX Twig Component</h1>

<p dir="auto" align="center"><a href="https://packagist.org/packages/vardumper/slim-ux-twig-component" rel="nofollow"><img src="https://camo.githubusercontent.com/1cb1d589a8e7dbd3b472c9aba54cb8249143d9ddb181d27b6b4d76b47e3dd752/68747470733a2f2f706f7365722e707567782e6f72672f76617264756d7065722f736c696d2d75782d747769672d636f6d706f6e656e742f762f737461626c65" alt="Latest Stable Version" data-canonical-src="https://poser.pugx.org/vardumper/slim-ux-twig-component/v/stable" style="max-width: 100%;"></a>
<a href="https://packagist.org/packages/vardumper/slim-ux-twig-component" rel="nofollow"><img src="https://camo.githubusercontent.com/e1d931cd82d815f27573be5ab2809bbd081f04c80d63cc99c7b6c0ea1f4981d2/68747470733a2f2f706f7365722e707567782e6f72672f76617264756d7065722f736c696d2d75782d747769672d636f6d706f6e656e742f646f776e6c6f616473" alt="Total Downloads" data-canonical-src="https://poser.pugx.org/vardumper/slim-ux-twig-component/downloads" style="max-width: 100%;"></a>
<a href="https://dtrack.erikpoehler.us/projects/4d0cd6c8-6c59-4d4a-8096-b503045a06b9" rel="nofollow"><img src="https://camo.githubusercontent.com/8c55b58f7b2bc4b69e4274eda7d2c902817c3d573e0236f504101b52a9c83f25/68747470733a2f2f64747261636b2e6572696b706f65686c65722e75732f6170692f76312f62616467652f76756c6e732f70726f6a6563742f34643063643663382d366335392d346434612d383039362d6235303330343561303662393f6170694b65793d6f64745f4a354f4b7a394a6357704b416e717a383077687854767741336f516a47424779" alt="Vulnerabilities for slim-ux-twig-component" data-canonical-src="https://dtrack.erikpoehler.us/api/v1/badge/vulns/project/4d0cd6c8-6c59-4d4a-8096-b503045a06b9?apiKey=odt_J5OKz9JcWpKAnqz80whxTvwA3oQjGBGy" style="max-width: 100%;"></a></p>

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
