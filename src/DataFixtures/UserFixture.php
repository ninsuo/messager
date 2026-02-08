<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

class UserFixture extends Fixture
{
    public const USER_ADMIN = 'user-admin';
    public const USER_REGULAR = 'user-regular';

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setUuid(Uuid::v4()->toRfc4122());
        $admin->setPhoneNumber('+33600000001');
        $admin->setIsAdmin(true);
        $manager->persist($admin);
        $this->addReference(self::USER_ADMIN, $admin);

        $regular = new User();
        $regular->setUuid(Uuid::v4()->toRfc4122());
        $regular->setPhoneNumber('+33600000002');
        $regular->setIsAdmin(false);
        $manager->persist($regular);
        $this->addReference(self::USER_REGULAR, $regular);

        $manager->flush();
    }
}
