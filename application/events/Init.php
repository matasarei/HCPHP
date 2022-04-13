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
use UserBundle\Service\Authenticator;

final class Init extends Handler
{
    private $container;

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

    private function initDynamicDB(DatabaseSQL $database)
    {
        (new DatabaseManager($database))->initialize();

        $tableRepository = new TableRepository();
        $this->container->set('repository_table', $tableRepository);
        $this->container->set(
            'factory_dynamic_repository',
            new DynamicRepositoryFactory($database, $tableRepository)
        );
    }

    private function initUserBundle(DatabaseInterface $database)
    {
        $roleRepository = new RoleRepository(new RoleMapper());
        $this->container->set('repository_role', $roleRepository);

        $userRepository = new UserRepository($database, new UserMapper($roleRepository));
        $this->container->set('repository_user', $userRepository);

        $userManager = new UserManager($userRepository, $roleRepository);
        $this->container->set('user_manager', $userManager);

        $this->container->set('authenticator', new Authenticator($userRepository));
        $this->container->set('view_factory', new ViewFactory($userRepository));
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
