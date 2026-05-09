<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Vardumper\SlimTwigComponent\Twig\SlimTwigComponentRuntime;

it('renders anonymous components discovered from the main namespace', function (): void {
    $baseDir = sys_get_temp_dir() . '/slim-ux-runtime-' . uniqid('', true);
    $templatesDir = $baseDir . '/templates';

    mkdir($templatesDir . '/components', 0777, true);
    file_put_contents(
        $templatesDir . '/components/Alert.html.twig',
        '{{ message }}|{{ attributes }}'
    );

    $twig = new Environment(new FilesystemLoader($templatesDir));
    $runtime = new SlimTwigComponentRuntime($twig);

    $output = $runtime->render('alert', [
        'message' => 'Ready',
        'class' => 'notice',
    ]);

    expect($output)->toBe('Ready|');
});

it('throws for unknown components', function (): void {
    $baseDir = sys_get_temp_dir() . '/slim-ux-runtime-missing-' . uniqid('', true);
    $templatesDir = $baseDir . '/templates';

    mkdir($templatesDir, 0777, true);

    $twig = new Environment(new FilesystemLoader($templatesDir));
    $runtime = new SlimTwigComponentRuntime($twig);

    expect(fn () => $runtime->render('does-not-exist'))
        ->toThrow(InvalidArgumentException::class, 'Unknown component "does-not-exist".');
});
