<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class AdminControllerTest extends WebTestCase
{
    private function createTestUser(string $phone = '+33600000001', bool $admin = false): User
    {
        $user = new User();
        $user->setUuid(Uuid::v4()->toRfc4122());
        $user->setPhoneNumber($phone);
        $user->setIsAdmin($admin);

        $userRepository = self::getContainer()->get(UserRepository::class);
        $userRepository->save($user);

        return $user;
    }

    public function testAdminDashboardRequiresAdmin(): void
    {
        $client = static::createClient();
        $user = $this->createTestUser('+33600000010');

        $client->loginUser($user);
        $client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminDashboardLoads(): void
    {
        $client = static::createClient();
        $admin = $this->createTestUser('+33600000011', true);

        $client->loginUser($admin);
        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Utilisateurs');
    }

    public function testUserListLoads(): void
    {
        $client = static::createClient();
        $admin = $this->createTestUser('+33600000012', true);

        $client->loginUser($admin);
        $client->request('GET', '/admin/users');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('table');
    }

    public function testUserListMasksPhoneNumbers(): void
    {
        $client = static::createClient();
        $admin = $this->createTestUser('+33612345678', true);

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/users');

        $this->assertResponseIsSuccessful();
        $tableText = $crawler->filter('table')->text();
        $this->assertStringNotContainsString('+33612345678', $tableText);
        $this->assertStringContainsString('+336XXXX5678', $tableText);
    }

    public function testCreateUser(): void
    {
        $client = static::createClient();
        $admin = $this->createTestUser('+33600000013', true);

        $client->loginUser($admin);
        $client->request('GET', '/admin/users');

        $client->submitForm('Créer', [
            'admin_create_user_form[phone]' => '+33699999999',
        ]);

        $this->assertResponseRedirects('/admin/users');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Utilisateur créé');

        $userRepository = self::getContainer()->get(UserRepository::class);
        $this->assertNotNull($userRepository->findByPhoneNumber('+33699999999'));
    }

    public function testCreateDuplicateUserShowsError(): void
    {
        $client = static::createClient();
        $admin = $this->createTestUser('+33600000014', true);
        $this->createTestUser('+33688888888');

        $client->loginUser($admin);
        $client->request('GET', '/admin/users');

        $client->submitForm('Créer', [
            'admin_create_user_form[phone]' => '+33688888888',
        ]);

        $this->assertResponseRedirects('/admin/users');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'existe déjà');
    }

    public function testGrantAdmin(): void
    {
        $client = static::createClient();
        $admin = $this->createTestUser('+33600000015', true);
        $user = $this->createTestUser('+33600000016');

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/' . $user->getUuid() . '/grant-admin');

        $this->assertResponseRedirects('/admin/users');

        $userRepository = self::getContainer()->get(UserRepository::class);
        $updated = $userRepository->findByPhoneNumber('+33600000016');
        $this->assertTrue($updated->isAdmin());
    }

    public function testRevokeAdmin(): void
    {
        $client = static::createClient();
        $admin = $this->createTestUser('+33600000017', true);
        $otherAdmin = $this->createTestUser('+33600000018', true);

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/' . $otherAdmin->getUuid() . '/revoke-admin');

        $this->assertResponseRedirects('/admin/users');

        $userRepository = self::getContainer()->get(UserRepository::class);
        $updated = $userRepository->findByPhoneNumber('+33600000018');
        $this->assertFalse($updated->isAdmin());
    }

    public function testCannotRevokeSelfAdmin(): void
    {
        $client = static::createClient();
        $admin = $this->createTestUser('+33600000019', true);

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/' . $admin->getUuid() . '/revoke-admin');

        $this->assertResponseRedirects('/admin/users');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'propres droits');

        $userRepository = self::getContainer()->get(UserRepository::class);
        $self = $userRepository->findByPhoneNumber('+33600000019');
        $this->assertTrue($self->isAdmin());
    }

    public function testDeleteUser(): void
    {
        $client = static::createClient();
        $admin = $this->createTestUser('+33600000020', true);
        $user = $this->createTestUser('+33600000021');

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/' . $user->getUuid() . '/delete');

        $this->assertResponseRedirects('/admin/users');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'supprimé');

        $userRepository = self::getContainer()->get(UserRepository::class);
        $this->assertNull($userRepository->findByPhoneNumber('+33600000021'));
    }

    public function testCannotDeleteAdmin(): void
    {
        $client = static::createClient();
        $admin = $this->createTestUser('+33600000022', true);
        $otherAdmin = $this->createTestUser('+33600000023', true);

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/' . $otherAdmin->getUuid() . '/delete');

        $this->assertResponseRedirects('/admin/users');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'administrateur');

        $userRepository = self::getContainer()->get(UserRepository::class);
        $this->assertNotNull($userRepository->findByPhoneNumber('+33600000023'));
    }

    public function testCannotDeleteSelf(): void
    {
        $client = static::createClient();
        $admin = $this->createTestUser('+33600000024', true);

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/' . $admin->getUuid() . '/delete');

        $this->assertResponseRedirects('/admin/users');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'propre compte');

        $userRepository = self::getContainer()->get(UserRepository::class);
        $this->assertNotNull($userRepository->findByPhoneNumber('+33600000024'));
    }
}
