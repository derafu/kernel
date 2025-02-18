<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsKernel;

use Derafu\Kernel\Environment;
use Derafu\Kernel\MicroKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

#[CoversClass(MicroKernel::class)]
#[CoversClass(Environment::class)]
class MicroKernelTest extends TestCase
{
    private TestEnvironment $environment;

    private TestKernel $kernel;

    protected function setUp(): void
    {
        $this->environment = new TestEnvironment('test', true);
        $this->kernel = new TestKernel($this->environment);
    }

    public function testKernelInitialization(): void
    {
        $this->assertSame('test', $this->environment->getName());
        $this->assertTrue($this->environment->isDebug());
        $this->assertFalse($this->kernel->isBooted());
    }

    public function testDirectoryStructure(): void
    {
        $projectDir = $this->environment->getProjectDir();

        $this->assertDirectoryExists($projectDir);
        $this->assertDirectoryExists($this->environment->getConfigDir());
        $this->assertSame($projectDir . '/tests/fixtures/config', $this->environment->getConfigDir());
        $this->assertSame($projectDir . '/var/cache/test', $this->environment->getCacheDir());
        $this->assertSame($projectDir . '/var/log', $this->environment->getLogDir());
    }

    public function testConfigurationLoading(): void
    {
        $container = $this->kernel->getContainer();

        $this->assertTrue($container->has('test.service'));
        $this->assertInstanceOf(stdClass::class, $container->get('test.service'));
    }

    public function testMultipleBoots(): void
    {
        $container1 = $this->kernel->getContainer();
        $container2 = $this->kernel->getContainer();

        $this->assertSame($container1, $container2);
    }

    public function testConfigureHook(): void
    {
        $kernel = new Test2Kernel('test', true);

        $container = $kernel->getContainer();

        $this->assertTrue($kernel->wasConfigureCalled());
        $this->assertTrue($container->has('custom.service'));
    }
}

class TestEnvironment extends Environment
{
    public function getConfigDir(): string
    {
        return dirname(__DIR__) . '/fixtures/config';
    }

    protected function getConfigurationFileExtensions(): array
    {
        return ['php']; // Solo PHP para simplificar los tests.
    }
}

class TestKernel extends MicroKernel
{
    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function getContainer(): ContainerInterface
    {
        return parent::getContainer();
    }
}

class Test2Kernel extends MicroKernel
{
    private bool $configureWasCalled = false;

    protected function configure(ContainerConfigurator $configurator): void
    {
        $this->configureWasCalled = true;
        $services = $configurator->services();
        $services->set('custom.service', stdClass::class)->public();
    }

    public function wasConfigureCalled(): bool
    {
        return $this->configureWasCalled;
    }

    public function getContainer(): ContainerInterface
    {
        return parent::getContainer();
    }
}
