<?php

namespace DynamicDB\Validator\Exception;

use UnexpectedValueException;

/**
 * Thrown when an upload is not something this application is willing to store.
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class InvalidFileTypeException extends UnexpectedValueException
{
}
