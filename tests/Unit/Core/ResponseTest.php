<?php

namespace Tests\Unit\Core;

use core\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testDefaultsToAnEmptyOkResponse(): void
    {
        $response = new Response();

        self::assertNull($response->getContent());
        self::assertSame(Response::STATUS_OK, $response->getResponseCode());
    }

    public function testConstructorTakesContentAndCode(): void
    {
        $response = new Response('body', Response::STATUS_CREATED);

        self::assertSame('body', $response->getContent());
        self::assertSame(201, $response->getResponseCode());
    }

    public function testSettersAreFluent(): void
    {
        $response = new Response();

        self::assertSame($response, $response->setContent('x'));
        self::assertSame($response, $response->setResponseCode(Response::STATUS_NOT_FOUND));
        self::assertSame('x', $response->getContent());
        self::assertSame(404, $response->getResponseCode());
    }

    public function testContentCanBeClearedBackToNull(): void
    {
        $response = new Response('x');
        $response->setContent(null);

        self::assertNull($response->getContent());
    }

    /**
     * Responses are echoed directly, so a null body has to render as an empty string rather
     * than raise a deprecation on PHP 8.1+.
     */
    public function testCastingANullBodyGivesAnEmptyString(): void
    {
        self::assertSame('', (string)new Response());
    }

    public function testCastingGivesTheBody(): void
    {
        self::assertSame('body', (string)new Response('body'));
    }

    public function testStatusConstants(): void
    {
        self::assertSame(200, Response::STATUS_OK);
        self::assertSame(201, Response::STATUS_CREATED);
        self::assertSame(204, Response::STATUS_EMPTY);
        self::assertSame(400, Response::STATUS_BAD_REQUEST);
        self::assertSame(403, Response::STATUS_FORBIDDEN);
        self::assertSame(404, Response::STATUS_NOT_FOUND);
    }
}
