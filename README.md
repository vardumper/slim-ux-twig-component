# Slim UX Twig Component

A small integration to make building and using Twig "components" easy in Slim apps. It provides a simple registration helper so you can map a PHP component namespace to a filesystem location and then render components in Twig with a friendly tag.

## Installation
```bash
composer require vardumper/slim-ux-twig-component
```

## Slim Setup

After you register Twig in your app bootstrap, call the register method of `SlimTwigComponent`:

```php
use Slim\Views\Twig;
use Vardumper\SlimTwigComponent\SlimTwigComponent;

$twig = Twig::create(__DIR__ . '/../templates', [
    'cache' =>  __DIR__ . '/../var/cache/twig',
]);

SlimTwigComponent::register(
    twig: $twig,
    namespacePaths: [
        'AdditionalNamespace' => __DIR__ . '../path/to/twig-component',
    ],
    componentPaths: [
        'App\\Twig\\Component\\' => __DIR__ . '/../src/Twig/Component',
    ],
);
```

Now inside your Twig templates you can render components:

```twig
{% component 'Alert' with { type: 'success' } %}
{% component('Alert', { type: 'success' }) %}
<twig:Alert type="success" />
<twig:Alert :object='{ "key": "value" }' />
```

This will look for a component class like `App\Twig\Component\Alert` and render the corresponding template (which either exists in your default namespace eg: `templates/components/Alert.html.twig`.
Alternatively provide more namespacePaths, for the Runtime to look in. eg: `@AdditionalNamespace/components/Alert.html.twig`.
