<?php

namespace core;

use InvalidArgumentException;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class View extends Template
{
    protected $layout;

    public function __construct(string $view = null, $layout = 'default')
    {
        try {
            if ($view === null) {
                $view = Application::getControllerName() . '/' . Application::getActionName();
            }

            $path = new Path(sprintf('application/views/%s.php', $view), true);
            $this->path = $path;
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(sprintf('View "%s" does not exist!', $view));
        }

        $this->layout = new Template($layout);
        $this->template = $view;
    }

    public function getLayout(): Template
    {
        return $this->layout;
    }

    public function setLayout(Template $layout)
    {
        $this->layout = new Template($layout);
    }

    public function make(array $data = null)
    {
        $this->layout->set('content', parent::make($data));

        return $this->layout->make();
    }
}
