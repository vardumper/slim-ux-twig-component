<?php

declare(strict_types=1);

use Slim\Views\Twig;
use Symfony\UX\TwigComponent\Twig\ComponentExtension;
use Symfony\UX\TwigComponent\Twig\ComponentRuntime;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;
use Vardumper\SlimTwigComponent\SlimTwigComponent;

it('registers component support on the twig environment', function (): void {
    $baseDir = sys_get_temp_dir() . '/slim-ux-twig-component-' . uniqid('', true);
    $templatesDir = $baseDir . '/templates';
    $extraNamespaceDir = $baseDir . '/extra';
    $componentDir = $baseDir . '/components';

    mkdir($templatesDir, 0777, true);
    mkdir($extraNamespaceDir . '/components', 0777, true);
    mkdir($componentDir, 0777, true);

    file_put_contents($extraNamespaceDir . '/components/Badge.html.twig', 'Hello {{ message }}');

    $twig = Twig::create($templatesDir);

    SlimTwigComponent::register(
        twig: $twig,
        namespacePaths: [
            'Extra' => $extraNamespaceDir,
        ],
        componentPaths: [$componentDir],
    );

    $environment = $twig->getEnvironment();
    $loader = $environment->getLoader();

    expect($environment->hasExtension(ComponentExtension::class))->toBeTrue();
    expect($environment->getFunction('component'))->not->toBeNull();
    expect($loader)->toBeInstanceOf(FilesystemLoader::class);

    assert($loader instanceof FilesystemLoader);

    expect($loader->getPaths('Extra'))->toContain($extraNamespaceDir);

    $runtime = $environment->getRuntime(ComponentRuntime::class);
    expect($runtime->render('Badge', [
        'message' => 'World',
    ]))->toBe('Hello World');
});

it('registers without filesystem loader and still exposes runtime', function (): void {
    $twig = new Twig(new ArrayLoader([]));

    SlimTwigComponent::register($twig);

    $environment = $twig->getEnvironment();

    expect($environment->hasExtension(ComponentExtension::class))->toBeTrue();
    expect($environment->getRuntime(ComponentRuntime::class))->toBeObject();
});
