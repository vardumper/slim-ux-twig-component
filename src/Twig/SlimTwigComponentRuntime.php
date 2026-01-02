<?php
declare(strict_types=1);

namespace Vardumper\SlimTwigComponent\Twig;

use Symfony\UX\TwigComponent\Event\PreRenderEvent;
use Symfony\UX\TwigComponent\ComponentMetadata;
use Symfony\UX\TwigComponent\MountedComponent;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\ComputedPropertiesProxy;
use Symfony\UX\TwigComponent\Attribute\PreMount as PreMountAttr;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Twig\Environment;
use Twig\Runtime\EscaperRuntime;

final class SlimTwigComponentRuntime
{
    private Environment $twig;
    /** @var array<string,array{class:string,template:string,pre_mount:list<string>}> */
    private array $components = [];
    /** @var array<string,string> */
    private array $namespacePaths = [];

    /**
     * @param string[] $componentPaths Optional list of paths to scan for class-based components
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
    public function preRender(string $name, array $props): ?string
    {
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
        $lower = $name;
        if (!isset($this->components[$name])) {
            foreach ($this->components as $k => $v) {
                if (strcasecmp($k, $name) === 0) {
                    $lower = $k;
                    break;
                }
            }
        }
        if (!isset($this->components[$lower])) {
            throw new \InvalidArgumentException(sprintf('Unknown component "%s".', $name));
        } /* try case-insensitive match */

        $cfg = $this->components[$lower];
        $class = $cfg['class'];
        $template = $cfg['template'];
        $preMountMethods = $cfg['pre_mount'] ?? [];

        if ($class === \Symfony\UX\TwigComponent\AnonymousComponent::class) { /* instantiate component (handle anonymous components) */
            $component = new $class(); /* anonymous components receive their props via mount() */
            $component->mount($props);
            $originalProps = $props;
            $props = []; /* after mounting, there are no "remaining" props to treat as attributes */
        } else {
            $component = new $class();

            foreach ($preMountMethods as $method) { /* call PreMount methods (they may transform the props) */
                $result = $component->$method($props);
                if (null !== $result) {
                    $props = $result;
                }
            }

            $originalProps = $props;

            foreach ($props as $k => $v) { /* mount public properties */
                if (property_exists($component, $k)) {
                    $component->$k = $v;
                    unset($props[$k]);
                }
            }
        }

        $escaper = $this->twig->getRuntime(EscaperRuntime::class); /* attributes are the remaining props ("attributes" var is used in templates) */
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

        $classProps = get_object_vars($component); /* variables visible in the component template: context + input props + class public props + attributes */
        $variables = array_merge($context, $originalProps, $classProps, [$meta->getAttributesVar() => $mounted->getAttributes()]);

        $event = new PreRenderEvent($mounted, $meta, $variables);

        $event->setVariables(array_merge($event->getVariables(), [ /* add convenience variables used by the official renderer */
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

    public array $componentPaths = [];

    private function discoverComponents(): void
    {
        foreach ($this->componentPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($files as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $content = file_get_contents($file->getPathname());
                if (!preg_match('/namespace\\s+([^;]+);/m', $content, $ns)) {
                    continue;
                }
                if (!preg_match('/class\\s+([A-Za-z0-9_]+)/m', $content, $cl)) {
                    continue;
                }
                $fqcn = trim($ns[1]) . '\\' . trim($cl[1]);
                if (!class_exists($fqcn)) {
                    continue;
                }
                try {
                    $ref = new \ReflectionClass($fqcn);
                } catch (\ReflectionException $e) {
                    continue;
                }
                $attrs = $ref->getAttributes(AsTwigComponent::class, \ReflectionAttribute::IS_INSTANCEOF);
                $name = $ref->getShortName();
                $template = null;
                $preMount = [];
                if ($attrs) {
                    $inst = $attrs[0]->newInstance();
                    $cfg = $inst->serviceConfig();
                    $name = $cfg['key'] ?? $name;
                    $template = $cfg['template'] ?? $template;
                }
                foreach ($ref->getMethods() as $m) {
                    if ($m->getAttributes(PreMountAttr::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                        $preMount[] = $m->getName();
                    }
                }
                if (null === $template) {
                    $template = sprintf('@HtmlTwigComponent/%s.html.twig', strtolower($name));
                }
                if ($attrs) {
                    $this->components[$name] = [
                        'class' => $fqcn,
                        'template' => $template,
                        'pre_mount' => $preMount,
                    ];
                }
            }
        }

        $loader = $this->twig->getLoader(); /* 2. Discover anonymous Twig components in namespacePaths (Twig loader namespaces) */
        if ($loader instanceof \Twig\Loader\FilesystemLoader) {
            $namespaces = $loader->getNamespaces();
            foreach ($namespaces as $ns) {
                $paths = $loader->getPaths($ns);
                foreach ($paths as $path) {
                    $componentsDir = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . 'components';
                    if (!is_dir($componentsDir)) {
                        continue;
                    }
                    $it = new \DirectoryIterator($componentsDir);
                    foreach ($it as $file) {
                        if (!$file->isFile()) {
                            continue;
                        }
                        $filename = $file->getBasename();
                        if (str_ends_with($filename, '.html.twig')) {
                            $basename = substr($filename, 0, -strlen('.html.twig'));
                        } else {
                            $basename = pathinfo($filename, PATHINFO_FILENAME);
                        }
                        $name = $basename;
                        if ($ns === \Twig\Loader\FilesystemLoader::MAIN_NAMESPACE) { /* build the twig template reference, using namespaces if not main */
                            $template = 'components/' . $file->getBasename();
                        } else {
                            $template = '@' . $ns . '/components/' . $file->getBasename();
                        }
                        if (isset($this->components[$name]) && empty($this->components[$name]['anonymous'])) { /* prefer class-based components when both exist; otherwise register anonymous */
                            continue;
                        }
                        $this->components[$name] = [
                            'class' => \Symfony\UX\TwigComponent\AnonymousComponent::class,
                            'template' => $template,
                            'pre_mount' => [],
                            'anonymous' => true,
                        ];
                    }
                }
            }
        }
    }
}
