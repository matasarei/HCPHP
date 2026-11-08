<?php

namespace Tests\Plugin;

use core\DatabaseSQL;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatabaseTestCase;
use UserBundle\Entity\User;
use UserBundle\Mapper\RoleMapper;
use UserBundle\Mapper\UserMapper;
use UserBundle\Repository\RoleRepository;
use UserBundle\Repository\UserRepository;

require_once dirname(__DIR__, 2) . '/templates/DatabaseTestCase.php';

/**
 * Runs the database test case template against HCPHP's own mappers: the tables it builds must
 * hold what the real repository writes and find it again, with no schema written by hand.
 */
class DatabaseTestCaseTemplateTest extends TestCase
{
    public function testATableBuiltFromTheMapperRoundTripsWhatTheRepositoryWrites(): void
    {
        $probe = $this->probe();
        $probe->start();

        $roles = new RoleRepository(new RoleMapper());
        $mapper = new UserMapper($roles);
        $user = (new User('alice@example.com', 'Alice', $roles->get('user')))
            ->setAuthKey('key_alice')
            ->setAuthTime(time());

        $probe->createTableFromRow('users', $probe->rowFromMapper($mapper, 'mapFromEntity', $user));

        $repository = new UserRepository($probe->database(), $mapper);
        $repository->save($user);

        self::assertNotNull($user->getId());
        self::assertSame('alice@example.com', $repository->getByAuthKey('key_alice')->getEmail());
        self::assertSame('Alice', $repository->get($user->getId())->getFirstName());

        $probe->finish();
    }

    public function testColumnTypesFollowTheSampleValuesUnlessGiven(): void
    {
        $probe = $this->probe();
        $probe->start();

        $probe->createTableFromRow(
            'samples',
            ['id' => null, 'count' => 3, 'ratio' => 1.5, 'flag' => true, 'phone' => '0991234567', 'seen' => null],
            ['seen' => 'INTEGER']
        );

        $types = [];
        foreach ($probe->database()->getRecordsSQL('PRAGMA table_info(samples)') as $column) {
            $types[$column['name']] = $column['type'];
        }

        self::assertSame(
            ['id' => 'INTEGER', 'count' => 'INTEGER', 'ratio' => 'REAL', 'flag' => 'INTEGER', 'phone' => 'TEXT', 'seen' => 'INTEGER'],
            $types
        );

        // A TEXT column keeps a phone number's leading zero, which a numeric one would drop.
        $probe->database()->insertRecord('samples', ['phone' => '0991234567']);
        self::assertSame('0991234567', $probe->database()->getRecord('samples', [])['phone']);

        $probe->finish();
    }

    /**
     * Why the types are worth getting right: a TEXT column compares as text, so '9' > '50', and a
     * time window over timestamps stored as text matches the wrong rows.
     */
    public function testATextColumnComparesAsText(): void
    {
        $probe = $this->probe();
        $probe->start();

        $probe->createTableFromRow('as_text', ['authtime' => null]);
        $probe->createTableFromRow('as_number', ['authtime' => 0]);
        foreach (['as_text', 'as_number'] as $table) {
            $probe->database()->insertRecord($table, ['authtime' => '9']);
            $probe->database()->insertRecord($table, ['authtime' => '10']);
        }

        $count = function (string $table) use ($probe): int {
            return (int) $probe->database()->getResultSQL("SELECT COUNT(*) FROM {$table} WHERE authtime > 50");
        };

        self::assertSame(1, $count('as_text'));
        self::assertSame(0, $count('as_number'));

        $probe->finish();
    }

    public function testReadsTheRowFromAProtectedMapperMethod(): void
    {
        $mapper = new class {
            protected function _prepareData($entity): array
            {
                return ['id' => $entity->id, 'title' => $entity->title];
            }
        };
        $entity = (object) ['id' => 7, 'title' => 'Seven'];

        self::assertSame(['id' => 7, 'title' => 'Seven'], $this->probe()->rowFromMapper($mapper, '_prepareData', $entity));
    }

    public function testRegistersTheConnectionForEachTestAndRemovesItAfter(): void
    {
        $probe = $this->probe();

        $probe->start();
        $first = $probe->database();
        self::assertSame([$first], $probe->registered);

        $probe->finish();
        self::assertSame([], $probe->registered);

        $probe->start();
        self::assertNotSame($first, $probe->database(), 'each test gets a new connection');
        $probe->finish();
    }

    private function probe()
    {
        return new class ('probe') extends DatabaseTestCase {
            /** @var DatabaseSQL[] */
            public $registered = [];

            public function start(): void
            {
                $this->setUp();
            }

            public function finish(): void
            {
                $this->tearDown();
            }

            public function database(): DatabaseSQL
            {
                return $this->database;
            }

            public function createTableFromRow(string $table, array $row, array $types = []): void
            {
                parent::createTableFromRow($table, $row, $types);
            }

            public function rowFromMapper($mapper, string $method, $entity): array
            {
                return parent::rowFromMapper($mapper, $method, $entity);
            }

            protected function registerDatabase(DatabaseSQL $database): void
            {
                $this->registered[] = $database;
            }

            protected function unregisterDatabase(): void
            {
                $this->registered = [];
            }
        };
    }
}
