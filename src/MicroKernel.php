<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Kernel;

use Derafu\Kernel\Config\Loader\PhpRoutesLoader;
use Derafu\Kernel\Contract\EnvironmentInterface;
use Derafu\Kernel\Contract\KernelInterface;
use Derafu\Support\File;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use ReflectionObject;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\EnvVarProcessor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A lightweight kernel implementation that provides basic container and
 * configuration management.
 *
 * This kernel serves as the core of the application, managing:
 *
 *   - Dependency injection container initialization and configuration.
 *
 * The container is cached in a file in the cache directory, and the next boot
 * loads it instead of building it again:
 *
 *   - In debug mode it is built and dumped on every boot.
 *   - Without debug the file that exists is used as it is, and it is never
 *     checked against the configuration: what the container was built with
 *     (`services` and `routes` files, also the ones of the packages, the
 *     classes that a resource glob finds, and the environment variables, which
 *     are dumped as `env.*` parameters) stays until the file is deleted.
 *
 * The kernel does not clear the cache: it is the deployment that has to start
 * with an empty cache directory (for example, by not sharing `var/cache`
 * between deploys). Two kernels of the same class in the same process do not
 * share a container: the second one that is built gets its own.
 */
class MicroKernel implements KernelInterface
{
    /**
     * The mapping of configuration files to their loader types.
     *
     * Array where keys are filenames and values are loader types.
     *
     * This array defines which configuration files should be loaded and with
     * which loader type. Override this constant to customize the configuration
     * files for your application.
     *
     * Default supported types:
     *
     *   - 'php': PHP configuration files.
     *   - 'yaml': YAML configuration files.
     *   - 'routes': Route configuration files (both PHP and YAML).
     *
     * @var array<string,string>
     */
    protected const CONFIG_FILES = [
        'services.php' => 'php',
        'routes.php' => 'routes',
    ];

    /**
     * Loaders for file configurations.
     *
     * @var class-string[]
     */
    protected const CONFIG_LOADERS = [
        PhpFileLoader::class,
        PhpRoutesLoader::class,
    ];

    /**
     * The current environment.
     *
     * @var EnvironmentInterface
     */
    protected EnvironmentInterface $environment;

    /**
     * Whether the kernel has been booted.
     *
     * @var bool
     */
    protected bool $booted = false;

    /**
     * The cache file that declared each container class in this process.
     *
     * @var array<string, string>
     */
    private static array $loadedContainers = [];

    /**
     * The dependency injection container instance.
     *
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Creates a new Kernel instance.
     *
     * @param string|EnvironmentInterface $environment The environment. Can be
     * an instance of EnvironmentInterface or just the name (e.g., 'dev', 'prod').
     * @param bool $debug Whether to enable debug mode. Used when $environment
     * is a string.
     * @param string|null $kernelId The ID of the kernel. Used to identify the
     * cached container class name.
     */
    public function __construct(
        string|EnvironmentInterface $environment,
        bool $debug = false,
        protected ?string $kernelId = null
    ) {
        $this->environment = $environment instanceof EnvironmentInterface
            ? $environment
            : new Environment($environment, $debug)
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $cachedContainerFile = $this->getCachedContainerFile();
        $cachedContainerClass = $this->getCachedContainerClass();

        $rebuild = $this->environment->isDebug() || !file_exists($cachedContainerFile);
        if ($rebuild) {
            $container = $this->buildContainer();
            $this->cacheContainer($container);
        }

        if (!class_exists('\\' . $cachedContainerClass, false)) {
            // First kernel of this class in the process: it declares the class.
            require $cachedContainerFile;
            self::$loadedContainers[$cachedContainerClass] = $cachedContainerFile;
        } elseif (
            $rebuild
            || (self::$loadedContainers[$cachedContainerClass] ?? null) !== $cachedContainerFile
        ) {
            // The class is already declared (another kernel of the same class
            // in this process) and the container that was just built, or that
            // is in another cache directory, is not the one it has: a class
            // can only be declared once, so this one gets a name of its own.
            $cachedContainerClass = $this->loadContainerWithOwnName(
                $cachedContainerFile,
                $cachedContainerClass
            );
        }

        $this->container = new ('\\' . $cachedContainerClass)();

        $this->booted = true;
    }

    /**
     * Loads the cached container under class names that are not in use.
     *
     * The cache file declares the class of the container, with the name of the
     * kernel (the same for every kernel of the same class), and the classes
     * that stand for the lazy services, with names that depend on the service.
     * A class can only be declared once in a process, so a copy of the file
     * where every class declared has a name of its own is loaded and removed.
     * The cache file is not changed: the next process needs the names it has.
     *
     * @param string $file The cache file.
     * @param string $class The name of the class of the container.
     * @return string The name that the class of the container got.
     */
    private function loadContainerWithOwnName(string $file, string $class): string
    {
        $content = (string) file_get_contents($file);

        preg_match_all(
            '/^(?:(?:abstract|final|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)/m',
            $content,
            $declared
        );

        $suffix = '_' . bin2hex(random_bytes(6));
        foreach (array_unique($declared[1]) as $name) {
            $content = (string) preg_replace(
                '/\b' . preg_quote($name, '/') . '\b/',
                $name . $suffix,
                $content
            );
        }

        // Next to the cache file, where the kernel has just written.
        $copy = $file . $suffix . '.tmp';

        try {
            file_put_contents($copy, $content);
            require $copy;
        } finally {
            @unlink($copy);
        }

        return $class . $suffix;
    }

