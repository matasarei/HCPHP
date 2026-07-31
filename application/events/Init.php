<?php

use core\Application;
use core\Config;
use core\DatabaseInterface;
use core\DatabaseSQL;
use core\Handler;
use core\Template;
use DynamicDB\Factory\DynamicRepositoryFactory;
use DynamicDB\Manager\DatabaseManager;
use DynamicDB\Repository\TableRepository;
use UserBundle\Manager\UserManager;
use UserBundle\Mapper\RoleMapper;
use UserBundle\Mapper\UserMapper;
use UserBundle\Repository\RoleRepository;
use UserBundle\Repository\UserRepository;
use UserBundle\Service\AuthChecker;
use UserBundle\Service\Authenticator;

final class Init extends Handler
{
    private $container;

    /**
     * Resolved once and reused by the two services that need it, so registering them stays
     * free even though reading it is not.
     *
     * @var int|null
     */
    private $loginTime;

    public function __construct($data = null)
    {
        $this->container = Application::getContainer();

        parent::__construct($data);
    }

    protected function handle($data)
    {
        $database = $this->initDatabases();

        $this->initDynamicDB($database);
        $this->initUserBundle($database);
        $this->extendTemplates();
    }

    private function initDatabases(): DatabaseInterface
    {
        $dbConfig = new Config('database', [
            'driver',
            'host',
            'dbname',
            'user',
            'pass' => '',
            'prefix' => '',
            'encoding',
        ]);

        $database = new DatabaseSQL(
            $dbConfig->get('driver'),
            $dbConfig->get('host'),
            $dbConfig->get('dbname'),
            $dbConfig->get('user'),
            $dbConfig->get('pass'),
            $dbConfig->get('prefix'),
            $dbConfig->get('encoding')
        );

        $this->container->set('database', $database);

        return $database;
    }

    /**
     * The services below are registered as factories rather than built here.
     *
     * Every request used to construct all of them, whichever route it was headed for:
     * TableRepository parses the DynamicDB config in its constructor, and a CLI command that
     * only wants the user manager was still handed a ViewFactory and an Authenticator. They
     * are plain constructions with no side effects, so deferring them changes nothing except
     * when the work happens.
     *
     * The database stays eager: DatabaseManager::initialize() below needs a live connection
     * during Init, so there is nothing to defer.
     */
    private function initDynamicDB(DatabaseSQL $database)
    {
        (new DatabaseManager($database))->initialize();

        $this->container->setFactory('repository_table', function () {
            return new TableRepository();
        });

        $this->container->setFactory('factory_dynamic_repository', function ($container) use ($database) {
            return new DynamicRepositoryFactory($database, $container->get('repository_table'));
        });
    }

    private function initUserBundle(DatabaseInterface $database)
    {
        $this->container->setFactory('repository_role', function () {
            return new RoleRepository(new RoleMapper());
        });

        $this->container->setFactory('repository_user', function ($container) use ($database) {
            return new UserRepository(
                $database,
                new UserMapper($container->get('repository_role'))
            );
        });

        $this->container->setFactory('user_manager', function ($container) {
            return new UserManager(
                $container->get('repository_user'),
                $container->get('repository_role')
            );
        });

        $this->container->setFactory('authenticator', function ($container) {
            return new Authenticator($container->get('repository_user'), $this->getLoginTime());
        });

        $this->container->setFactory('auth_checker', function ($container) {
            return new AuthChecker($container->get('repository_user'), $this->getLoginTime());
        });

        $this->container->setFactory('view_factory', function ($container) {
            return new ViewFactory($container->get('auth_checker'));
        });
    }

    /**
     * How long a login stays valid.
     *
     * This was configurable but nothing read it, so both services silently used their own
     * default and an auth key never actually expired.
     */
    private function getLoginTime(): int
    {
        if ($this->loginTime === null) {
            $config = new Config('default', ['loginTime' => Authenticator::DEFAULT_LOGIN_TIME]);
            $this->loginTime = (int)$config->get('loginTime');
        }

        return $this->loginTime;
    }

    private function extendTemplates()
    {
        /**
         * HTML filter
         * Syntax: {{html|content}}
         */
        Template::addShortcode('html', function(array $params, $info) {
            if (empty($params[1])) {
                return Template::replaceWithNotice($params, $info);
            }

            return "<?php echo (new Filter\HtmlFilter())->filter($params[1]) ?>";
        });
    }
}
