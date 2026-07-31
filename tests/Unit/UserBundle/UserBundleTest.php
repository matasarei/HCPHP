<?php

namespace Tests\Unit\UserBundle;

use core\DatabaseSQL;
use Html\Form\Form;
use Html\Form\Input;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\AppConfig;
use UnexpectedValueException;
use UserBundle\Entity\Role;
use UserBundle\Entity\User;
use UserBundle\Form\LoginFormFactory;
use UserBundle\Manager\UserManager;
use UserBundle\Mapper\RoleMapper;
use UserBundle\Mapper\UserMapper;
use UserBundle\Repository\RoleRepository;
use UserBundle\Repository\UserRepository;

class UserBundleTest extends TestCase
{
    /**
     * @var DatabaseSQL
     */
    private $database;

    public static function setUpBeforeClass(): void
    {
        AppConfig::ensure();
    }

    public static function tearDownAfterClass(): void
    {
        AppConfig::release();
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $this->database->getDBH()->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firstname TEXT, lastname TEXT, email TEXT, password TEXT,
                role TEXT, authkey TEXT, authtime INTEGER,
                timecreated INTEGER, timemodified INTEGER
            )'
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function role(string $name = 'user'): Role
    {
        return new Role($name, ucfirst($name));
    }

    private function userRepository(): UserRepository
    {
        return new UserRepository($this->database, new UserMapper(new RoleRepository(new RoleMapper())));
    }

    // --- Role ------------------------------------------------------------------------------

    public function testRoleCarriesItsNameAndDescription(): void
    {
        $role = new Role('admin', 'Administrator');

        self::assertSame('admin', $role->getName());
        self::assertSame('Administrator', $role->getDescription());
    }

    /**
     * A role is identified by its name, which is what the users table stores.
     */
    public function testRoleIdIsItsName(): void
    {
        self::assertSame('admin', (new Role('admin', 'Administrator'))->getId());
    }

    public function testRoleCastsToItsName(): void
    {
        self::assertSame('admin', (string)new Role('admin', 'Administrator'));
    }

    // --- User ------------------------------------------------------------------------------

    public function testUserCarriesTheConstructorValues(): void
    {
        $role = $this->role();
        $user = new User('bob@example.com', 'Bob', $role);

        self::assertSame('bob@example.com', $user->getEmail());
        self::assertSame('Bob', $user->getFirstName());
        self::assertSame($role, $user->getRole());
    }

    public function testUserIsStampedOnCreation(): void
    {
        $user = new User('a@b.c', 'A', $this->role());

        self::assertGreaterThan(0, $user->getTimeCreated());
        self::assertGreaterThan(0, $user->getTimeModified());
    }

    public function testUserDefaults(): void
    {
        $user = new User('a@b.c', 'A', $this->role());

        self::assertNull($user->getLastName());
        self::assertNull($user->getPassword());
        self::assertNull($user->getAuthKey());
        self::assertSame(0, $user->getAuthTime());
        self::assertFalse($user->isSuspended());
    }

    public function testUserSettersAreFluentAndRoundTrip(): void
    {
        $newRole = $this->role('admin');
        $user = (new User('a@b.c', 'A', $this->role()))
            ->setEmail('new@b.c')
            ->setFirstName('First')
            ->setLastName('Last')
            ->setPassword('hash')
            ->setAuthKey('key')
            ->setAuthTime(1700000000)
            ->setTimeCreated(100)
            ->setTimeModified(200)
            ->setSuspended(true)
            ->setRole($newRole)
        ;

        self::assertSame('new@b.c', $user->getEmail());
        self::assertSame('First', $user->getFirstName());
        self::assertSame('Last', $user->getLastName());
        self::assertSame('hash', $user->getPassword());
        self::assertSame('key', $user->getAuthKey());
        self::assertSame(1700000000, $user->getAuthTime());
        self::assertSame(100, $user->getTimeCreated());
        self::assertSame(200, $user->getTimeModified());
        self::assertTrue($user->isSuspended());
        self::assertSame($newRole, $user->getRole());
    }

    /**
     * @dataProvider fullNameProvider
     */
    public function testFullNameFormatting(?string $last, string $format, string $expected): void
    {
        $user = (new User('a@b.c', 'Bob', $this->role()))->setLastName($last);

        self::assertSame($expected, $user->getFullName($format));
    }

    public function fullNameProvider(): array
    {
        return [
            'default' => ['Smith', '%f %l', 'Bob Smith'],
            'surname first' => ['Smith', '%l %f', 'Smith Bob'],
            'first only' => ['Smith', '%f', 'Bob'],
            'no surname collapses the gap' => [null, '%f %l', 'Bob'],
            'comma format' => ['Smith', '%l, %f', 'Smith, Bob'],
        ];
    }

    public function testUserCastsToItsFullName(): void
    {
        $user = (new User('a@b.c', 'Bob', $this->role()))->setLastName('Smith');

        self::assertSame('Bob Smith', (string)$user);
    }

    public function testAuthKeyCanBeCleared(): void
    {
        $user = (new User('a@b.c', 'A', $this->role()))->setAuthKey('key')->setAuthKey(null);

        self::assertNull($user->getAuthKey());
    }

    // --- RoleMapper and RoleRepository ---------------------------------------------------------

    public function testRoleMapperBuildsARole(): void
    {
        $role = (new RoleMapper())->mapToEntity(['name' => 'admin', 'desc' => 'Administrator']);

        self::assertSame('admin', $role->getName());
        self::assertSame('Administrator', $role->getDescription());
    }

