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
use Derafu\Kernel\Environment;
use Derafu\Kernel\MicroKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The container is cached in a file, and a kernel loads it as a class.
 *
 * The name of the class is the same for every kernel of the same class (the
 * id of the kernel is the hash of its class), and a class can only be declared
 * once in a process: a second kernel, built with the configuration of its
 * moment, can not take its container from a class that the first one declared.
 */
#[CoversClass(MicroKernel::class)]
#[UsesClass(Environment::class)]
#[UsesClass(PhpRoutesLoader::class)]
final class MicroKernelCacheTest extends TestCase
{
    private string $id;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->id = uniqid('kernel_cache_');
        $this->cacheDir = (new Environment('test'))->getCacheDir();
        ProbeKernel::$value = 'first';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDir . '/container_' . $this->id . '*') ?: [] as $file) {
            unlink($file);
        }
    }

    private function kernel(bool $debug): ProbeKernel
    {
        return new ProbeKernel(new Environment('test', $debug), false, $this->id);
    }

    #[Test]
    public function aSecondKernelOfTheSameClassGetsItsOwnConfiguration(): void
    {
        $first = $this->kernel(true);
        $this->assertSame('first', $first->container()->getParameter('probe'));

        ProbeKernel::$value = 'second';
        $second = $this->kernel(true);

        $this->assertSame('second', $second->container()->getParameter('probe'));
        // The first one keeps its own.
        $this->assertSame('first', $first->container()->getParameter('probe'));
    }

    #[Test]
    public function aSecondKernelWithLazyServicesDoesNotDeclareTheirClassesTwice(): void
    {
        // The file of the container also declares the classes that stand for
        // the lazy services, with names that depend on the service and not on
        // the kernel: the copy of the second kernel can not declare them again.
        ProbeKernel::$lazy = true;
        try {
            $first = $this->kernel(true);
            $this->assertInstanceOf(ProbeServiceInterface::class, $first->container()->get('probe.lazy'));

            ProbeKernel::$value = 'second';
            $second = $this->kernel(true);
            $this->assertInstanceOf(ProbeServiceInterface::class, $second->container()->get('probe.lazy'));
            $this->assertSame('second', $second->container()->getParameter('probe'));
            $this->assertSame('first', $first->container()->getParameter('probe'));
        } finally {
            ProbeKernel::$lazy = false;
        }
    }

    #[Test]
    public function theCacheFileKeepsTheNameOfTheClassForTheNextProcess(): void
    {
        $this->kernel(true)->container();
        ProbeKernel::$value = 'second';
        $this->kernel(true)->container();

        $file = $this->cacheDir . '/container_' . $this->id . '.php';
        $this->assertFileExists($file);
        $this->assertStringContainsString('class CachedContainer_' . $this->id . ' extends', (string) file_get_contents($file));
    }

    #[Test]
    public function loadingASecondContainerLeavesNothingInTheCacheDirectory(): void
    {
        $this->kernel(true)->container();
        $this->kernel(true)->container();

        $files = glob($this->cacheDir . '/container_' . $this->id . '*') ?: [];
        $this->assertSame([$this->cacheDir . '/container_' . $this->id . '.php'], $files);
    }

    #[Test]
    public function withoutDebugTheCacheThatIsThereIsUsedWhateverTheConfigurationSays(): void
    {
        // This is what production does: the cache is not checked against the
        // configuration, it is cleared when the application is deployed.
        $first = $this->kernel(false);
        $this->assertSame('first', $first->container()->getParameter('probe'));

        ProbeKernel::$value = 'second';
        $second = $this->kernel(false);

        $this->assertSame('first', $second->container()->getParameter('probe'));
    }

    #[Test]
    public function withDebugTheCacheIsAlwaysBuiltAgain(): void
    {
        $this->kernel(false)->container();
        ProbeKernel::$value = 'second';

        $this->assertSame('second', $this->kernel(true)->container()->getParameter('probe'));
    }
}

interface ProbeServiceInterface
{
}

final class ProbeService implements ProbeServiceInterface
{
}

final class ProbeKernel extends MicroKernel
{
    public static string $value = 'first';

    public static bool $lazy = false;

    public function container(): ContainerInterface
    {
        return $this->getContainer();
    }

    protected function configure(
        ContainerConfigurator $configurator,
        ContainerBuilder $container
    ): void {
        $container->setParameter('probe', self::$value);

        if (self::$lazy) {
            $configurator->services()
                ->set('probe.lazy', ProbeService::class)
                ->lazy(ProbeServiceInterface::class)
                ->public()
            ;
        }
    }
}
