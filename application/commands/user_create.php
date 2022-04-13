<?php

use core\Command;
use UserBundle\Manager\UserManager;

class UserCreateCommand extends Command
{
    const ARGUMENT_ROLE_DEFAULT = 'user';

    public function run(): int
    {
        /** @var UserManager $userManager */
        $userManager = $this->container->get('user_manager');

        $userManager->createUser(
            $this->getArgument('email'),
            $this->getArgument('firstname'),
            $this->getArgument('password'),
            $this->getArgument('role')
        );

        return 0;
    }

    protected function parseArguments(array $args)
    {
        if (!isset($args[0], $args[1], $args[2])) {
            throw new InvalidArgumentException('Missing required arguments, see --help.');
        }

        $this
            ->setArgument('email', $args[0])
            ->setArgument('firstname', $args[1])
            ->setArgument('password', $args[2])
            ->setArgument('role', $args[3] ?? self::ARGUMENT_ROLE_DEFAULT)
        ;
    }

    protected function getHelp(): string
    {
        return implode(
            PHP_EOL,
            [
                'Adds a new user by provided info, example: ',
                'run user:create EMAIL FIRST_NAME PASSWORD [ROLE_NAME]',
            ]
        );
    }
}
