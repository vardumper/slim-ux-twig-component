<?php
declare(strict_types=1);

namespace Vardumper\SlimTwigComponent\Twig;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount as PreMountAttr;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\ComponentMetadata;
use Symfony\UX\TwigComponent\ComputedPropertiesProxy;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;
use Symfony\UX\TwigComponent\MountedComponent;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;

final class SlimTwigComponentRuntime
{
    /**
     * @var list<string>
     */
    public array $componentPaths = [];

    private Environment $twig;

    /**
     * @var array<string,array{class:string,template:string,pre_mount:list<string>,anonymous?:bool}>
     */
    private array $components = [];

    /**
     * @var array<string,string>
     */
    private array $namespacePaths = [];

    /**
     * @param list<string> $componentPaths Optional list of paths to scan for class-based components
     * @param array<string,string> $namespacePaths
     */
    public function __construct(Environment $twig, array $componentPaths = [], array $namespacePaths = [])
    {
        $this->twig = $twig;
        $this->componentPaths = $componentPaths;
        $this->namespacePaths = $namespacePaths;
        $this->discoverComponents();
    }

    /**
     * Called by compiled templates to short-circuit render if needed. We don't dispatch events here, so always return null.
     *
     * @param array<string,mixed> $props
     */
    public function preRender(string $name, array $props): null
    {
        unset($name, $props);

        return null;
    }

    /**
     * Render a component by name (used by the `component()` Twig function).
     *
     * @param array<string,mixed> $props
     */
    public function render(string $name, array $props = []): string
    {
        $event = $this->startEmbedComponent($name, $props, [], '', 0);

        return $this->twig->render($event->getTemplate(), $event->getVariables());
    }

    /**
     * Create pre-render information for an embedded component (used by {% component %} tag).
     *
     * @param array<string,mixed> $props
     * @param array<string,mixed> $context
     */
    public function startEmbedComponent(string $name, array $props, array $context, string $hostTemplateName, int $index): PreRenderEvent
    {
        unset($hostTemplateName, $index);

        $cfg = $this->resolveComponentConfig($name);
        [$component, $originalProps, $props] = $this->createMountedComponent($cfg, $props);
        $class = $cfg['class'];
        $template = $cfg['template'];
        $preMountMethods = $cfg['pre_mount'];

        $escaper = $this->twig->getRuntime(EscaperRuntime::class);
        $attributes = new ComponentAttributes($props, $escaper);
        $mounted = new MountedComponent($name, $component, $attributes, $originalProps, []);

        $meta = new ComponentMetadata([
            'key' => $name,
            'template' => $template,
            'class' => $class,
            'pre_mount' => $preMountMethods,
            'expose_public_props' => true,
            'attributes_var' => 'attributes',
        ]);

        $classProps = get_object_vars($component);
        $variables = array_merge($context, $originalProps, $classProps, [
            $meta->getAttributesVar() => $mounted->getAttributes(),
        ]);

        $event = new PreRenderEvent($mounted, $meta, $variables);
        $event->setVariables(array_merge($event->getVariables(), [
            'this' => $component,
            'computed' => new ComputedPropertiesProxy($component),
            'outerScope' => $context,
            '__props' => $classProps,
            '__context' => array_diff_key($context, $originalProps),
        ]));

        return $event;
    }

    public function finishEmbedComponent(): void
    {
        /* no-op for our minimal implementation */
    }

    private function discoverComponents(): void
    {
        $this->discoverClassBasedComponents();
        $this->discoverAnonymousComponents();
    }

    private function discoverClassBasedComponents(): void
    {
        foreach ($this->componentPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $this->discoverClassBasedComponentsInPath($path);
        }
    }

