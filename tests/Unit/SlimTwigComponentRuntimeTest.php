<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;
use Vardumper\SlimTwigComponent\Twig\SlimTwigComponentRuntime;

function slimUxWrite(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($path, $content);
}

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

it('supports class-based components with pre-mount and attributes', function (): void {
    $baseDir = sys_get_temp_dir() . '/slim-ux-runtime-class-' . uniqid('', true);
    $templatesDir = $baseDir . '/templates';
    $componentDir = $baseDir . '/components-src';
    $namespace = 'Tests\\Generated\\C' . str_replace('.', '', uniqid('', true));

    slimUxWrite($templatesDir . '/components/card.html.twig', '{{ title }}|{{ attributes }}|{{ this.title }}|{{ outerScope.ctx }}');

    $classCode = <<<'PHP'
<?php
declare(strict_types=1);

namespace __NS__;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsTwigComponent('Card', 'components/card.html.twig')]
final class CardComponent
{
    public string $title = '';

    #[PreMount]
    public function normalize(array $props): array
    {
        $props['title'] = strtoupper((string) (
            $props['title'] ?? ''
        ));

        return $props;
    }

    #[PreMount]
    public function noop(array $props): ?array
    {
        return null;
    }
}
PHP;
    $classCode = str_replace('__NS__', $namespace, $classCode);

    $classPath = $componentDir . '/CardComponent.php';
    slimUxWrite($classPath, $classCode);
    require_once $classPath;

    $twig = new Environment(new FilesystemLoader($templatesDir));
    $runtime = new SlimTwigComponentRuntime($twig, [$componentDir]);

    $event = $runtime->startEmbedComponent('Card', [
        'title' => 'ready',
        'class' => 'notice',
        'unused' => 'kept-as-attr',
    ], [
        'ctx' => 'outer',
    ], 'host.twig', 0);

    expect($event->getTemplate())->toBe('components/card.html.twig');
    expect($event->getVariables()['title'])->toBe('READY');
    expect((string)$event->getVariables()['attributes'])->toContain('class="notice"');
    expect((string)$event->getVariables()['attributes'])->toContain('unused="kept-as-attr"');
    expect($event->getVariables()['outerScope'])->toBe([
        'ctx' => 'outer',
    ]);

    expect($runtime->render('Card', [
        'title' => 'ready',
        'class' => 'notice',
        'unused' => 'kept-as-attr',
    ]))->toContain('READY|');
});

it('uses fallback template naming for class components without explicit template', function (): void {
    $baseDir = sys_get_temp_dir() . '/slim-ux-runtime-fallback-' . uniqid('', true);
    $componentDir = $baseDir . '/components-src';
    $namespace = 'Tests\\Generated\\F' . str_replace('.', '', uniqid('', true));

    $classCode = <<<'PHP'
<?php
declare(strict_types=1);

namespace __NS__;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Badge')]
final class BadgeComponent
{
}
PHP;
    $classCode = str_replace('__NS__', $namespace, $classCode);

    $classPath = $componentDir . '/BadgeComponent.php';
    slimUxWrite($classPath, $classCode);
    require_once $classPath;

    $twig = new Environment(new ArrayLoader([]));
    $runtime = new SlimTwigComponentRuntime($twig, [$componentDir]);

    $event = $runtime->startEmbedComponent('Badge', [], [], 'host.twig', 0);
    expect($event->getTemplate())->toBe('@HtmlTwigComponent/badge.html.twig');
});

it('discovers anonymous components in non-main namespaces', function (): void {
    $baseDir = sys_get_temp_dir() . '/slim-ux-runtime-ns-' . uniqid('', true);
    $templatesDir = $baseDir . '/templates';
    $extraDir = $baseDir . '/extra';

    mkdir($templatesDir, 0777, true);
    slimUxWrite($extraDir . '/components/Notice.twig', 'NS={{ message }}');

    $loader = new FilesystemLoader($templatesDir);
    $loader->addPath($extraDir, 'Extra');

    $runtime = new SlimTwigComponentRuntime(new Environment($loader));

    expect($runtime->render('Notice', [
        'message' => 'ok',
    ]))->toBe('NS=ok');
});

it('prefers class-based component over anonymous component with same name', function (): void {
    $baseDir = sys_get_temp_dir() . '/slim-ux-runtime-prefer-class-' . uniqid('', true);
    $templatesDir = $baseDir . '/templates';
    $componentDir = $baseDir . '/components-src';
    $namespace = 'Tests\\Generated\\P' . str_replace('.', '', uniqid('', true));

    slimUxWrite($templatesDir . '/components/Alert.html.twig', 'anonymous');
    slimUxWrite($templatesDir . '/components/class-alert.html.twig', 'class-based');

    $classCode = <<<'PHP'
<?php
declare(strict_types=1);

namespace __NS__;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Alert', 'components/class-alert.html.twig')]
final class AlertComponent
{
}
PHP;
    $classCode = str_replace('__NS__', $namespace, $classCode);

    $classPath = $componentDir . '/AlertComponent.php';
    slimUxWrite($classPath, $classCode);
    require_once $classPath;

    $runtime = new SlimTwigComponentRuntime(new Environment(new FilesystemLoader($templatesDir)), [$componentDir]);
    expect($runtime->render('Alert'))->toBe('class-based');
});

it('keeps discovery resilient for non-php and non-autoloadable files', function (): void {
    $baseDir = sys_get_temp_dir() . '/slim-ux-runtime-resilient-' . uniqid('', true);
    $templatesDir = $baseDir . '/templates';
    $componentDir = $baseDir . '/components-src';

    slimUxWrite($templatesDir . '/components/Plain.html.twig', 'plain');
    slimUxWrite($componentDir . '/README.txt', 'not a php file');
    slimUxWrite($componentDir . '/NoNamespace.php', '<?php class FooWithoutNamespace {}');
    slimUxWrite($componentDir . '/NoClass.php', '<?php namespace Tests\\Generated;');
    slimUxWrite($componentDir . '/NotLoaded.php', '<?php namespace Tests\\Generated; class NotLoaded {}');

    $runtime = new SlimTwigComponentRuntime(new Environment(new FilesystemLoader($templatesDir)), [$baseDir . '/missing', $componentDir]);

    expect($runtime->render('Plain'))->toBe('plain');
    expect($runtime->preRender('Plain', []))->toBeNull();

    $runtime->finishEmbedComponent();

    expect(true)->toBeTrue();
});
