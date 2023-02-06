<?php

namespace alcamo\json;

use alcamo\exception\{DataValidationFailed, Recursion};
use PHPUnit\Framework\TestCase;

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
                $doc,
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
                        'atUri' => "{$doc->getBaseUri()}#$jsonPtr",
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
                        'atUri' => "{$doc->getBaseUri()}#$jsonPtr",
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
                                    'atUri' => "{$doc->getBaseUri()}#$jsonPtr",
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
                                    'atUri' => "{$doc->getBaseUri()}#$jsonPtr",
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
                                'atUri' => "{$doc->getBaseUri()}#$jsonPtr",
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
            'file://' . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME);

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
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        self::checkStructure($jsonDoc);

        $qux = $jsonDoc->bar->baz->qux;

        return [
            [ $jsonDoc, '/', null ],
            [ $jsonDoc->foo, '/foo', 'foo' ],
            [ $jsonDoc->foo->{'/'}, '/foo/~1', '~1' ],
            [ $jsonDoc->foo->{'~~'}, '/foo/~0~0', '~0~0' ],
            [ $jsonDoc->bar, '/bar', 'bar' ],
            [ $jsonDoc->bar->baz, '/bar/baz', 'baz' ],
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

        $this->assertEquals('#/', $jsonDoc->getUri());

        $this->assertEquals(
            '#/bar/baz/qux',
            $jsonDoc->bar->baz->getUri('qux')
        );
    }

    public function testCreateDeepCopy()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $bar2 = $jsonDoc->bar->createDeepCopy();

        $this->assertEquals(json_encode($jsonDoc->bar), json_encode($bar2));
        $this->assertNotSame($jsonDoc->bar, $bar2);

        $this->assertEquals(
            json_encode($jsonDoc->bar->baz->qux[5]),
            json_encode($bar2->baz->qux[5])
        );
        $this->assertNotSame($jsonDoc->bar->baz->qux[5], $bar2->baz->qux[5]);

        $bar2->baz->qux[0] = 2;
        $this->assertSame(2, $bar2->baz->qux[0]);
        $this->assertSame(1, $jsonDoc->bar->baz->qux[0]);

        $bar2->baz->corge = 'Lorem ipsum';
        $this->assertSame('Lorem ipsum', $bar2->baz->corge);
        $this->assertFalse(isset($jsonDoc->bar->baz->corge));

        $jsonDoc2 = $jsonDoc->createDeepCopy();

        self::checkStructure($jsonDoc2);

        $this->assertEquals(
            json_encode($jsonDoc->bar),
            json_encode($jsonDoc2->bar)
        );
        $this->assertNotSame($jsonDoc->bar, $jsonDoc2->bar);

        $this->assertNotSame(
            $jsonDoc->bar->baz->qux[5],
            $jsonDoc2->bar->baz->qux[5]
        );

        $this->assertNotSame(
            $jsonDoc->bar->baz->qux[6][0][2],
            $jsonDoc2->bar->baz->qux[6][0][2]
        );
    }

    public function testImportObjectNode()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $jsonDoc2 = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $jsonDoc->bar->foo = $jsonDoc->importObjectNode(
            $jsonDoc2->foo,
            JsonPtr::newFromString('/bar/foo'),
            null,
            $jsonDoc->bar
        );

        self::checkStructure($jsonDoc);

        $jsonDoc->bar->baz->qux[2] =
            $jsonDoc->importObjectNode(
                $jsonDoc2->foo->{'~~'},
                JsonPtr::newFromString('/bar/baz/qux/2'),
                JsonNode::COPY_UPON_IMPORT
            );

        self::checkStructure($jsonDoc);

        $this->assertSame((string)$jsonDoc->foo, (string)$jsonDoc->bar->foo);

        $this->assertSame(
            (string)$jsonDoc->bar->baz->qux[2],
            (string)$jsonDoc2->foo->{'~~'}
        );
    }

    public function testImportArrayNode()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $this->assertNotSame(
            $jsonDoc->bar->baz->qux[6][0][2],
            $jsonDoc2->bar->baz->qux[6][0][2]
        );

        $jsonDoc->foo = $jsonDoc->importArrayNode(
            $jsonDoc2->bar->baz->qux[6],
            JsonPtr::newFromString('/foo')
        );

        $jsonDoc->foo[0][1] = $jsonDoc->importArrayNode(
            $jsonDoc2->bar->baz->qux[6][1],
            JsonPtr::newFromString('/foo/0/1'),
            JsonNode::COPY_UPON_IMPORT
        );

        self::checkStructure($jsonDoc);

        $this->assertSame(43, $jsonDoc->foo[0][0]);

        $this->assertSame(
            json_encode($jsonDoc->bar->baz->qux[6][1]),
            json_encode($jsonDoc->foo[0][1])
        );
    }
}
