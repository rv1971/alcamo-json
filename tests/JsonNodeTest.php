<?php

namespace alcamo\json;

use PHPUnit\Framework\TestCase;

class JsonNodeTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';
    public const BAR_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'bar.json';
    public const BAZ_FILENAME = __DIR__ . DIRECTORY_SEPARATOR
        . 'foo' . DIRECTORY_SEPARATOR . 'baz.json';
    public const QUX_FILENAME = __DIR__ . DIRECTORY_SEPARATOR
        . 'foo' . DIRECTORY_SEPARATOR
        . 'qux' . DIRECTORY_SEPARATOR
        . 'qux.json';
    public const QUUX_FILENAME = __DIR__ . DIRECTORY_SEPARATOR
        . 'foo' . DIRECTORY_SEPARATOR
        . 'quux' . DIRECTORY_SEPARATOR
        . 'quux.json';

    public static function checkStructure(JsonDocument $doc): void
    {
        foreach (
            new RecursiveWalker(
                $doc,
                RecursiveWalker::JSON_OBJECTS_ONLY
            ) as $jsonPtr => $node
        ) {
            if ($node->getOwnerDocument() !== $doc) {
                /** @throw alcamo::exception::DataValidationFailed if node
                 *  has a wrong owner document. */
                throw new DataValidationFailed(
                    $node,
                    "{$doc->getBaseUri()}#$jsonPtr",
                    null,
                    "; \$ownerDocument_ differs from document owning this node"
                );
            }

            if ($node->getJsonPtr() !== $jsonPtr) {
                /** @throw alcamo::exception::DataValidationFailed if node
                 *  has a wrong JSOn pointer. */
                throw new DataValidationFailed(
                    $node,
                    "{$doc->getBaseUri()}#$jsonPtr",
                    null,
                    "; \$jsonPtr_=\"{$node->getJsonPtr()}\" differs from actual position \"$jsonPtr\""
                );
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

        $this->assertSame($expectedBaseUri, (string)$node->getBaseUri());

        $this->assertSame($expectedJsonPtr, $node->getJsonPtr());
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

    public function testClone()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $bar2 = $jsonDoc->bar->createDeepCopy();

        $this->assertEquals($jsonDoc->bar, $bar2);
        $this->assertNotSame($jsonDoc->bar, $bar2);

        $this->assertEquals($jsonDoc->bar->baz->qux[5], $bar2->baz->qux[5]);
        $this->assertNotSame($jsonDoc->bar->baz->qux[5], $bar2->baz->qux[5]);

        $bar2->baz->qux[0] = 2;
        $this->assertSame(2, $bar2->baz->qux[0]);
        $this->assertSame(1, $jsonDoc->bar->baz->qux[0]);

        $bar2->baz->corge = 'Lorem ipsum';
        $this->assertSame('Lorem ipsum', $bar2->baz->corge);
        $this->assertFalse(isset($jsonDoc->bar->baz->corge));

        $jsonDoc2 = $jsonDoc->createDeepCopy();

        self::checkStructure($jsonDoc2);

        $this->assertEquals($jsonDoc->bar, $jsonDoc2->bar);
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

        $jsonDoc->bar->foo =
            $jsonDoc->importObjectNode($jsonDoc2->foo, '/bar/foo');

        $jsonDoc->bar->baz->qux[2] =
            $jsonDoc->importObjectNode(
                $jsonDoc2->foo->{'~~'},
                '/bar/baz/qux/2',
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
            '/foo'
        );

        $jsonDoc->foo[0][1] = $jsonDoc->importArrayNode(
            $jsonDoc2->bar->baz->qux[6][1],
            '/foo/0/1',
            JsonNode::COPY_UPON_IMPORT
        );

        self::checkStructure($jsonDoc);

        $this->assertSame(43, $jsonDoc->foo[0][0]);

        $this->assertSame(
            json_encode($jsonDoc->bar->baz->qux[6][1]),
            json_encode($jsonDoc->foo[0][1])
        );
    }

    public function testResolveInternal()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::BAR_FILENAME)
        );

        self::checkStructure($jsonDoc);

        $jsonDoc2 = $jsonDoc->createDeepCopy();

        self::checkStructure($jsonDoc2);

        $this->assertEquals($jsonDoc, $jsonDoc2);

        // does nothing, bar.json has no external references
        $jsonDoc2 = $jsonDoc2->resolveReferences(JsonNode::RESOLVE_EXTERNAL);

        self::checkStructure($jsonDoc2);

        $this->assertEquals($jsonDoc, $jsonDoc2);

        $jsonDoc2 = $jsonDoc2->resolveReferences();

        self::checkStructure($jsonDoc2);

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        // check that all references have been replaced.
        $this->assertSame(false, strpos($jsonDoc2, '$ref'));

        $this->assertSame('Lorem ipsum', $jsonDoc2->bar->foo);

        $this->assertSame(42, $jsonDoc2->bar->bar[0]);

        $this->assertSame(
            (string)$jsonDoc2->defs->baz,
            (string)$jsonDoc2->bar->bar[1]
        );

        $this->assertSame(true, $jsonDoc2->bar->bar[1]->qux2);

        $this->assertSame($jsonDoc2->defs->qux, $jsonDoc2->bar->bar[2]);

        $this->assertSame(true, $jsonDoc2->defs->baz->qux2);

        $this->assertSame(
            [ "Lorem", "ipsum", true, 43, false, null ],
            $jsonDoc2->bar->bar[2]
        );

        $this->assertSame(null, $jsonDoc2->bar->bar[3]);

        // replace refs only in part of the document

        $jsonDoc3 = $jsonDoc->createDeepCopy();

        $jsonDoc3->bar->bar[3]->resolveReferences();

        $this->assertSame(null, $jsonDoc3->bar->bar[3]);

        $this->assertSame('#/defs/qux/5', $jsonDoc3->defs->quux->{'$ref'});
    }

    // replace a document node by another document node
    public function testResolveExternal1()
    {
        $barUri = 'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::BAR_FILENAME);

        $bazUri = 'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::BAZ_FILENAME);

        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl($bazUri);

        self::checkStructure($jsonDoc);

        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $this->assertEquals($jsonDoc, $jsonDoc2);

        $jsonDoc2 = $jsonDoc2->resolveReferences(JsonNode::RESOLVE_INTERNAL);

        self::checkStructure($jsonDoc2);

        $this->assertEquals($jsonDoc, $jsonDoc2);

        $jsonDoc2 = $jsonDoc2->resolveReferences(JsonNode::RESOLVE_EXTERNAL);

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        $this->assertSame('#/defs/foo', $jsonDoc2->bar->foo->{'$ref'});

        $this->assertSame($barUri, (string)$jsonDoc2->getBaseUri());

        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $jsonDoc2 = $jsonDoc2->resolveReferences();

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        // check that all references have been replaced
        $this->assertSame(false, strpos($jsonDoc2, '$ref'));

        $this->assertSame('Lorem ipsum', $jsonDoc2->bar->foo);
    }

    // other internal/external replacements
    public function testResolveExternal2()
    {
        $barUri = 'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::BAR_FILENAME);

        $quxUri = 'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::QUX_FILENAME);

        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl($quxUri);

        self::checkStructure($jsonDoc);

        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $jsonDoc2 = $jsonDoc2->resolveReferences();

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        // check that all references have been replaced
        $this->assertSame(false, strpos($jsonDoc2, '$ref'));

        // replace node by external node, which has been resolved internally
        $this->assertSame('Lorem ipsum', $jsonDoc2->foo);

        // replace node by external node, which has been resolved internally
        // to an array
        $this->assertSame(42, $jsonDoc2->bar[0]);
        $this->assertSame(true, $jsonDoc2->bar[1]->qux2);

        // replacement via multiple files
        $this->assertSame(null, $jsonDoc2->quux);

        $this->assertSame($jsonDoc->getBaseUri(), $jsonDoc2->getBaseUri());

        $this->assertSame($barUri, (string)$jsonDoc2->bar[1]->getBaseUri());
    }
}
