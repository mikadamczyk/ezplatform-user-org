<?php
/**
 * This file is part of the eZ RepositoryForms package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace EzSystems\EzPlatformUserBundle\Controller;

use eZ\Publish\Core\MVC\Symfony\Security\Authorization\Attribute;
use EzSystems\EzPlatformUser\Form\DataMapper\UserRegisterMapper;
use EzSystems\EzPlatformUser\View\UserRegisterConfirmView;
use EzSystems\EzPlatformUser\View\UserRegisterFormView;
use EzSystems\RepositoryForms\Form\ActionDispatcher\ActionDispatcherInterface;
use EzSystems\EzPlatformUser\Form\Type\UserRegisterType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class UserRegisterController extends Controller
{
    /** @var \EzSystems\EzPlatformUser\Form\DataMapper\UserRegisterMapper */
    private $userRegisterMapper;

    /** @var \EzSystems\RepositoryForms\Form\ActionDispatcher\ActionDispatcherInterface */
    private $userActionDispatcher;

    /**
     * @param \EzSystems\EzPlatformUser\Form\DataMapper\UserRegisterMapper $userRegisterMapper
     * @param \EzSystems\RepositoryForms\Form\ActionDispatcher\ActionDispatcherInterface $userActionDispatcher
     */
    public function __construct(
        UserRegisterMapper $userRegisterMapper,
        ActionDispatcherInterface $userActionDispatcher
    ) {
        $this->userRegisterMapper = $userRegisterMapper;
        $this->userActionDispatcher = $userActionDispatcher;
    }

    /**
     * Displays and processes a user registration form.
     *
     * @param Request $request
     *
     * @return \EzSystems\EzPlatformUser\View\UserRegisterFormView|Response
     *
     * @throws \Exception if the current user isn't allowed to register an account
     */
    public function registerAction(Request $request)
    {
        if (!$this->isGranted(new Attribute('user', 'register'))) {
            throw new UnauthorizedHttpException('You are not allowed to register a new account');
        }

        $data = $this->userRegisterMapper->mapToFormData();
        $language = $data->mainLanguageCode;

        /** @var \Symfony\Component\Form\Form $form */
        $form = $this->createForm(
            UserRegisterType::class,
            $data,
            ['languageCode' => $language, 'mainLanguageCode' => $language]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && null !== $form->getClickedButton()) {
            $this->userActionDispatcher->dispatchFormAction($form, $data, $form->getClickedButton()->getName());
            if ($response = $this->userActionDispatcher->getResponse()) {
                return $response;
            }
        }

        return new UserRegisterFormView(null, ['form' => $form->createView()]);
    }

    /**
     * @return \EzSystems\EzPlatformUser\View\UserRegisterConfirmView
     *
     * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentType
     */
    public function registerConfirmAction(): UserRegisterConfirmView
    {
        return new UserRegisterConfirmView();
    }
}