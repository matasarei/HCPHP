<?php

namespace core;

use InvalidArgumentException;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class Command
{
    const HELP_REQUEST = [
        '/?',
        '--help',
    ];

    protected $container;
    protected $arguments = [];

    public function __construct(Container $container, array $args = [])
    {
        $this->container = $container;

        if (in_array($args[0] ?? null, self::HELP_REQUEST)) {
            throw new InvalidArgumentException($this->getHelp());
        }

        $this->parseArguments($args);
    }

    /**
     * @param string $name
     * @param int|string $value
     *
     * @return self
     */
    public function setArgument(string $name, $value): self
    {
        $this->arguments[$name] = $value;

        return $this;
    }

    /**
     * @param string $name
     * @param int|string|null $default
     *
     * @return int|string|null
     */
    public function getArgument(string $name, $default = null)
    {
        return $this->arguments[$name] ?? $default;
    }

    abstract public function run(): int;

    /**
     * @param array $args
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    abstract protected function parseArguments(array $args);

    abstract protected function getHelp(): string;
}
