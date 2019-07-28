<?php

namespace Umanit\DocumentGeneratorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Class Configuration
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        if (3 <= Kernel::MAJOR_VERSION) {
            $treeBuilder = new TreeBuilder();
            $root        = $treeBuilder->root('umanit_document_generator');
        } else {
            $treeBuilder = new TreeBuilder('umanit_document_generator');
            $root        = $treeBuilder->getRootNode();
        }

        $root
            ->children()
                ->scalarNode('base_uri')
                    ->isRequired()
                    ->info('Base URI of the API used to generate documents.')
                ->end()
                ->scalarNode('encryption_key')
                    ->defaultNull()
                    ->info('Key used to crypt message before calling the API. It must match the one defined in the micro-service.')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
