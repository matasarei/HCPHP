<?php

namespace core;

use InvalidArgumentException;
use stdClass;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Template
{
    protected $data = [];
    protected $path;
    protected $template;
    protected $useShortCodes = true;
    private static $shortcodes = [];

    public function useShortCodes(bool $value)
    {
        $this->useShortCodes = $value;
    }

    public function isUsesShortCodes(): bool
    {
        return $this->useShortCodes;
    }

    public function getPath(): Path
    {
        return $this->path;
    }

    /**
     * Example: new Template('foo/bar') for 'templates/foo/bar.php'
     *
     * @param string $template Template name
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $template)
    {
        $this->path = new Path(sprintf('application/templates/%s.php', $template));

        if (!file_exists($this->path)) {
            throw new InvalidArgumentException(sprintf('Template "%s" does not exist!', $template));
        }

        $this->template = $template;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data assoc array, 'var'=>'value'
     *
     * @return self
     */
    public function setData(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return self
     */
    public function set(string $name, $value): self
    {
        $this->data[$name] = $value;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     */
    public function get(string $name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        return null;
    }

    public function __get(string $name)
    {
        return $this->get($name);
    }

    /**
     * Parse and return result
     *
     * @param mixed Include data
     */
    public function make(array $data = null)
    {
        $cache = new Path(sprintf('/cache/templates/%s.tmp', $this->template));

        if (!(is_readable($cache) && filemtime($cache) > filemtime($this->path))) {
            $contents = file_get_contents($this->path);
            $cache = new Path(sprintf('/cache/templates/%s.tmp', $this->template));
            $cache->mkpath();

            file_put_contents($cache, $this->parse($contents));
        }

        $this->path = $cache;

        if ($data !== null) {
            $this->setData($data);
        }

        extract($this->data, EXTR_OVERWRITE);
        ob_start();
        include ($this->path);

        return ob_get_clean();
    }

    private function parse(string $contents)
    {
        // remove comments.
        $contents = preg_replace('/\{\*.*\*\}/Us', '', $contents, -1);
        $matches = [];
        $lines = [];

        if ($this->useShortCodes) {
            // fetch all shortcodes.
            preg_match_all("/(\!)?{{\s*(.*)\s*}}/Uus", $contents, $matches, PREG_SET_ORDER, 0);

            // explode to lines.
            $lines = preg_split("/\n/", $contents, -1, null);
        }

        // process each shortcode.
        foreach ($matches as $match) {
            // ignore shortcode
            if ($match[1] === '!') {
                $pattern = '#' . preg_quote($match[0]) . '#';

                //replace in template
                $contents = preg_replace($pattern, sprintf('{{%s}}', $match[2]), $contents, -1);
                continue;
            }

            $pattern = '#' . preg_quote($match[0]) . '#';
            $info = new stdClass();
            $info->file = $this->path;

            $extracted = array_keys(preg_grep($pattern, $lines));
            $info->line = $extracted ? array_shift($extracted) + 1 : false;

            // replace slash(\) by 'end'.
            $match = preg_replace("/^\s*\/(.*)/", "end\$1", $match, -1);

            // get function name and params.
            $params = preg_split("@\|@s", $match[2], -1, null);

            // method name.
            $method = "parse{$params[0]}";

            // predefined shortcodes.
            if (preg_match('@(\$[A-Za-z0-9_]|^[A-Z][A-Z0-9_]{2,})@', $params[0])) {
                $replacement = $this->parseEcho([$params[0]], $info);

                // custom shortcodes (hooks).
            } elseif (isset(self::$shortcodes[$params[0]])) {
                $func = self::$shortcodes[$params[0]];
                $replacement = $func($params, $info);

            } elseif (method_exists($this, $method)) {
                $replacement = $this->$method($params, $info);

            } else {
                $replacement = $this->replaceWithNotice($params, $info);
            }

            // replace in template.
            $contents = preg_replace($pattern, $replacement, $contents, -1);
        }

        // do not compress template if debug enabled or any preformatted content exist.
        if (Debug::isOn() || preg_match('@<pre@', $contents)) {
            return $contents;
        }

        // remove php comments.
        $contents = preg_replace(['/(^\s*|;\s*)\/\/.*(\?>|[\n\r]+|\s*$)/Um', '/\/\*.*\*\//Us'], ['$1$2', ''], $contents, -1);

        return preg_replace("@\s+@s", " ", trim($contents), -1);
    }

    /**
     * @param array $params
     * @param object $info
     *
     * @return string
     */
    public static function replaceWithNotice(array $params, $info)
    {
        $msg = sprintf('Parse error: wrong syntax or shortcode does not defined in %s', $info->file);
        trigger_error($info->line ? sprintf('%s on line %s', $msg, $info->line) : $msg);
        
        if (Debug::isOn()) {
            return '{{' . implode('|', $params) . '}}';
        }

        return '%shortcode%';
    }

    /**
     * @param array $params
     * @param object $info
     *
     * @return string
     */
    public function parseEcho(array $params, $info): string
    {
        if (empty($params)) {
            return self::replaceWithNotice($params, $info);
        }

        return sprintf('<?php echo %s ?>', $params[0]);
    }

    /**
     * Template shortcode
     * syntax: {{template|name[|data_array]}}
     * examples:
     *  {{template|some_name}}
     *  {{template|'some_name'|['var' => 'value']}}
     *  {{template|'/path/to/template_name'}}
     *
     * @param array $params
     * @param object $info
     *
     * @return string
     */
    public function parseTemplate(array $params, $info): string
    {
        if (empty($params[1])) {
            return self::replaceWithNotice($params, $info);
        } else {
            $params[1] = preg_replace("@[\'\"](.*)[\'\"]@", '$1', $params[1], -1);
        }

        return sprintf(
            '<?php echo (new \core\Template("%s"))->make(%s); ?>',
            $params[1],
            $params[2] ?? 'null'
        );
    }

    /**
     * Language template shortcode
     * Syntax: {{lang|string_name[|args][|language_code]}}
     * Examples:
     *  {{lang|name|['some', 'args', 123, 123]}}
     *  {{lang|name||en}}
     *
     * @param array $params
     * @param object $info
     *
     * @return string
     */
    public function parseLang(array $params, $info): string
    {
        $quotesPattern = "@[\'\"](.*)[\'\"]@";

        if (empty($params[1])) {
            return self::replaceWithNotice($params, $info);
        }

        $return = "<?php echo \core\Language::getInstance()->getString({$params[1]}";

        if (empty($params[2])) {
            $return .= ', []';
        } else {
            $return .= ', ' . $params[2];
        }

        if (isset($params[3])) {
            $return .= ", '" . preg_replace($quotesPattern, "$1", $params[3], -1) . "'";
        }

        return $return . '); ?>';
    }

    /**
     * Adds hook in parser. Can override parser functions.
     *
     * @param string $name Hook name
     * @param callable $func function(string $params[, stdClass $info])
     */
    public static function addShortcode(string $name, callable $func)
    {
        self::$shortcodes[$name] = $func;
    }

    public function __toString()
    {
        return $this->make();
    }
}
