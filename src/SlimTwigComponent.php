<?php
declare(strict_types=1);

namespace Vardumper\SlimTwigComponent;

use Slim\Views\Twig;
use Symfony\UX\TwigComponent\Twig\{ComponentExtension, ComponentLexer, ComponentRuntime};
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Vardumper\SlimTwigComponent\Twig\SlimTwigComponentRuntime;

final class SlimTwigComponent
{
    private const DEFAULT_NAMESPACES = [
        'HtmlTwigComponent' => __DIR__ . '/../../html5-twig-component-bundle/src/Resources',
    ];

    private const DEFAULT_COMPONENT_PATHS = [
        __DIR__ . '/../../html5-twig-component-bundle/src/Twig',
    ];

    /**
     * Register Twig Components with the given Twig environment.
     */
    /**
     * @phpstan-param array<string, string> $namespacePaths
     * @phpstan-param list<string> $componentPaths
     */
    public static function register(Twig $twig, array $namespacePaths = [], array $componentPaths = []): void
    {
        $env = $twig->getEnvironment();
        $allNamespacePaths = \array_merge(self::DEFAULT_NAMESPACES, $namespacePaths);

        $loader = $env->getLoader();
        if ($loader instanceof \Twig\Loader\FilesystemLoader) {
            foreach ($allNamespacePaths as $ns => $path) {
                if (\is_dir($path)) {
                    $loader->addPath($path, $ns);
                }
            }
        }

        $env->addExtension(new ComponentExtension());

        $env->setLexer(new ComponentLexer($env));

        $componentPaths = \array_merge(self::DEFAULT_COMPONENT_PATHS, $componentPaths);
        $env->addRuntimeLoader(
            new FactoryRuntimeLoader([
                ComponentRuntime::class => static fn () =>
                    new SlimTwigComponentRuntime($env, $componentPaths),
            ])
        );
    }
}
