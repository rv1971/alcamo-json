<?php

namespace alcamo\json;

use PHPUnit\Framework\TestCase;

class JsonDocumentFactoryTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    public function testCreateFromUrl()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(self::FOO_FILENAME);

        $this->assertInstanceOf(JsonDocument::class, $jsonDoc);

        $this->assertSame(17, $jsonDoc->foo->{'~~'}->{'/~'}[5]);

        $jsonDoc2 = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
            . '#/foo/~1'
        );

        $this->assertInstanceOf(JsonNode::class, $jsonDoc2);

        $this->assertNotInstanceOf(JsonDocument::class, $jsonDoc2);

        $this->assertSame(42, $jsonDoc2->{'~/'});

        $factory2 = new JsonDocumentFactory(null, JSON_BIGINT_AS_STRING);

        $bigint = PHP_INT_MAX . PHP_INT_MAX;

        $jsonDoc3 = $factory2->createFromJsonText("{\"foo\": $bigint}");

        $this->assertSame($bigint, $jsonDoc3->foo);
    }

    public function testException()
    {
        $factory = new JsonDocumentFactory();

        $this->expectException(\JsonException::class);

        $factory->createFromJsonText('{ "foo": 42, }');
    }
}
