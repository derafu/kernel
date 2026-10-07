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

use Derafu\Kernel\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The `.env` files are loaded in a fixed order, by the name of the environment
 * of the kernel.
 *
 * From the one with the least precedence to the one with the most: `.env`,
 * `.env.<environment>`, `.env.<environment>.local` and `.env.local`, each one
 * replacing what the ones before it defined. A variable of the real
 * environment is never changed. In `test` the `.local` files are not loaded, so
 * the machine of who runs the tests does not change them.
 */
#[CoversClass(Environment::class)]
final class EnvironmentFilesTest extends TestCase
{
    private const VARIABLES = ['FILES_WHO', 'FILES_ONLY_ENV', 'FILES_ONLY_DEV', 'FILES_ONLY_LOCAL', 'FILES_REAL', 'APP_ENV'];

    /**
     * @var array<string, mixed>
     */
    private array $env;

    /**
     * @var array<string, mixed>
     */
    private array $server;

    /**
     * @var list<string>
     */
    private array $directories = [];

    protected function setUp(): void
    {
        $this->env = $_ENV;
        $this->server = $_SERVER;
        $this->clean();
    }

    protected function tearDown(): void
    {
        $_ENV = $this->env;
        $_SERVER = $this->server;

        foreach ($this->directories as $directory) {
            foreach (glob($directory . '/.env*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    private function clean(): void
    {
        foreach (self::VARIABLES as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    /**
     * A project directory with the files, that Environment takes as its own.
     *
     * @param array<string, string> $files File name => content.
     */
    private function project(array $files, string $name = 'project'): string
    {
        $directory = sys_get_temp_dir() . '/' . uniqid('kernel_env_') . '/' . $name;
        mkdir($directory, 0777, true);
        $this->directories[] = $directory;
        $this->directories[] = dirname($directory);
        foreach ($files as $file => $content) {
            file_put_contents($directory . '/' . $file, $content);
        }

        return $directory;
    }

    private function load(string $environment, string $project): void
    {
        new Environment($environment, false, [], ['project' => $project]);
    }

    /**
     * @return array<string, string>
     */
    private function everyFile(): array
    {
        return [
            '.env' => "FILES_WHO=env\nFILES_ONLY_ENV=env\n",
            '.env.dev' => "FILES_WHO=dev\nFILES_ONLY_DEV=dev\n",
            '.env.dev.local' => "FILES_WHO=dev.local\n",
            '.env.local' => "FILES_WHO=local\nFILES_ONLY_LOCAL=local\n",
        ];
    }

    #[Test]
    public function theLastFileThatHasAVariableWins(): void
    {
        $this->load('dev', $this->project($this->everyFile()));

        $this->assertSame('local', $_ENV['FILES_WHO']);
        $this->assertSame('env', $_ENV['FILES_ONLY_ENV']);
        $this->assertSame('dev', $_ENV['FILES_ONLY_DEV']);
        $this->assertSame('local', $_ENV['FILES_ONLY_LOCAL']);
    }

    #[Test]
    public function theLocalFileOfTheEnvironmentComesBeforeTheFileOfTheEnvironment(): void
    {
        $files = $this->everyFile();
        unset($files['.env.local']);

        $this->load('dev', $this->project($files));

        $this->assertSame('dev.local', $_ENV['FILES_WHO']);
    }

    #[Test]
    public function inTestTheLocalFilesAreNotLoaded(): void
    {
        $this->load('test', $this->project($this->everyFile() + [
            '.env.test' => "FILES_WHO=test\n",
            '.env.test.local' => "FILES_WHO=test.local\n",
        ]));

        $this->assertSame('test', $_ENV['FILES_WHO']);
        $this->assertSame('env', $_ENV['FILES_ONLY_ENV']);
        $this->assertArrayNotHasKey('FILES_ONLY_LOCAL', $_ENV);
    }

    #[Test]
    public function theFileOfAnotherEnvironmentIsNotLoaded(): void
    {
        $this->load('prod', $this->project($this->everyFile()));

        $this->assertSame('local', $_ENV['FILES_WHO']);
        $this->assertArrayNotHasKey('FILES_ONLY_DEV', $_ENV);
    }

    #[Test]
    public function aProjectDirectoryWithLocalInItsNameLoadsItsFilesInTest(): void
    {
        $this->load('test', $this->project(['.env' => "FILES_ONLY_ENV=env\n"], 'site.local'));

        $this->assertSame('env', $_ENV['FILES_ONLY_ENV']);
    }

    #[Test]
    public function aVariableThatIsAlreadyDefinedIsNotChanged(): void
    {
        $_ENV['FILES_REAL'] = 'real';

        $this->load('dev', $this->project(['.env' => "FILES_REAL=file\n"]));

        $this->assertSame('real', $_ENV['FILES_REAL']);
    }

    #[Test]
    public function theNameOfTheEnvironmentIsNotDefinedWhenNobodyDefinesIt(): void
    {
        $this->load('dev', $this->project(['.env' => "FILES_ONLY_ENV=env\n"]));

        $this->assertArrayNotHasKey('APP_ENV', $_ENV);
        $this->assertArrayNotHasKey('APP_ENV', $_SERVER);
    }

    #[Test]
    public function theNameOfTheEnvironmentIsLoadedWhenAFileDefinesIt(): void
    {
        $this->load('dev', $this->project(['.env' => "APP_ENV=staging\n"]));

        $this->assertSame('staging', $_ENV['APP_ENV']);
    }

    #[Test]
    public function aProjectWithoutFilesIsNotAnError(): void
    {
        $this->load('dev', $this->project([]));

        $this->assertArrayNotHasKey('FILES_WHO', $_ENV);
    }
}