    public function testRoleMapperIsReadOnly(): void
    {
        $this->expectException(RuntimeException::class);

        (new RoleMapper())->mapFromEntity($this->role());
    }

    public function testRoleRepositoryReadsTheConfiguredRoles(): void
    {
        $repository = new RoleRepository(new RoleMapper());

        self::assertGreaterThan(0, count($repository->find()));
        self::assertInstanceOf(Role::class, $repository->get('user'));
    }

    public function testRoleRepositoryReturnsNullForAnUnknownRole(): void
    {
        self::assertNull((new RoleRepository(new RoleMapper()))->get('no_such_role'));
    }

    public function testRoleRepositoryFiltersByName(): void
    {
        $found = (new RoleRepository(new RoleMapper()))->find(['name' => 'user']);

        self::assertCount(1, $found);
    }

    public function testRoleRepositoryIsReadOnly(): void
    {
        $repository = new RoleRepository(new RoleMapper());

        $this->expectException(RuntimeException::class);

        $repository->save($this->role());
    }

    public function testRoleRepositoryRemoveIsAlsoRefused(): void
    {
        $repository = new RoleRepository(new RoleMapper());

        $this->expectException(RuntimeException::class);

        $repository->remove($this->role());
    }

    // --- UserMapper ------------------------------------------------------------------------------

    public function testUserMapperRoundTripsThroughTheDatabaseShape(): void
    {
        $mapper = new UserMapper(new RoleRepository(new RoleMapper()));

        $data = $mapper->mapFromEntity(
            (new User('bob@example.com', 'Bob', $this->role()))
                ->setId(3)
                ->setLastName('Smith')
                ->setPassword('hash')
                ->setAuthKey('key')
                ->setAuthTime(50)
        );

        self::assertSame(3, $data['id']);
        self::assertSame('bob@example.com', $data['email']);
        self::assertSame('Bob', $data['firstname']);
        self::assertSame('Smith', $data['lastname']);
        self::assertSame('hash', $data['password']);
        self::assertSame('user', $data['role']);
        self::assertSame('key', $data['authkey']);
        self::assertSame(50, $data['authtime']);

        $user = $mapper->mapToEntity($data);

        self::assertSame(3, $user->getId());
        self::assertSame('bob@example.com', $user->getEmail());
        self::assertSame('Smith', $user->getLastName());
        self::assertSame('key', $user->getAuthKey());
    }

    /**
     * A row naming a role that the configuration no longer defines cannot become a User, and
     * saying so beats returning one with a broken role.
     */
    public function testUserMapperRefusesAnUnknownRole(): void
    {
        $mapper = new UserMapper(new RoleRepository(new RoleMapper()));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('No role found for "ghost"');

        $mapper->mapToEntity([
            'id' => 1, 'email' => 'a@b.c', 'firstname' => 'A', 'lastname' => null,
            'password' => null, 'role' => 'ghost', 'authkey' => null, 'authtime' => 0,
            'timecreated' => 0, 'timemodified' => 0,
        ]);
    }

    // --- UserRepository and UserManager -----------------------------------------------------------

    public function testUserManagerCreatesAndStoresAUser(): void
    {
        $manager = new UserManager($this->userRepository(), new RoleRepository(new RoleMapper()));

        $user = $manager->createUser('bob@example.com', 'Bob', 'secret123');

        self::assertNotNull($user->getId());
        self::assertSame('bob@example.com', $user->getEmail());
        self::assertNotNull($this->userRepository()->findOne(['email' => 'bob@example.com']));
    }

    /**
     * The stored password must be a hash, never the password itself.
     */
    public function testUserManagerHashesThePassword(): void
    {
        $manager = new UserManager($this->userRepository(), new RoleRepository(new RoleMapper()));

        $user = $manager->createUser('bob@example.com', 'Bob', 'secret123');

        self::assertNotSame('secret123', $user->getPassword());
        self::assertTrue(password_verify('secret123', $user->getPassword()));
    }

    public function testUserManagerRefusesAnUnknownRole(): void
    {
        $manager = new UserManager($this->userRepository(), new RoleRepository(new RoleMapper()));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Wrong role name');

        $manager->createUser('a@b.c', 'A', 'x', 'no_such_role');
    }

    public function testUserRepositoryUsesTheUsersTable(): void
    {
        $manager = new UserManager($this->userRepository(), new RoleRepository(new RoleMapper()));
        $manager->createUser('bob@example.com', 'Bob', 'secret123');

        self::assertCount(1, $this->database->getRecords('users'));
    }

    // --- LoginFormFactory ---------------------------------------------------------------------------

    public function testLoginFormHasEmailPasswordAndASubmitButton(): void
    {
        $form = (new LoginFormFactory())->createForm();

        self::assertInstanceOf(Form::class, $form);

        $names = array_map(function ($field) {
            return $field->getName();
        }, $form->getFields());

        self::assertSame(['email', 'password'], $names);
        self::assertCount(1, $form->getButtons());
    }

    public function testLoginFormMasksThePassword(): void
    {
        $fields = (new LoginFormFactory())->createForm()->getFields();

        self::assertInstanceOf(Input::class, $fields[1]);
        self::assertSame('password', $fields[1]->getType());
    }

    /**
     * The login form posts credentials, so it has to carry a token.
     */
    public function testLoginFormCarriesACsrfToken(): void
    {
        self::assertNotNull((new LoginFormFactory())->createForm()->getSessionKey());
    }
}
