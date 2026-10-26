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

use Derafu\Kernel\Config\Loader\YamlRoutesLoader;
use Derafu\Kernel\Translation\KernelTranslationResourceProvider;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Import the provider-only YAML and consume its tagged translation resources.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function load(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/resources/config')))
            ->load('kernel-services.yaml');

        return $container;
    }

    public function testTheConfiguredProviderCanBeConstructed(): void
    {
        $container = $this->load();
        $container->getDefinition(KernelTranslationResourceProvider::class)->setPublic(true);
        $container->compile();
        $provider = $container->get(KernelTranslationResourceProvider::class);
        $this->assertInstanceOf(KernelTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        $this->assertCount(1, $directories);
        $this->assertSame(realpath(dirname(__DIR__, 2) . '/resources/translations'), realpath($directories[0]));
    }

    public function testTaggedResourcesTranslateAnExceptionFromTheRealRoutesLoader(): void
    {
        $container = $this->load();
        $this->assertArrayHasKey(
            KernelTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        $container->register('app.translator', Translator::class)
            ->setFactory([TranslatorFactory::class, 'create'])
            ->setArguments(['es', ['en'], new TaggedIteratorArgument('derafu_translation.resource_provider')])
            ->setPublic(true);
        $container->compile();
        $translator = $container->get('app.translator');
        $this->assertInstanceOf(Translator::class, $translator);

        // The YAML registers catalogues, while consumers create their loaders.
        $loader = new YamlRoutesLoader($container, new FileLocator());
        try {
            $loader->load(42);
            $this->fail('A routes resource that is not a string was accepted.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertSame('The YAML routes resource must be a string; int given.', $exception->getMessage());
            $this->assertSame('El recurso de rutas YAML debe ser un string; se recibió int.', $exception->trans($translator));
        }
    }
}
