<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformUser\Form\Type;

use EzSystems\RepositoryForms\Data\User\UserRegisterData;
use EzSystems\RepositoryForms\Form\EventSubscriber\UserFieldsSubscriber;
use EzSystems\RepositoryForms\Form\Type\Content\BaseContentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for content edition (create/update).
 * Underlying data will be either \EzSystems\RepositoryForms\Data\Content\ContentCreateData or \EzSystems\RepositoryForms\Data\Content\ContentUpdateData
 * depending on the context (create or update).
 */
class UserRegisterType extends AbstractType
{
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    public function getBlockPrefix()
    {
        return 'ezrepoforms_user_register';
    }

    public function getParent()
    {
        return BaseContentType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('register', SubmitType::class, ['label' => /** @Desc("Register") */ 'user.register_button'])
            ->addEventSubscriber(new UserFieldsSubscriber());
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => UserRegisterData::class,
                'translation_domain' => 'ezrepoforms_user_registration',
                'intent' => 'register',
            ])
            ->setRequired(['languageCode']);
    }
}
