<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;
use alcamo\uri\FileUriFactory;
use PHPUnit\Framework\TestCase;

class JsonDocumentFactoryTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    public function testCreateFromJsonText(): void
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromJsonText(
            '[ null, true, "Lorem ipsum", 42 ]'
        );

        $this->assertSame(
            [ null, true, "Lorem ipsum", 42 ],
            $jsonDoc->getRoot()
        );
    }

    public function testCreateFromUrl(): void
    {
        $factory = new JsonDocumentFactory();

        $this->assertSame(JsonDocument::class, $factory->getClass());

        $jsonDoc = $factory->createFromUrl(self::FOO_FILENAME);

        $this->assertInstanceOf(JsonDocument::class, $jsonDoc);

        $this->assertSame(17, $jsonDoc->getRoot()->foo->{'~~'}->{'/~'}[5]);

        $jsonDoc2Node = $factory->createFromUrl(
            (new FileUriFactory())->create(self::FOO_FILENAME)
                ->withFragment('/foo/~1')
        );

        $this->assertInstanceOf(JsonNode::class, $jsonDoc2Node);

        $this->assertSame(42, $jsonDoc2Node->{'~/'});

        $factory2 = new JsonDocumentFactory(null, null, JSON_BIGINT_AS_STRING);

        $bigint = PHP_INT_MAX . PHP_INT_MAX;

        $jsonDoc3 = $factory2->createFromJsonText("{\"foo\": $bigint}");

        $this->assertSame($bigint, $jsonDoc3->getRoot()->foo);
    }

    public function testException(): void
    {
        $factory = new JsonDocumentFactory();

        $this->expectException(SyntaxError::class);

        $factory->createFromJsonText('{ "foo": 42, }');
    }
}
