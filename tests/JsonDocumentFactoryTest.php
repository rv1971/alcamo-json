<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;
use PHPUnit\Framework\TestCase;

class JsonDocumentFactoryTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    public function testCreateFromUrl()
    {
        $factory = new JsonDocumentFactory();

        $this->assertSame(JsonDocument::class, $factory->getClass());

        $jsonDoc = $factory->createFromUrl(self::FOO_FILENAME);

        $this->assertInstanceOf(JsonDocument::class, $jsonDoc);

        $this->assertSame(17, $jsonDoc->getRoot()->foo->{'~~'}->{'/~'}[5]);

        $jsonDoc2 = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
            . '#/foo/~1'
        );

        $this->assertInstanceOf(JsonNode::class, $jsonDoc2);

        $this->assertNotInstanceOf(JsonDocument::class, $jsonDoc2);

        $this->assertSame(42, $jsonDoc2->{'~/'});

        $factory2 = new JsonDocumentFactory(null, null, JSON_BIGINT_AS_STRING);

        $bigint = PHP_INT_MAX . PHP_INT_MAX;

        $jsonDoc3 = $factory2->createFromJsonText("{\"foo\": $bigint}");

        $this->assertSame($bigint, $jsonDoc3->getRoot()->foo);
    }

    public function testException()
    {
        $factory = new JsonDocumentFactory();

        $this->expectException(SyntaxError::class);

        $factory->createFromJsonText('{ "foo": 42, }');
    }
}
