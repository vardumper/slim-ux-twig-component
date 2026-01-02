# Slim UX Twig Component

A small integration to make building and using Twig "components" easy in Slim apps. It provides a simple registration helper so you can map a PHP component namespace to a filesystem location and then render components in Twig with a friendly tag.

---

## ✅ What problem this solves

Many apps need a lightweight component system for Twig templates (re-usable UI pieces with small PHP classes for logic). This package provides a tiny integration layer so you can register component class namespaces and use a simple `{% component %}` tag in your Twig templates without needing a full framework integration.

---

## ⚙️ Requirements

- PHP 8.1+ (or the version your app uses)
- Slim 4
- `slim/twig-view` (Twig integration for Slim)

---

## 📦 Installation


```bash
composer require vardumper/slim-ux-twig-component
```

---

## 🚀 Minimal Slim example

Register Twig and the component integration in your app bootstrap:

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
```

This will look for a component class like `App\Twig\Component\Alert` and render the corresponding template (which either exists in your default namespace eg: `templates/components/Alert.html.twig`. Alternatively provide more namespacePaths, for the Runtime to look in. eg: `@AdditionalNamespace/components/Alert.html.twig`.

---

## 💡 Recommended folder convention

```
src/
└── Twig/
    └── Component/
        └── Alert.php

templates/
└── components/
    └── Alert.html.twig
```

### Example component class (minimal)

```php
namespace App\Twig\Component;

#[AsTwigComponent('Alert', template: 'Alert.html.twig')]
class Alert
{
    private ?string $type;
    private ?string $content;

    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('type');
        $resolver->setAllowedTypes('type', ['string']);
        $resolver->setDefined('content');
        $resolver->setAllowedTypes('content', ['string']);
        return $resolver->resolve($data);
    }
}
```

### Example template (`templates/components/Alert.html.twig`)

```twig
<div class="alert alert-{{ type|default('info') }}">
  {{ content|default('') }}
</div>
```

---

## ⚠️ Limitations

- No Symfony Dependency Injection: this package does not integrate with Symfony DI or use autoconfig. You must provide the mapping from namespace to path yourself.
- No automatic autoconfig/autodiscovery: components are found based on the namespace -> path mapping you register.
- Expect to register PSR-4 autoloading for your component classes (via your app's composer.json or equivalent).

---