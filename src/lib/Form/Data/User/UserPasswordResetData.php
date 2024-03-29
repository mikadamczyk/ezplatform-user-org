<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace EzSystems\EzPlatformUser\Form\Data\User;

use Symfony\Component\Validator\Constraints as Assert;

class UserPasswordResetData
{
    /**
     * @Assert\NotBlank()
     *
     * @var string
     */
    private $newPassword;

    /**
     * @param string|null $newPassword
     */
    public function __construct(?string $newPassword = null)
    {
        $this->newPassword = $newPassword;
    }

    /**
     * @param string|null $newPassword
     */
    public function setNewPassword(?string $newPassword): void
    {
        $this->newPassword = $newPassword;
    }

    /**
     * @return string|null
     */
    public function getNewPassword(): ?string
    {
        return $this->newPassword;
    }
}
