<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testDefaultIsAdminFalse(): void
    {
        $user = new User();

        $this->assertFalse($user->isAdmin());
    }

    public function testGetRolesRegularUser(): void
    {
        $user = new User();

        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testGetRolesAdminUser(): void
    {
        $user = new User();
        $user->setIsAdmin(true);

        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testGetUserIdentifier(): void
    {
        $user = new User();
        $user->setUuid('test-uuid-1234');

        $this->assertSame('test-uuid-1234', $user->getUserIdentifier());
    }
}
