<?php

namespace alcamo\json;

use PHPUnit\Framework\TestCase;

class JsonNodeFactoryTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    public function testCreateFromUrl()
    {
        $factory = new JsonNodeFactory();

        $jsonDoc = $factory->createFromUrl(self::FOO_FILENAME);

        $this->assertInstanceOf(JsonDocument::class, $jsonDoc);

        $jsonDoc2 = $factory
            ->createFromUrl(self::FOO_FILENAME, null, null, JsonNode::class);

        $this->assertInstanceOf(JsonNode::class, $jsonDoc2);

        $this->assertNotInstanceOf(JsonDocument::class, $jsonDoc2);

        $factory2 = new JsonNodeFactory(null, JSON_BIGINT_AS_STRING);

        $bigint = PHP_INT_MAX . PHP_INT_MAX;

        $jsonDoc3 = $factory2->createFromJsonText("{\"foo\": $bigint}");

        $this->assertSame($bigint, $jsonDoc3->foo);
    }

    public function testException()
    {
        $factory = new JsonNodeFactory();

        $this->expectException(\JsonException::class);

        $factory->createFromJsonText('{ "foo": 42, }');
    }
}
