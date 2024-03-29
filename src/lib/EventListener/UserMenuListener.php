<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace EzSystems\EzPlatformUser\EventListener;

use EzSystems\EzPlatformAdminUi\Menu\Event\ConfigureMenuEvent;
use JMS\TranslationBundle\Translation\TranslationContainerInterface;
use Knp\Menu\Util\MenuManipulator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use JMS\TranslationBundle\Model\Message;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserMenuListener implements EventSubscriberInterface, TranslationContainerInterface
{
    public const ITEM_CHANGE_PASSWORD = 'user__change_password';

    /** @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface  */
    private $tokenStorage;

    /**
     * @param \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $tokenStorage
     */
    public function __construct(TokenStorageInterface $tokenStorage) {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [ConfigureMenuEvent::USER_MENU => 'onUserMenuConfigure'];
    }

    /**
     * @param \EzSystems\EzPlatformAdminUi\Menu\Event\ConfigureMenuEvent $event
     */
    public function onUserMenuConfigure(ConfigureMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $token = $this->tokenStorage->getToken();

        if (null !== $token && is_object($token->getUser())) {
            $menu->addChild(
                self::ITEM_CHANGE_PASSWORD,
                [
                    'route' => 'ezplatform.user_profile.change_password',
                    'extras' => ['translation_domain' => 'menu'],
                ]
            );
        }

        $manipulator = new MenuManipulator();
        $manipulator->moveToPosition($menu[self::ITEM_CHANGE_PASSWORD], 0);
    }

    /**
     * @return \JMS\TranslationBundle\Model\Message[]
     */
    public static function getTranslationMessages(): array
    {
        return [
            (new Message(self::ITEM_CHANGE_PASSWORD, 'menu'))->setDesc('Change password'),
        ];
    }
}
