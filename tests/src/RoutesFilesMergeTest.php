<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsKernel;

use Derafu\Kernel\Config\Loader\PhpRoutesLoader;
use Derafu\Kernel\Config\Loader\YamlRoutesLoader;
use Derafu\Kernel\Environment;
use Derafu\Kernel\MicroKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * The routes of `routes.yaml` and the ones of `routes.php` are added, none of
 * the two files takes the routes of the other away.
 *
 * Each file is loaded after the one before it (the order of `CONFIG_FILES`), so
 * the routes are in that order, and a name that two files define has the route
 * of the later one.
 */
#[CoversClass(PhpRoutesLoader::class)]
#[CoversClass(YamlRoutesLoader::class)]
#[UsesClass(MicroKernel::class)]
#[UsesClass(Environment::class)]
final class RoutesFilesMergeTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('kernel_routes_');
        mkdir($this->directory);
        MergeEnvironment::$directory = $this->directory;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    /**
     * @param array<string, string> $files
     * @return array<string, array<string, mixed>>
     */
    private function routes(array $files): array
    {
        foreach ($files as $name => $content) {
            file_put_contents($this->directory . '/' . $name, $content);
        }

        $kernel = new MergeKernel(new MergeEnvironment('test', true), false, uniqid('kernel_'));

        return $kernel->container()->getParameter('routes');
    }

    #[Test]
    public function theRoutesOfTheTwoFormatsAreAdded(): void
    {
        $routes = $this->routes([
            'routes.yaml' => "blog:\n  path: /blog\n  handler: 'x'\ndocs:\n  path: /docs\n  handler: 'x'\n",
            'routes.php' => "<?php return ['admin' => ['path' => '/admin', 'handler' => 'x']];\n",
        ]);

        $this->assertSame(['blog', 'docs', 'admin'], array_keys($routes));
    }

    #[Test]
    public function aNameThatTheTwoDefineHasTheRouteOfTheLaterFile(): void
    {
        $routes = $this->routes([
            'routes.yaml' => "blog:\n  path: /blog\n  handler: 'yaml'\ndocs:\n  path: /docs\n  handler: 'x'\n",
            'routes.php' => "<?php return ['blog' => ['path' => '/news', 'handler' => 'php']];\n",
        ]);

        $this->assertSame(['blog', 'docs'], array_keys($routes));
        $this->assertSame('/news', $routes['blog']['path']);
        $this->assertSame('/docs', $routes['docs']['path']);
    }

    #[Test]
    public function theImportsOfAYamlAreStillAddedToo(): void
    {
        $routes = $this->routes([
            'imported.yaml' => "imported:\n  path: /imported\n  handler: 'x'\n",
            'routes.yaml' => "imports:\n  - { resource: 'imported.yaml' }\nblog:\n  path: /blog\n  handler: 'x'\n",
            'routes.php' => "<?php return ['admin' => ['path' => '/admin', 'handler' => 'x']];\n",
        ]);

        $this->assertSame(['imported', 'blog', 'admin'], array_keys($routes));
    }
}

final class MergeEnvironment extends Environment
{
    public static string $directory = '';

    public function getConfigDir(): string
    {
        return self::$directory;
    }
}

final class MergeKernel extends MicroKernel
{
    protected const CONFIG_FILES = [
        'routes.yaml' => 'routes',
        'routes.php' => 'routes',
    ];

    protected const CONFIG_LOADERS = [
        PhpFileLoader::class,
        PhpRoutesLoader::class,
        YamlFileLoader::class,
        YamlRoutesLoader::class,
    ];

    public function container(): ContainerInterface
    {
        return $this->getContainer();
    }
}
