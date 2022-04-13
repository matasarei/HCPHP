<?php

use core\Command;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Factory\DynamicRepositoryFactory;

class RelationsAddCommand extends Command
{
    public function run(): int
    {
        /** @var DynamicRepositoryFactory $factory */
        $factory = $this->container->get('factory_dynamic_repository');
        $repository = $factory->getRepository('relation_table');

        $entity = new DynamicEntity();
        $entity->set('text', $this->getArgument('value'));

        $repository->save($entity);

        return 0;
    }

    protected function parseArguments(array $args)
    {
        if (!isset($args[0])) {
            throw new InvalidArgumentException('Missing required arguments, see --help.');
        }

        $this
            ->setArgument('value', $args[0])
        ;
    }

    protected function getHelp(): string
    {
        return implode(
            PHP_EOL,
            [
                'Adds a new record to the relations demo table: ',
                'run relations:add "value"',
            ]
        );
    }
}
