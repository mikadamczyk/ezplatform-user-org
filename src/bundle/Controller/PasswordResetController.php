<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace EzSystems\EzPlatformUserBundle\Controller;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\PermissionResolver;
use eZ\Publish\API\Repository\Values\User\User;
use EzSystems\EzPlatformUser\Form\Factory\FormFactory;
use EzSystems\EzPlatformAdminUi\Notification\NotificationHandlerInterface;
use EzSystems\EzPlatformUser\View\UserForgotPasswordFormView;
use EzSystems\EzPlatformUser\View\UserForgotPasswordLoginView;
use EzSystems\EzPlatformUser\View\UserForgotPasswordSuccessView;
use EzSystems\EzPlatformUser\View\UserResetPasswordFormView;
use EzSystems\EzPlatformUser\View\UserResetPasswordInvalidLinkView;
use EzSystems\EzPlatformUser\View\UserResetPasswordSuccessView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use eZ\Publish\API\Repository\UserService;
use eZ\Publish\API\Repository\Values\User\UserTokenUpdateStruct;
use Swift_Mailer;
use Twig_Environment;
use DateTime;
use DateInterval;
use Swift_Message;

class PasswordResetController extends Controller
{
    /** @var \EzSystems\EzPlatformUser\Form\Factory\FormFactory */
    private $formFactory;

    /** @var \eZ\Publish\API\Repository\UserService */
    private $userService;

    /** @var Swift_Mailer */
    private $mailer;

    /** @var Twig_Environment */
    private $twig;

    /** @var string */
    private $tokenIntervalSpec;

    /** @var \EzSystems\EzPlatformAdminUi\Notification\NotificationHandlerInterface */
    private $notificationHandler;

    /** @var \eZ\Publish\API\Repository\PermissionResolver */
    private $permissionResolver;

    /** @var string */
    private $forgotPasswordMail;

    /**
     * @param \EzSystems\EzPlatformUser\Form\Factory\FormFactory $formFactory
     * @param \eZ\Publish\API\Repository\UserService $userService
     * @param Swift_Mailer $mailer
     * @param Twig_Environment $twig
     * @param \EzSystems\EzPlatformAdminUi\Notification\NotificationHandlerInterface $notificationHandler
     * @param \eZ\Publish\API\Repository\PermissionResolver $permissionResolver
     * @param string $tokenIntervalSpec
     * @param string $forgotPasswordMail
     */
    public function __construct(
        FormFactory $formFactory,
        UserService $userService,
        Swift_Mailer $mailer,
        Twig_Environment $twig,
        NotificationHandlerInterface $notificationHandler,
        PermissionResolver $permissionResolver,
        string $tokenIntervalSpec,
        string $forgotPasswordMail
    ) {
        $this->formFactory = $formFactory;
        $this->userService = $userService;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->notificationHandler = $notificationHandler;
        $this->permissionResolver = $permissionResolver;
        $this->tokenIntervalSpec = $tokenIntervalSpec;
        $this->forgotPasswordMail = $forgotPasswordMail;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     *
     * @return \EzSystems\EzPlatformUser\View\UserForgotPasswordFormView|\EzSystems\EzPlatformUser\View\UserForgotPasswordSuccessView|\Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentType
     */
    public function userForgotPasswordAction(Request $request)
    {
        $form = $this->formFactory->forgotUserPassword();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $users = $this->userService->loadUsersByEmail($data->getEmail());

            /** Because is is possible to have multiple user accounts with same email address we must gain a user login. */
            if (count($users) > 1) {
                return $this->redirectToRoute('ezplatform.user.forgot_password.login');
            }

            if (!empty($users)) {
                $user = reset($users);
                $token = $this->updateUserToken($user);

                $this->sendResetPasswordMessage($user->email, $token);
            }

            return new UserForgotPasswordSuccessView(null);
        }

        return new UserForgotPasswordFormView(null, [
            'form_forgot_user_password' => $form->createView(),
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \EzSystems\EzPlatformUser\View\UserForgotPasswordLoginView|\EzSystems\EzPlatformUser\View\UserForgotPasswordSuccessView
     *
     * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentType
     */
    public function userForgotPasswordLoginAction(Request $request)
    {
        $form = $this->formFactory->forgotUserPasswordWithLogin();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $user = $this->userService->loadUserByLogin($data->getLogin());
            } catch (NotFoundException $e) {
                $user = null;
            }

            if (!$user || count($this->userService->loadUsersByEmail($user->email)) < 2) {
                return new UserForgotPasswordSuccessView(null);
            }

            $token = $this->updateUserToken($user);
            $this->sendResetPasswordMessage($user->email, $token);

            return new UserForgotPasswordSuccessView(null);
        }
        return new UserForgotPasswordLoginView(null, [
            'form_forgot_user_password_with_login' => $form->createView(),
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string $hashKey
     *
     * @return \EzSystems\EzPlatformUser\View\UserResetPasswordFormView|\EzSystems\EzPlatformUser\View\UserResetPasswordInvalidLinkView|\EzSystems\EzPlatformUser\View\UserResetPasswordSuccessView
     *
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentType
     */
    public function userResetPasswordAction(Request $request, string $hashKey)
    {
        $response = new Response();
        $response->headers->set('X-Robots-Tag', 'noindex');

        try {
            $this->userService->loadUserByToken($hashKey);
        } catch (NotFoundException $e) {
            $view = new UserResetPasswordInvalidLinkView(null);
            $view->setResponse($response);

            return $view;
        }

        $form = $this->formFactory->resetUserPassword();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = $this->userService->loadUserByToken($hashKey);
                $currentUser = $this->permissionResolver->getCurrentUserReference();
                $this->permissionResolver->setCurrentUserReference($user);
            } catch (NotFoundException $e) {
                $view = new UserResetPasswordInvalidLinkView(null);
                $view->setResponse($response);

                return $view;
            }

            $data = $form->getData();

            try {
                $userUpdateStruct = $this->userService->newUserUpdateStruct();
                $userUpdateStruct->password = $data->getNewPassword();
                $this->userService->updateUser($user, $userUpdateStruct);
                $this->userService->expireUserToken($hashKey);
                $this->permissionResolver->setCurrentUserReference($currentUser);

                $view = new UserResetPasswordSuccessView(null);
                $view->setResponse($response);

                return $view;
            } catch (\Exception $e) {
                $this->notificationHandler->error($e->getMessage());
            }
        }

        $view = new UserResetPasswordFormView(null, [
            'form_reset_user_password' => $form->createView(),
        ]);
        $view->setResponse($response);

        return $view;
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\User\User $user
     *
     * @return string
     *
     * @throws \Exception
     */
    private function updateUserToken(User $user): string
    {
        $struct = new UserTokenUpdateStruct();
        $struct->hashKey = md5($user->email . microtime(true));
        $date = new DateTime();
        $date->add(new DateInterval($this->tokenIntervalSpec));
        $struct->time = $date;
        $this->userService->updateUserToken($user, $struct);

        return $struct->hashKey;
    }

    /**
     * @param string $to
     * @param string $hashKey
     */
    private function sendResetPasswordMessage(string $to, string $hashKey): void
    {
        $template = $this->twig->loadTemplate($this->forgotPasswordMail);

        $subject = $template->renderBlock('subject', []);
        $from = $template->renderBlock('from', []);
        $body = $template->renderBlock('body', ['hashKey' => $hashKey]);

        $message = (new Swift_Message())
            ->setSubject($subject)
            ->setFrom($from)
            ->setTo($to)
            ->setBody($body, 'text/html');

        $this->mailer->send($message);
    }
}
