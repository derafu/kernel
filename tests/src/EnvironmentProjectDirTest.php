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

use Composer\InstalledVersions;
use Derafu\Kernel\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The project directory is the root package of Composer, unless it is told.
 *
 * It can be told with the `project` directory, or with the `PROJECT_DIR` of
 * the context (what the application receives from the runtime); the `project`
 * directory comes first. It is not deduced from where a package of the
 * dependencies happens to be installed.
 */
#[CoversClass(Environment::class)]
final class EnvironmentProjectDirTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $env;

    /**
     * @var array<string, mixed>
     */
    private array $server;

    protected function setUp(): void
    {
        $this->env = $_ENV;
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->env;
        $_SERVER = $this->server;
    }

    #[Test]
    public function byDefaultItIsTheRootPackageOfComposer(): void
    {
        $this->assertSame(
            realpath(InstalledVersions::getRootPackage()['install_path']),
            (new Environment('test'))->getProjectDir()
        );
    }

    #[Test]
    public function theProjectDirOfTheContextIsUsed(): void
    {
        $directory = sys_get_temp_dir();

        $environment = new Environment('test', false, ['PROJECT_DIR' => $directory]);

        $this->assertSame($directory, $environment->getProjectDir());
        $this->assertSame($directory . '/var/cache/test', $environment->getCacheDir());
    }

    #[Test]
    public function theProjectDirectoryThatIsGivenComesBeforeTheOneOfTheContext(): void
    {
        $environment = new Environment(
            'test',
            false,
            ['PROJECT_DIR' => sys_get_temp_dir()],
            ['project' => dirname(__DIR__, 2)]
        );

        $this->assertSame(dirname(__DIR__, 2), $environment->getProjectDir());
    }
}
