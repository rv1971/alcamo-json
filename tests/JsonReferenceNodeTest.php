<?php

namespace alcamo\json;

use alcamo\uri\{FileUriFactory, Uri};
use PHPUnit\Framework\TestCase;

class JsonReferenceNodeTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    public const BAR_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'bar.json';

    public function getFooDoc(): JsonDocument
    {
        static $factory;

        if (!isset($factory)) {
            $factory = new JsonDocumentFactory();
        }

        $fooDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::FOO_FILENAME)
        );

        return $fooDoc;
    }

    /**
     * @dataProvider newFromUriProvider
     */
    public function testNewFromUri(
        $uri,
        $expectedIsExternal,
        $expectedTarget
    ): void {
        $fooDoc = $this->getFooDoc();

        $jsonPtr1 = JsonPtr::newFromString('/ref1');

        $refNode1 = JsonReferenceNode::newFromUri($uri, $fooDoc, $jsonPtr1);

        $this->assertSame((string)$uri, $refNode1->{'$ref'});

        $this->assertSame($fooDoc, $refNode1->getOwnerDocument());

        $this->assertSame($jsonPtr1, $refNode1->getJsonPtr());

        $this->assertNull($refNode1->getParent());

        $this->assertSame($expectedIsExternal, $refNode1->isExternal());

        if ($expectedTarget instanceof JsonDocument) {
            $this->assertSame(
                (string)$expectedTarget->getRoot(),
                (string)$refNode1->getTarget()->getRoot()
            );
        } else {
            $this->assertSame(
                (string)$expectedTarget,
                (string)$refNode1->getTarget()
            );
        }

        $jsonPtr2 = JsonPtr::newFromString('/foo/ref2');

        $refNode2 = JsonReferenceNode::newFromUri(
            $uri,
            $fooDoc,
            $jsonPtr2,
            $fooDoc->getRoot()->foo
        );

        $this->assertSame((string)$uri, $refNode2->{'$ref'});

        $this->assertSame($fooDoc, $refNode2->getOwnerDocument());

        $this->assertSame($jsonPtr2, $refNode2->getJsonPtr());

        $this->assertSame($fooDoc->getRoot()->foo, $refNode2->getParent());

        $this->assertSame($expectedIsExternal, $refNode2->isExternal());

        if ($expectedTarget instanceof JsonDocument) {
            $this->assertSame(
                (string)$expectedTarget->getRoot(),
                (string)$refNode2->getTarget()->getRoot()
            );
        } else {
            $this->assertSame(
                (string)$expectedTarget,
                (string)$refNode2->getTarget()
            );
        }
    }

    public function newFromUriProvider(): array
    {
        $factory = new JsonDocumentFactory();

        $fooDoc = $this->getFooDoc();

        $barUri = (new FileUriFactory())->create(self::BAR_FILENAME);

        $barDoc = $factory->createFromUrl($barUri);

        return [
            [
                '#/foo/~1',
                false,
                $fooDoc->getRoot()->foo->{'/'}
            ],
            [
                $barUri,
                true,
                $barDoc
            ],
            [
                "$barUri#/defs",
                true,
                $barDoc->getRoot()->defs
            ],
            [
                "$barUri#/defs/foo",
                true,
                'Lorem ipsum'
            ]
        ];
    }

    public function testRebase(): void
    {
        $doc = new JsonDocument(null, new Uri('data/old/'));

        $node = JsonReferenceNode::newFromUri('data.json', $doc, new JsonPtr());

        $doc2 = new JsonDocument(null, new Uri('http://example.biz'));

        $node2 = JsonNode::importObjectNode(
            $doc2,
            $node,
            new JsonPtr(),
            JsonNode::COPY_UPON_IMPORT
        );

        $this->assertSame(
            'data/old/data.json',
            $node2->{'$ref'}
        );

        $doc3 = new JsonDocument(null);

        $node3 = JsonNode::importObjectNode(
            $doc3,
            $node2,
            new JsonPtr(),
            JsonNode::COPY_UPON_IMPORT
        );

        $this->assertSame(
            'http://example.biz/data/old/data.json',
            $node3->{'$ref'}
        );

    }
}
