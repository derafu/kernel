<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsKernel\Config\Loader;

use Derafu\Kernel\Config\Loader\PhpRoutesLoader;
use Derafu\Kernel\Config\Loader\YamlRoutesLoader;
use Derafu\Support\File;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Throwable;

/**
 * A routes file that does not have the expected content is a translatable
 * error that says which file it is and what it has.
 */
#[CoversClass(PhpRoutesLoader::class)]
#[CoversClass(YamlRoutesLoader::class)]
final class RoutesLoadersTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/derafu-kernel-' . uniqid('', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        File::rmdir($this->directory);
    }

    private function assertTranslatableError(callable $load, string $message): void
    {
        $exception = null;
        try {
            $load();
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testAPhpFileThatDoesNotReturnAnArrayIsATranslatableError(): void
    {
        $file = $this->directory . '/routes.php';
        file_put_contents($file, '<?php return "no";');

        $loader = new PhpRoutesLoader(new ContainerBuilder(), new FileLocator($this->directory));

        $this->assertTranslatableError(
            fn () => $loader->load($file),
            sprintf('The PHP file "%s" must return an array, got string.', $file)
        );
    }

    public function testAYamlFileThatIsNotAnArrayIsATranslatableError(): void
    {
        $file = $this->directory . '/routes.yaml';
        file_put_contents($file, 'just text');

        $loader = new YamlRoutesLoader(new ContainerBuilder(), new FileLocator($this->directory));

        $this->assertTranslatableError(
            fn () => $loader->load($file),
            sprintf('The YAML file "%s" has an invalid type, got string.', $file)
        );
    }
}
