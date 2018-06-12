<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace EzSystems\EzPlatformUserBundle\DependencyInjection\Configuration\Parser;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\AbstractParser;
use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\SiteAccessAware\ContextualizerInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class ResetPassword extends AbstractParser
{
    /**
     * Adds semantic configuration definition.
     *
     * @param \Symfony\Component\Config\Definition\Builder\NodeBuilder $nodeBuilder Node just under ezpublish.system.<siteaccess>
     */
    public function addSemanticConfig(NodeBuilder $nodeBuilder)
    {
        $nodeBuilder
            ->arrayNode('user_reset_password')
                ->info('User reset password configuration')
                ->children()
                    ->arrayNode('templates')
                        ->info('User change password templates.')
                        ->children()
                            ->scalarNode('form')
                                ->info('Template to use for reset password form rendering.')
                            ->end()
                            ->scalarNode('invalid_link')
                                ->info('Template to use for invalid password link rendering.')
                            ->end()
                            ->scalarNode('success')
                                ->info('Template to use for reset password confirmation rendering.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function mapConfig(array &$scopeSettings, $currentScope, ContextualizerInterface $contextualizer)
    {
        if (empty($scopeSettings['user_reset_password'])) {
            return;
        }

        $settings = $scopeSettings['user_reset_password'];

        if (!empty($settings['templates']['form'])) {
            $contextualizer->setContextualParameter(
                'user_reset_password.templates.form',
                $currentScope,
                $settings['templates']['form']
            );
        }

        if (!empty($settings['templates']['invalid_link'])) {
            $contextualizer->setContextualParameter(
                'user_reset_password.templates.invalid_link',
                $currentScope,
                $settings['templates']['invalid_link']
            );
        }

        if (!empty($settings['templates']['success'])) {
            $contextualizer->setContextualParameter(
                'user_reset_password.templates.success',
                $currentScope,
                $settings['templates']['success']
            );
        }
    }
}
