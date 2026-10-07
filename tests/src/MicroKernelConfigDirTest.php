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
 * The configuration files are the ones of the configuration directory.
 *
 * Whatever the directory where the process is running has in it, even a file
 * with the name of one of them, is not read.
 */
#[CoversClass(MicroKernel::class)]
#[UsesClass(Environment::class)]
#[UsesClass(YamlRoutesLoader::class)]
#[UsesClass(PhpRoutesLoader::class)]
final class MicroKernelConfigDirTest extends TestCase
{
    private string|false $cwd;

    private string $directory;

    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->directory = sys_get_temp_dir() . '/' . uniqid('kernel_cwd_');
        mkdir($this->directory);
        file_put_contents($this->directory . '/routes.yaml', "cwd_probe:\n  path: /cwd-probe\n  handler: 'x'\n");
    }

    protected function tearDown(): void
    {
        if ($this->cwd !== false) {
            chdir($this->cwd);
        }
        unlink($this->directory . '/routes.yaml');
        rmdir($this->directory);
    }

    #[Test]
    public function aFileWithTheNameOfOneOfThemInTheCurrentDirectoryIsNotRead(): void
    {
        chdir($this->directory);
        $kernel = new ConfigDirKernel(new ConfigDirEnvironment('test', true), false, uniqid('kernel_'));

        $routes = $kernel->container()->getParameter('routes');

        $this->assertArrayHasKey('users_show', $routes);
        $this->assertArrayNotHasKey('cwd_probe', $routes);
    }
}

final class ConfigDirEnvironment extends Environment
{
    public function getConfigDir(): string
    {
        return dirname(__DIR__) . '/fixtures/config';
    }
}

final class ConfigDirKernel extends MicroKernel
{
    protected const CONFIG_FILES = [
        'routes.yaml' => 'routes',
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
