<?php
declare(strict_types=1);

namespace Vardumper\SlimTwigComponent;

use Slim\Views\Twig;
use Symfony\UX\TwigComponent\Twig\ComponentExtension;
use Symfony\UX\TwigComponent\Twig\ComponentLexer;
use Symfony\UX\TwigComponent\Twig\ComponentRuntime;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Vardumper\SlimTwigComponent\Twig\SlimTwigComponentRuntime;

final class SlimTwigComponent
{
    private const DEFAULT_NAMESPACES = [
        'HtmlTwigComponent' => __DIR__ . '/../../../vendor/vardumper/html5-twig-component-bundle/src/Resources',
    ];

    private const DEFAULT_COMPONENT_PATHS = [
        __DIR__ . '/../../../vendor/vardumper/html5-twig-component-bundle/src/Twig',
    ];

    /**
     * Register Twig Components with the given Twig environment.
     *
     * provide optional $namespacePaths to lookup twig files or anonymous components.
     * provide optional $componentPaths to lookup class-based components.
     */
    public static function register(Twig $twig, array $namespacePaths = [], array $componentPaths = []): void
    {
        $env = $twig->getEnvironment();

        // Register default template namespaces and append any additional namespaces passed in
        $loader = $env->getLoader();
        if ($loader instanceof \Twig\Loader\FilesystemLoader) {
            $allNamespacePaths = array_merge(self::DEFAULT_NAMESPACES, $namespacePaths);
            foreach ($allNamespacePaths as $ns => $path) {
                if (is_dir($path)) {
                    $loader->addPath($path, $ns);
                }
            }
        }

        // Register UX extension
        $env->addExtension(new ComponentExtension());

        // Enable <twig:Component />
        $env->setLexer(new ComponentLexer($env));

        // Runtime loader
        $componentPaths = array_merge(self::DEFAULT_COMPONENT_PATHS, $componentPaths);
        $env->addRuntimeLoader(
            new FactoryRuntimeLoader([
                ComponentRuntime::class => fn () =>
                    new SlimTwigComponentRuntime($env, $componentPaths, $allNamespacePaths),
            ])
        );
    }
}