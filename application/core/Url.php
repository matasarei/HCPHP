<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Url extends MagicObject
{
    const SCHEME_HTTPS = 'https';
    const SCHEME_HTTP = 'http';

    protected $params = [];

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $scheme = self::SCHEME_HTTP;

    /**
     * @var int
     */
    protected $port = 80;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string|null
     */
    protected $anchor;

    /**
     * @param Url|string $path
     * @param array $params
     * @param string|null $anchor
     */
    public function __construct($path = '', array $params = [], string $anchor = null)
    {
        // Get current path.
        if ($path === true) {
            // Current anchor is not available at server side.
            $request = Application::getCurrentPath(); // no anchor here.
            $path = ltrim(parse_url($request, PHP_URL_PATH), '/\\');
            $this->setParams($this->parseParams($request));
        } else {
            $path = ltrim($path, '/\\');
        }

        $url = filter_var($path, FILTER_VALIDATE_URL);

        if ($url) {
            $this->scheme = parse_url($url, PHP_URL_SCHEME);
            $this->host = parse_url($url, PHP_URL_HOST);
            $this->setPort(parse_url($url, PHP_URL_PORT));
            $this->setPath(parse_url($url, PHP_URL_PATH));
            $this->anchor = parse_url($url, PHP_URL_FRAGMENT);

        } else {
            $this->path = (string)$path;

            if (Application::isHttpsEnabled()) {
                $this->scheme = self::SCHEME_HTTPS;
            } else {
                $this->scheme = self::SCHEME_HTTP;
            }

            $this->port = Application::getPort();
            $this->host = Application::getHost();
        }

        if (empty($this->host)) {
            $this->host = 'localhost';
        }

        if (!empty($anchor)) {
            $this->setAnchor($anchor);
        }

        $this->setParams(array_merge($this->parseParams($url), $params));
    }

    public static function parseParams(string $url): array
    {
        $params_str = parse_url($url, PHP_URL_QUERY);
        $params = [];

        if ($params_str) {
            foreach (preg_split('@&(amp;)?@', $params_str, -1, null) AS $p_fragment) {
                $var = preg_split('@=@', $p_fragment, -1, null);
                $params[$var[0]] = empty($var[1]) ? null : $var[1];
            }
        }

        return $params;
    }

    public function make(): string
    {
        $url =  sprintf('%s://%s', $this->scheme, $this->host);

        if (!in_array($this->port, [80, 443], true)) {
            $url .= ":{$this->port}";
        }

        if (
            filter_input(INPUT_SERVER, 'HTTP_HOST') === $this->host
            && file_exists(new Path($this->path))
            && Application::isRewriteEnabled()
        ) {
            $url .= '/index.php';
            $this->params['q'] = $this->path;
        } else {
            $url .= '/' . $this->path;
        }

        if ($this->params) {
            $url = $url . '?' . http_build_query($this->params);
        }

        return $this->anchor ? $url . '#' . $this->anchor : $url;
    }

    public function setAnchor(string $val): self
    {
        $this->anchor = trim($val);

        return $this;
    }

    public function getAnchor(): ?string
    {
        return $this->anchor;
    }

    public function setPath(string $path): self
    {
        $this->path = preg_replace(
            [
                "/^\s*\/*(.*)\s*/",
                "/[\/]+/"
            ],
            [
                '$1',
                '/'
            ],
            $path
        );

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPort(int $val = 0): self
    {
        if (empty($val)) {
            $this->port = $this->scheme === self::SCHEME_HTTPS ? 443 : 80;

            return $this;
        }

        $this->port = $val;

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setScheme(string $val): self
    {
        $this->scheme = trim($val);

        return $this;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function setHostname(string $hostname): self
    {
        $this->host = trim($hostname);

        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setParams(array $params): self
    {
        $this->params = [];

        foreach ($params as $name => $val) {
            $this->addParam($name, $val);
        }

        return $this;
    }

    /**
     * Set or rewrite optional url param
     *
     * @param string $name Name
     * @param mixed $val Param value
     */
    public function addParam(string $name, $val): self
    {
        $this->params[urldecode($name)] = is_string($val) ? urldecode($val) : $val;

        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParam(string $name): ?string
    {
        if (key_exists($name, $this->params)) {
            return $this->params[$name];
        }

        return null;
    }

    public function removeParam(string $name): self
    {
        unset($this->params[$name]);

        return $this;
    }

    /**
     * @return false|int
     */
    public function isImage()
    {
        return preg_match('/.(png|jpeg|jpg|gif|bmp|webp|svg)$/i', $this->path);
    }

    public function getFileName(): ?string
    {
        if (preg_match("/([\w]+\.[a-z]+)$/Uui", $this->path, $matches, null)) {
            return array_shift($matches);
        }

        return null;
    }

    public function getExtension(): ?string
    {
        if (preg_match("/\.([a-z]+)$/i", $this->path, $matches, null)) {
            return array_shift($matches);
        }

        return null;
    }

    public function __toString()
    {
        return $this->make();
    }
}
