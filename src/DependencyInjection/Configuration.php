<?php

namespace Umanit\DocumentGeneratorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

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
        $treeBuilder = new TreeBuilder('umanit_document_generator');

        $treeBuilder->getRootNode()
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