    private function discoverClassBasedComponentsInPath(string $path): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $this->discoverClassBasedComponentFromFile($file->getPathname());
        }
    }

    private function discoverClassBasedComponentFromFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        if (preg_match('/namespace\s+([^;]+);/m', $content, $namespaceMatch)
            && preg_match('/class\s+(\w+)/m', $content, $classMatch)
        ) {
            $fqcn = trim($namespaceMatch[1]) . '\\' . trim($classMatch[1]);
            if (class_exists($fqcn)) {
                $this->registerClassBasedComponent($fqcn);
            }
        }
    }

    private function registerClassBasedComponent(string $fqcn): void
    {
        $ref = new \ReflectionClass($fqcn);
        $attrs = $ref->getAttributes(AsTwigComponent::class, \ReflectionAttribute::IS_INSTANCEOF);
        if (!$attrs) {
            return;
        }

        $name = $ref->getShortName();
        $template = null;

        $inst = $attrs[0]->newInstance();
        $cfg = $inst->serviceConfig();
        $name = $cfg['key'] ?? $name;
        $template = $cfg['template'] ?? $template;

        $preMount = $this->collectPreMountMethods($ref);

        if ($template === null) {
            $template = sprintf('@HtmlTwigComponent/%s.html.twig', strtolower($name));
        }

        $this->components[$name] = [
            'class' => $fqcn,
            'template' => $template,
            'pre_mount' => $preMount,
        ];
    }

    /**
     * @return array{class:string,template:string,pre_mount:list<string>,anonymous?:bool}
     */
    private function resolveComponentConfig(string $name): array
    {
        if (isset($this->components[$name])) {
            return $this->components[$name];
        }

        foreach ($this->components as $componentName => $componentConfig) {
            if (strcasecmp($componentName, $name) === 0) {
                return $componentConfig;
            }
        }

        throw new \InvalidArgumentException(sprintf('Unknown component "%s".', $name));
    }

    /**
     * @param array{class:string,template:string,pre_mount:list<string>,anonymous?:bool} $cfg
     * @param array<string,mixed> $props
     * @return array{0:object,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function createMountedComponent(array $cfg, array $props): array
    {
        $class = $cfg['class'];

        if ($class === \Symfony\UX\TwigComponent\AnonymousComponent::class) {
            return $this->createAnonymousComponent($class, $props);
        }

        return $this->createClassBasedComponent($class, $cfg['pre_mount'], $props);
    }

    /**
     * @param class-string $class
     * @param array<string,mixed> $props
     * @return array{0:object,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function createAnonymousComponent(string $class, array $props): array
    {
        $component = new $class();
        $component->mount($props);

        return [$component, $props, []];
    }

    /**
     * @param class-string $class
     * @param list<string> $preMountMethods
     * @param array<string,mixed> $props
     * @return array{0:object,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function createClassBasedComponent(string $class, array $preMountMethods, array $props): array
    {
        $component = new $class();

        foreach ($preMountMethods as $method) {
            $result = $component->{$method}($props);
            if ($result !== null) {
                $props = $result;
            }
        }

        $originalProps = $props;

        foreach ($props as $key => $value) {
            if (property_exists($component, $key)) {
                $component->{$key} = $value;
                unset($props[$key]);
            }
        }

        return [$component, $originalProps, $props];
    }

    /**
     * @param \ReflectionClass<object> $ref
     * @return list<string>
     */
    private function collectPreMountMethods(\ReflectionClass $ref): array
    {
        $preMount = [];

        foreach ($ref->getMethods() as $method) {
            if ($method->getAttributes(PreMountAttr::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                $preMount[] = $method->getName();
            }
        }

        return $preMount;
    }

    private function discoverAnonymousComponents(): void
    {
        $loader = $this->twig->getLoader();
        if (!$loader instanceof FilesystemLoader) {
            return;
        }

        foreach ($loader->getNamespaces() as $namespace) {
            $this->discoverAnonymousComponentsInNamespace($loader, $namespace);
        }
    }

    private function discoverAnonymousComponentsInNamespace(FilesystemLoader $loader, string $namespace): void
    {
        foreach ($loader->getPaths($namespace) as $path) {
            $componentsDir = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . 'components';
            if (!is_dir($componentsDir)) {
                continue;
            }

            $this->discoverAnonymousComponentsInDirectory($componentsDir, $namespace);
        }
    }

    private function discoverAnonymousComponentsInDirectory(string $componentsDir, string $namespace): void
    {
        $it = new \DirectoryIterator($componentsDir);

        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $this->registerAnonymousComponent($namespace, $file->getBasename());
        }
    }

    private function registerAnonymousComponent(string $namespace, string $basename): void
    {
        if (str_ends_with($basename, '.html.twig')) {
            $name = substr($basename, 0, -strlen('.html.twig'));
        } else {
            $name = pathinfo($basename, PATHINFO_FILENAME);
        }

        if ($namespace === FilesystemLoader::MAIN_NAMESPACE) {
            $template = 'components/' . $basename;
        } else {
            $template = '@' . $namespace . '/components/' . $basename;
        }

        if (isset($this->components[$name]) && !($this->components[$name]['anonymous'] ?? false)) {
            return;
        }

        $this->components[$name] = [
            'class' => \Symfony\UX\TwigComponent\AnonymousComponent::class,
            'template' => $template,
            'pre_mount' => [],
            'anonymous' => true,
        ];
    }
}
