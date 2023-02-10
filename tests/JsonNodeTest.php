<?php

namespace alcamo\json;

use alcamo\exception\{DataValidationFailed, Recursion};
use alcamo\uri\FileUriFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'FooDocument.php';

/*
 * JsonNode::resolveUri() is implicitely tested in ReferenceResolverTest.php
 */
class JsonNodeTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    public static function checkStructure(JsonDocument $doc): void
    {
        foreach (
            new RecursiveWalker(
                $doc->getRoot(),
                RecursiveWalker::JSON_OBJECTS_ONLY
            ) as $pair
        ) {
            [ $jsonPtr, $node] = $pair;

            if ($node->getOwnerDocument() !== $doc) {
                /** @throw alcamo::exception::DataValidationFailed if node
                 *  has a wrong owner document. */
                throw (new DataValidationFailed())->setMessageContext(
                    [
                        'inData' => $node,
                        'atUri' => $node->getUri(),
                        'extraMessage' => "\$ownerDocument_ differs from document owning this node"
                    ]
                );
            }

            if ($node->getJsonPtr() != $jsonPtr) {
                /** @throw alcamo::exception::DataValidationFailed if node
                 *  has a wrong JSON pointer. */
                throw (new DataValidationFailed())->setMessageContext(
                    [
                        'inData' => $node,
                        'atUri' => $node->getUri(),
                        'extraMessage' =>
                        "\$jsonPtr_=\"{$node->getJsonPtr()}\" differs from actual position \"$jsonPtr\""
                    ]
                );
            }

            if ($node instanceof JsonNode) {
                $parentJsonPtr = $jsonPtr->getParent();

                if (isset($parentJsonPtr)) {
                    $parent = $doc->getNode($parentJsonPtr);

                    if ($parent instanceof JsonNode) {
                        if ($node->getParent() !== $parent) {
                            /** @throw alcamo::exception::DataValidationFailed
                             *  if a node's parent is not the parent node it
                             *  should be */
                            throw (new DataValidationFailed())->setMessageContext(
                                [
                                    'inData' => $node,
                                    'atUri' => $node->getUri(),
                                    'extraMessage' =>
                                    "\$parent_="
                                    . ($node->getParent()
                                     ? "\"{$node->getParent()->getJsonPtr()}\""
                                     : "null")
                                    . " differs from correct parent at $parentJsonPtr"
                                ]
                            );
                        }
                    } else {
                        if ($node->getParent() !== null) {
                            /** @throw alcamo::exception::DataValidationFailed
                             *  if a node's parent is not null while it should
                             *  be null because the parent is not a JSON
                             *  object */
                            throw (new DataValidationFailed())->setMessageContext(
                                [
                                    'inData' => $node,
                                    'atUri' => $node->getUri(),
                                    'extraMessage' =>
                                    "\$parent_ is not null when parent is not a JSON object"
                                ]
                            );
                        }
                    }
                } else {
                    if ($node->getParent() !== null) {
                        /** @throw alcamo::exception::DataValidationFailed if
                         *  a node's parent is not null while it should be
                         *  null because the current node os the root node */
                        throw (new DataValidationFailed())->setMessageContext(
                            [
                                'inData' => $node,
                                'atUri' => $node->getUri(),
                                'extraMessage' =>
                                "\$parent_ of root node is not null"
                            ]
                        );
                    }
                }
            }
        }
    }

    /**
     * @dataProvider getJsonPtrProvider
     */
    public function testGetJsonPtr($node, $expectedJsonPtr, $expectedKey)
    {
        $expectedBaseUri =
            (string)(new FileUriFactory())->create(self::FOO_FILENAME);

        $this->assertSame(
            $expectedBaseUri,
            (string)$node->getOwnerDocument()->getBaseUri()
        );

        $this->assertSame($expectedJsonPtr, (string)$node->getJsonPtr());

        $this->assertEquals(
            "$expectedBaseUri#$expectedJsonPtr",
            $node->getUri()
        );

        $this->assertEquals(
            $expectedJsonPtr == '/'
            ? "$expectedBaseUri#/foo"
            : "$expectedBaseUri#$expectedJsonPtr/foo",
            $node->getUri('foo')
        );
    }

    public function getJsonPtrProvider()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::FOO_FILENAME)
        );

        self::checkStructure($jsonDoc);

        $qux = $jsonDoc->getRoot()->bar->baz->qux;

        return [
            [ $jsonDoc->getRoot(), '/', null ],
            [ $jsonDoc->getRoot()->foo, '/foo', 'foo' ],
            [ $jsonDoc->getRoot()->foo->{'/'}, '/foo/~1', '~1' ],
            [ $jsonDoc->getRoot()->foo->{'~~'}, '/foo/~0~0', '~0~0' ],
            [ $jsonDoc->getRoot()->bar, '/bar', 'bar' ],
            [ $jsonDoc->getRoot()->bar->baz, '/bar/baz', 'baz' ],
            [ $qux[5], '/bar/baz/qux/5', '5' ],
            [ $qux[5]->FOO, '/bar/baz/qux/5/FOO', 'FOO' ],
            [ $qux[5]->FOO->BAZ, '/bar/baz/qux/5/FOO/BAZ', 'BAZ' ],
            [ $qux[6][0][2]->QUUX, '/bar/baz/qux/6/0/2/QUUX', 'QUUX' ],
            [ $qux[6][1][1], '/bar/baz/qux/6/1/1', '1' ]
        ];
    }

    public function testWithoutBaseUrl()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc =
            $factory->createFromJsonText(file_get_contents(self::FOO_FILENAME));

        $this->assertEquals('#/', $jsonDoc->getRoot()->getUri());

        $this->assertEquals(
            '#/bar/baz/qux',
            $jsonDoc->getRoot()->bar->baz->getUri('qux')
        );
    }

    public function testCreateDeepCopy()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::FOO_FILENAME)
        );

        $bar2 = $jsonDoc->getRoot()->bar->createDeepCopy();

        $this->assertEquals(json_encode($jsonDoc->getRoot()->bar), json_encode($bar2));
        $this->assertNotSame($jsonDoc->getRoot()->bar, $bar2);

        $this->assertEquals(
            json_encode($jsonDoc->getRoot()->bar->baz->qux[5]),
            json_encode($bar2->baz->qux[5])
        );
        $this->assertNotSame($jsonDoc->getRoot()->bar->baz->qux[5], $bar2->baz->qux[5]);

        $bar2->baz->qux[0] = 2;
        $this->assertSame(2, $bar2->baz->qux[0]);
        $this->assertSame(1, $jsonDoc->getRoot()->bar->baz->qux[0]);

        $bar2->baz->corge = 'Lorem ipsum';
        $this->assertSame('Lorem ipsum', $bar2->baz->corge);
        $this->assertFalse(isset($jsonDoc->getRoot()->bar->baz->corge));

        $jsonDoc2 = clone $jsonDoc;

        self::checkStructure($jsonDoc2);

        $this->assertEquals(
            json_encode($jsonDoc->getRoot()->bar),
            json_encode($jsonDoc2->getRoot()->bar)
        );
        $this->assertNotSame(
            $jsonDoc->getRoot()->bar,
            $jsonDoc2->getRoot()->bar
        );

        $this->assertNotSame(
            $jsonDoc->getRoot()->bar->baz->qux[5],
            $jsonDoc2->getRoot()->bar->baz->qux[5]
        );

        $this->assertNotSame(
            $jsonDoc->getRoot()->bar->baz->qux[6][0][2],
            $jsonDoc2->getRoot()->bar->baz->qux[6][0][2]
        );
    }

    public function testImportObjectNode()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::FOO_FILENAME)
        );

        $jsonDoc2 = $factory->createFromUrl(
            (new FileUriFactory())->create(self::FOO_FILENAME)
        );

        $jsonDoc->getRoot()->bar->foo = $jsonDoc->getRoot()->importObjectNode(
            $jsonDoc2->getRoot()->foo,
            JsonPtr::newFromString('/bar/foo'),
            null,
            $jsonDoc->getRoot()->bar
        );

        self::checkStructure($jsonDoc);

        $jsonDoc->getRoot()->bar->baz->qux[2] =
            $jsonDoc->getRoot()->importObjectNode(
                $jsonDoc2->getRoot()->foo->{'~~'},
                JsonPtr::newFromString('/bar/baz/qux/2'),
                JsonNode::COPY_UPON_IMPORT
            );

        self::checkStructure($jsonDoc);

        $this->assertSame((string)$jsonDoc->getRoot()->foo, (string)$jsonDoc->getRoot()->bar->foo);

        $this->assertSame(
            (string)$jsonDoc->getRoot()->bar->baz->qux[2],
            (string)$jsonDoc2->getRoot()->foo->{'~~'}
        );
    }

    public function testImportArrayNode()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::FOO_FILENAME)
        );

        $jsonDoc2 = clone $jsonDoc;

        $this->assertNotSame(
            $jsonDoc->getRoot()->bar->baz->qux[6][0][2],
            $jsonDoc2->getRoot()->bar->baz->qux[6][0][2]
        );

        $jsonDoc->getRoot()->foo = $jsonDoc->getRoot()->importArrayNode(
            $jsonDoc2->getRoot()->bar->baz->qux[6],
            JsonPtr::newFromString('/foo')
        );

        $jsonDoc->getRoot()->foo[0][1] = $jsonDoc->getRoot()->importArrayNode(
            $jsonDoc2->getRoot()->bar->baz->qux[6][1],
            JsonPtr::newFromString('/foo/0/1'),
            JsonNode::COPY_UPON_IMPORT
        );

        self::checkStructure($jsonDoc);

        $this->assertSame(43, $jsonDoc->getRoot()->foo[0][0]);

        $this->assertSame(
            json_encode($jsonDoc->getRoot()->bar->baz->qux[6][1]),
            json_encode($jsonDoc->getRoot()->foo[0][1])
        );
    }

    public function testRebase(): void
    {
        $baseUri = (new FileUriFactory())->create(self::FOO_FILENAME);

        $factory = new FooDocumentFactory();

        $jsonDoc = $factory->createFromUrl($baseUri);

        // rebase due to change in base URI

        $jsonDoc2 = new FooDocument();

        $jsonDoc2->setRoot(new JsonNode((object)[], $jsonDoc2, new JsonPtr()));

        $node2a = $jsonDoc2->getRoot()->importObjectNode(
            $jsonDoc->getRoot()->createDeepCopy(),
            JsonPtr::newFromString('/test')
        );

        $this->assertSame((string)$baseUri, $node2a->oldBase);

        $this->assertSame((string)$baseUri, $node2a->foo->{'/'}->oldBaseSlash);

        $this->assertSame(
            (string)$baseUri,
            $node2a->bar->baz->qux[6][0][2]->oldBaseQuux
        );

        $node2b = $jsonDoc2->getRoot()->importObjectNode(
            $jsonDoc->getRoot()->createDeepCopy(),
            JsonPtr::newFromString('/test'),
            JsonNode::COPY_UPON_IMPORT
        );

        $this->assertSame((string)$baseUri, $node2b->oldBaseOther);

        $this->assertSame((string)$baseUri, $node2b->foo->oldBaseOther);

        // no rebase because base URI does not change

        $jsonDoc3 = clone $jsonDoc;

        $node3a = $jsonDoc3->getRoot()->importObjectNode(
            $jsonDoc->getRoot()->createDeepCopy(),
            JsonPtr::newFromString('/test')
        );

        $this->assertFalse(isset($node3a->oldBase));

        $this->assertFalse(isset($node3a->foo->{'/'}->oldBaseSlash));

        $this->assertFalse(isset($node3a->bar->baz->qux[6][0][2]->oldBaseQuux));

        $node3b = $jsonDoc3->getRoot()->importObjectNode(
            $jsonDoc->getRoot()->createDeepCopy(),
            JsonPtr::newFromString('/test'),
            JsonNode::COPY_UPON_IMPORT
        );

        $this->assertFalse(isset($node3a->oldBaseOther));

        $this->assertFalse(isset($node3a->foo->oldBaseOther));
    }
}
