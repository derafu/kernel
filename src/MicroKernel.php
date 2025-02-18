<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Kernel;

use Composer\InstalledVersions;
use Derafu\Kernel\Contract\KernelInterface;
use ReflectionObject;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * A lightweight kernel implementation that provides basic container and
 * configuration management.
 *
 * This kernel serves as the core of the application, managing:
 *
 *   - Dependency injection container initialization and configuration.
 *   - Environment and debug settings.
 *   - Project directory structure.
 *   - Configuration loading from PHP and YAML files.
 */
class MicroKernel implements KernelInterface
{
    /**
     * The current environment (e.g., 'dev', 'prod').
     *
     * @var string
     */
    protected string $environment;

    /**
     * Whether debug mode is enabled.
     *
     * @var bool
     */
    protected bool $debug;

    /**
     * Whether the kernel has been booted.
     *
     * @var bool
     */
    protected bool $booted = false;

    /**
     * The project's root directory path.
     *
     * @var string
     */
    protected string $projectDir;

    /**
     * The dependency injection container instance.
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Creates a new Kernel instance.
     *
     * @param string $environment The environment name (e.g., 'dev', 'prod').
     * @param bool $debug Whether to enable debug mode.
     */
    public function __construct(string $environment, bool $debug = false)
    {
        $this->environment = $environment;
        $this->debug = $debug;
    }

    /**
     * Gets the current environment.
     *
     * @return string The environment name.
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool True if debug mode is enabled, false otherwise.
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Gets the dependency injection container.
     *
     * Boots the kernel if it hasn't been booted yet.
     *
     * @return ContainerInterface The service container.
     */
    public function getContainer(): ContainerInterface
    {
        if (!$this->booted) {
            $this->boot();
        }

        return $this->container;
    }

    /**
     * Gets the project root directory.
     *
     * Determines the project directory based on the location of the symfony
     * dependency-injection package.
     *
     * This assumes the project follows a standard Composer directory structure.
     *
     * @return string The project root directory path.
     */
    public function getProjectDir(): string
    {
        if (!isset($this->projectDir)) {
            $routingPackagePath = realpath(
                InstalledVersions::getInstallPath('symfony/dependency-injection')
            );
            $this->projectDir = dirname($routingPackagePath, 3);
        }

        return $this->projectDir;
    }

    /**
     * Gets the build directory for compiled assets and other build artifacts.
     *
     * @return string The build directory path.
     */
    public function getBuildDir(): string
    {
        return $this->getProjectDir() . '/build';
    }

