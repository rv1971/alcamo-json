<?php

namespace alcamo\json;

use alcamo\uri\FileUriFactory;
use PHPUnit\Framework\TestCase;

class JsonReferenceNodeTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    public function testNewFromUri()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::FOO_FILENAME)
        );

        $refNode = JsonReferenceNode::newFromUri(
            'http:://www.example.com/foo.json',
            $jsonDoc,
            JsonPtr::newFromString('/quux')
        );

        $this->assertSame(
            'http:://www.example.com/foo.json',
            $refNode->{'$ref'}
        );

        $this->assertSame($jsonDoc, $refNode->getOwnerDocument());

        $this->assertSame('/quux', (string)$refNode->getJsonPtr());
    }
}