    /**
     * Gets the ID of the kernel.
     *
     * @return string
     */
    protected function getId(): string
    {
        if (!isset($this->kernelId)) {
            $this->kernelId = md5(static::class);
        }

        return $this->kernelId;
    }

    /**
     * Gets the class name of the cached container.
     *
     * @return string
     */
    protected function getCachedContainerClass(): string
    {
        return 'CachedContainer_' . $this->getId();
    }

    /**
     * Gets the path to the cached container file.
     *
     * @return string
     */
    protected function getCachedContainerFile(): string
    {
        return $this->environment->getCacheDir() . '/container_' . $this->getId() . '.php';
    }

    /**
     * Gets the dependency injection container.
     *
     * Boots the kernel if it hasn't been booted yet.
     *
     * @return ContainerInterface The service container.
     */
    protected function getContainer(): ContainerInterface
    {
        if (!$this->booted) {
            $this->boot();
        }

        return $this->container;
    }

    /**
     * Caches the container by dumping its configuration to a PHP file.
     *
     * This method dumps the container configuration to a PHP file that can be
     * loaded directly in subsequent requests, improving performance by avoiding
     * container rebuilding.
     *
     * The method performs two main tasks:
     *
     *   1. Dumps the container configuration using PhpDumper.
     *   2. Writes the dumped configuration to a file, with `File::write()`:
     *      into a temporary file that is then renamed, so a request that boots
     *      while another one writes never reads a file that is half written.
     *
     * @param ContainerInterface $container The container instance to be cached.
     *
     * @throws RuntimeException If the cache directory cannot be created.
     * @throws RuntimeException If the container cannot be written to the cache file.
     */
    protected function cacheContainer(ContainerInterface $container): void
    {
        $cachedContainerClass = $this->getCachedContainerClass();
        $cachedContainerFile = $this->getCachedContainerFile();

        assert($container instanceof ContainerBuilder);

        $dumper = new PhpDumper($container);

        $content = $dumper->dump([
            'class' => $cachedContainerClass,
        ]);

        File::write($cachedContainerFile, $content);
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

        // Set kernel parameters.
        foreach ($this->environment->toArray() as $name => $value) {
            if ($name === 'env_vars') {
                continue;
            }

            if ($name === 'context') {
                // Replace % with %% in all values.
                // This is necessary to avoid issues with the Symfony parser,
                // avoiding the interpolation of the values with the % sign as
                // parameters.
                array_walk_recursive($value, function (&$contextValue) {
                    if (is_string($contextValue)) {
                        $contextValue = str_replace('%', '%%', $contextValue);
                    }
                });
            }

            $container->setParameter('kernel.' . $name, $value);
        }

        // Set environment variables as parameters.
        // And do the same replacement for the % sign as context values.
        $envVars = $this->environment->getEnvVars();
        array_walk_recursive($envVars, function (&$envValue) {
            if (is_string($envValue)) {
                $envValue = str_replace('%', '%%', $envValue);
            }
        });
        foreach ($envVars as $name => $value) {
            $container->setParameter('env.' . $name, $value);
        }

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
            $this->environment->getConfigDir(),
            $this->environment->getCacheDir(),
            $this->environment->getName()
        );

        // Load all configuration files.
        $this->loadConfiguration($delegatingLoader);

        // Configure the container with additional settings.
        $this->configureContainer($configurator, $container);

        // Register the env var processor.
        $container
            ->register(EnvVarProcessor::class)
            ->setArguments([new Reference('service_container')])
            ->addTag('container.env_var_processor');

        // Compile the container for performance.
        $container->compile();

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
        $fileLocator = new FileLocator($this->environment->getConfigDir());
        $loaders = [];
        foreach (static::CONFIG_LOADERS as $configLoader) {
            $loaders[] = new $configLoader($container, $fileLocator);
        }
        $loaderResolver = new LoaderResolver($loaders);

        return new DelegatingLoader($loaderResolver);
    }

    /**
     * Loads configuration from supported config files.
     *
     * @param DelegatingLoader $loader
     * @return void
     */
    protected function loadConfiguration(DelegatingLoader $loader): void
    {
        // The files of the configuration directory, and only those: a file
        // with the same name in the directory where the process runs (a
        // relative path would be resolved against it) is not part of the
        // configuration.
        foreach (static::CONFIG_FILES as $file => $type) {
            $configFile = $this->environment->getConfigDir() . '/' . $file;
            if (file_exists($configFile)) {
                $loader->load($configFile, $type);
            }
        }
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
        ContainerConfigurator $configurator,
        ContainerBuilder $container
    ): void {
        $services = $configurator->services();

        // Set up default service configuration.
        $services
            ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()
        ;

        // Allow additional configuration through the configure method.
        $this->configure($configurator, $container);
    }

    /**
     * Hook method for additional container configuration.
     *
     * Override this method in child classes to add custom service
     * configuration previous compilation.
     *
     * @param ContainerConfigurator $configurator The container configurator.
     * @param ContainerBuilder $container The container builder.
     */
    protected function configure(
        ContainerConfigurator $configurator,
        ContainerBuilder $container
    ): void {
        // Override this method to add custom service configuration.
    }
}