    /**
     * Gets the cache directory for the current environment.
     *
     * @return string The cache directory path
     */
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }

    /**
     * Gets the configuration directory.
     *
     * @return string The config directory path.
     */
    public function getConfigDir(): string
    {
        return $this->getProjectDir() . '/config';
    }

    /**
     * Gets the log directory.
     *
     * @return string The log directory path.
     */
    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }

    /**
     * Gets the resources directory.
     *
     * @return string The resources directory path.
     */
    public function getResourcesDir(): string
    {
        return $this->getProjectDir() . '/resources';
    }

    /**
     * Gets the translations directory.
     *
     * @return string The translations directory path.
     */
    public function getTranslationsDir(): string
    {
        return $this->getProjectDir() . '/translations';
    }

    /**
     * Gets the templates directory.
     *
     * @return string The templates directory path.
     */
    public function getTemplatesDir(): string
    {
        return $this->getProjectDir() . '/templates';
    }

    /**
     * Boots the kernel.
     *
     * This method initializes the container and marks the kernel as booted.
     * It ensures this only happens once, even if called multiple times.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->container = $this->buildContainer();

        $this->booted = true;
    }

    /**
     * Builds and configures the dependency injection container.
     *
     * This method:
     *
     *   1. Creates a new container builder.
     *   2. Sets up basic parameters.
     *   3. Creates and configures loaders.
     *   4. Loads configuration files.
     *   5. Compiles the container.
     *
     * @return ContainerInterface The configured container.
     */
    protected function buildContainer(): ContainerInterface
    {
        // Initialize container and set basic parameters.
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->getProjectDir());
        $container->setParameter('kernel.build_dir', $this->getBuildDir());
        $container->setParameter('kernel.cache_dir', $this->getCacheDir());
        $container->setParameter('kernel.config_dir', $this->getConfigDir());
        $container->setParameter('kernel.log_dir', $this->getLogDir());
        $container->setParameter('kernel.resources_dir', $this->getResourcesDir());
        $container->setParameter('kernel.translations_dir', $this->getTranslationsDir());
        $container->setParameter('kernel.templates_dir', $this->getTemplatesDir());
        $container->setParameter('kernel.environment', $this->environment);
        $container->setParameter('kernel.debug', $this->debug);

        // Create the delegating loader for configuration files.
        $delegatingLoader = $this->getDelegatingLoader($container);

        // Get the kernel's own configuration file for initialization.
        $configureContainer = new ReflectionObject($this);
        $file = $configureContainer->getFileName();

        // Resolve the loader for PHP files.
        /** @var PhpFileLoader $kernelLoader */
        $kernelLoader = $delegatingLoader->getResolver()->resolve($file);

        // Create the container configurator.
        $instanceof = [];
        $configurator = new ContainerConfigurator(
            $container,
            $kernelLoader,
            $instanceof,
            $this->getConfigDir(),
            $this->getCacheDir(),
            $this->environment
        );

        // Load all configuration files.
        $this->loadConfigurations($delegatingLoader);

        // Configure the container with additional settings.
        $this->configureContainer($configurator);

        // Compile the container for performance.
        $container->compile();

        // Set the kernel as a synthetic service after compilation.
        $container->set(static::class, $this);

        return $container;
    }

    /**
     * Creates and returns a delegating loader for configuration files.
     *
     * Supports both PHP and YAML configuration files through appropriate
     * loaders.
     *
     * @param ContainerBuilder $container The container being built.
     * @return DelegatingLoader The configured loader.
     */
    protected function getDelegatingLoader(
        ContainerBuilder $container
    ): DelegatingLoader {
        $fileLocator = new FileLocator($this->getConfigDir());
        $loaderResolver = new LoaderResolver([
            new PhpFileLoader($container, $fileLocator),
            new YamlFileLoader($container, $fileLocator),
        ]);

        return new DelegatingLoader($loaderResolver);
    }

    /**
     * Loads configuration files from the config directory.
     *
     * Attempts to load 'parameters', 'routes', and 'services' configurations
     * from both PHP and YAML files.
     *
     * @param DelegatingLoader $loader The loader to use.
     */
    protected function loadConfigurations(DelegatingLoader $loader): void
    {
        // Configuration files to look for
        $configFiles = ['parameters', 'routes', 'services'];
        $extensions = $this->getConfigurationFileExtensions();

        // Try loading each configuration file with each supported extension
        foreach ($configFiles as $file) {
            foreach ($extensions as $extension) {
                $configFile = $this->getConfigDir() . '/' . $file . '.' . $extension;
                if (file_exists($configFile)) {
                    $loader->load($configFile);
                }
            }
        }
    }

    /**
     * Gets the list of supported configuration file extensions.
     *
     * @return array<string> Array of supported file extensions
     */
    protected function getConfigurationFileExtensions(): array
    {
        return ['yaml', 'php'];
    }

    /**
     * Configures the dependency injection container with default settings.
     *
     * Sets up:
     *
     *   - Default service configuration (autowiring, autoconfiguration).
     *   - Kernel service registration.
     *   - Additional custom configuration through configure() method.
     *
     * @param ContainerConfigurator $configurator The container configurator.
     */
    protected function configureContainer(
        ContainerConfigurator $configurator
    ): void {
        $services = $configurator->services();

        // Set up default service configuration.
        $services
            ->defaults()
            ->autowire()
            ->autoconfigure()
        ;

        // Register the kernel as a synthetic service.
        $services
            ->set(static::class)
            ->synthetic()
            ->public()
        ;

        // Allow additional configuration through the configure method.
        $this->configure($configurator);
    }

    /**
     * Hook method for additional container configuration.
     *
     * Override this method in child classes to add custom service
     * configuration.
     *
     * @param ContainerConfigurator $configurator The container configurator.
     */
    protected function configure(ContainerConfigurator $configurator): void
    {
    }
}
