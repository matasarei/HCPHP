<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Response
{
    const STATUS_OK = 200;
    const STATUS_CREATED = 201;
    const STATUS_EMPTY = 204;
    const STATUS_BAD_REQUEST = 400;
    const STATUS_FORBIDDEN = 403;
    const STATUS_NOT_FOUND = 404;

    /**
     * @var string
     */
    private $content;

    /**
     * @var int
     */
    private $responseCode;

    public function __construct(string $content = null, int $responseCode = 200)
    {
        $this->content = $content;
        $this->responseCode = $responseCode;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setResponseCode(int $responseCode): self
    {
        $this->responseCode = $responseCode;

        return $this;
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }

    public function __toString()
    {
        return $this->getContent() ?? '';
    }
}
