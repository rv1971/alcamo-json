<?php

namespace alcamo\json;

use alcamo\uri\FileUriFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'FooDocument.php';

/*
 * JsonNode::resolveUri() is implicitely tested in ReferenceResolverTest.php
 *
 * JsonNode::resolveReferences() is implicitely tested in JsonDocumentTest.php
 */
class JsonNodeTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

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
     * @dataProvider constructProvider
     */
    public function testConstruct(
        $data,
        $jsonPtr,
        $hasParent,
        $expectedString
    ): void {
        $doc = new JsonDocument(null);

        $parent = $hasParent
            ? new JsonNode((object)[], $doc, new JsonPtr())
            : null;

        $node = new JsonNode(
            (object)$data,
            $doc,
            JsonPtr::newFromString($jsonPtr),
            $parent
        );

        $this->assertSame($doc, $node->getOwnerDocument());

        $this->assertSame($jsonPtr, (string)$node->getJsonPtr());

        if ($hasParent) {
            $this->assertSame($parent, $node->getParent());
        } else {
            $this->assertNull($node->getParent());
        }

        foreach ($data as $key => $value) {
            switch (true) {
                case $value === null:
                    $this->assertNull($node->$key);
                    break;

                case is_object($value):
                    $this->assertInstanceOf(JsonNode::class, $node->$key);
                    break;

                default:
                    $this->assertSame($value, $node->$key);
            }
        }

        $this->assertSame($expectedString, (string)$node);

        $this->assertSame("#$jsonPtr", (string)$node->getUri());

        $this->assertSame(
            $jsonPtr != '/' ? "#$jsonPtr/corge" : "#/corge",
            (string)$node->getUri('corge')
        );

        $this->assertSame('foo', (string)$node->resolveUri('foo'));
    }

    public function constructProvider(): array
    {
        return [
            [ [], '/', false, '{}' ],
            [
                [
                    'foo' => null,
                    '1' => true,
                    'n' => 42,
                    's' => 'Lorem ipsum',
                    'o' => (object)[ 'baz' => 43 ]
                ],
                '/bar',
                true,
                '{"foo":null,"1":true,"n":42,"s":"Lorem ipsum","o":{"baz":43}}'
            ]
        ];
    }

    /**
     * @dataProvider getJsonPtrProvider
     */
    public function testGetJsonPtr($node, $expectedJsonPtr)
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
        $fooDoc = $this->getFooDoc();

        $fooDoc->checkStructure();

        $qux = $fooDoc->getRoot()->bar->baz->qux;

        return [
            [ $fooDoc->getRoot(), '/' ],
            [ $fooDoc->getRoot()->foo, '/foo' ],
            [ $fooDoc->getRoot()->foo->{'/'}, '/foo/~1' ],
            [ $fooDoc->getRoot()->foo->{'~~'}, '/foo/~0~0' ],
            [ $fooDoc->getRoot()->bar, '/bar' ],
            [ $fooDoc->getRoot()->bar->baz, '/bar/baz' ],
            [ $qux[5], '/bar/baz/qux/5' ],
            [ $qux[5]->FOO, '/bar/baz/qux/5/FOO' ],
            [ $qux[5]->FOO->BAZ, '/bar/baz/qux/5/FOO/BAZ' ],
            [ $qux[6][0][2]->QUUX, '/bar/baz/qux/6/0/2/QUUX' ],
            [ $qux[6][1][1], '/bar/baz/qux/6/1/1' ]
        ];
    }

    public function testClone()
    {
        $fooDoc = $this->getFooDoc();

        $bar = $fooDoc->getRoot()->bar;
        $bar2 = clone $bar;

        $this->assertSame((string)$bar, (string)$bar2);
        $this->assertSame(get_class($bar), get_class($bar2));
        $this->assertNotSame($bar, $bar2);
        $this->assertSame($bar->getOwnerDocument(), $bar2->getOwnerDocument());
        $this->assertSame($bar->getJsonPtr(), $bar2->getJsonPtr());
        $this->assertSame($bar->getParent(), $bar2->getParent());

        $this->assertSame(
            (string)$bar->baz->qux[5],
            (string)$bar2->baz->qux[5]
        );
        $this->assertSame(
            get_class($bar->baz->qux[5]),
            get_class($bar2->baz->qux[5])
        );
        $this->assertNotSame($bar->baz->qux[5], $bar2->baz->qux[5]);
        $this->assertSame(
            $bar->baz->qux[5]->getOwnerDocument(),
            $bar2->baz->qux[5]->getOwnerDocument()
        );
        $this->assertSame(
            $bar->baz->qux[5]->getJsonPtr(),
            $bar2->baz->qux[5]->getJsonPtr()
        );
        $this->assertNull($bar2->baz->qux[5]->getParent());

        $bar2->baz->qux[0] = 2;
        $this->assertSame(2, $bar2->baz->qux[0]);
        $this->assertSame(1, $bar->baz->qux[0]);

        $bar2->baz->corge = 'Lorem ipsum';
        $this->assertSame('Lorem ipsum', $bar2->baz->corge);
        $this->assertFalse(isset($bar->baz->corge));
    }

    public function testImportObjectNode()
    {
        $fooDoc = $this->getFooDoc();

        $fooDoc2 = $this->getFooDoc();

        $fooDoc->getRoot()->bar->baz->qux[2] =
            JsonNode::importObjectNode(
                $fooDoc,
                $fooDoc2->getRoot()->foo->{'~~'},
                JsonPtr::newFromString('/bar/baz/qux/2'),
                JsonNode::COPY_UPON_IMPORT
            );

        $fooDoc->checkStructure();

        $this->assertSame(
            (string)$fooDoc->getRoot()->foo->{'~~'},
            (string)$fooDoc->getRoot()->bar->baz->qux[2]
        );

        $this->assertNotSame(
            $fooDoc2->getRoot()->foo->{'~~'},
            $fooDoc->getRoot()->bar->baz->qux[2]
        );

        $this->assertNotSame(
            $fooDoc2->getRoot()->foo->{'~~'}->{'0'},
            $fooDoc->getRoot()->bar->baz->qux[2]->{'0'}
        );

        $fooDoc->getRoot()->bar->foo = JsonNode::importObjectNode(
            $fooDoc,
            $fooDoc2->getRoot()->foo,
            JsonPtr::newFromString('/bar/foo'),
            null,
            $fooDoc->getRoot()->bar
        );

        $this->assertSame(
            (string)$fooDoc->getRoot()->foo,
            (string)$fooDoc->getRoot()->bar->foo
        );

        $this->assertSame(
            $fooDoc2->getRoot()->foo->{'~~'},
            $fooDoc->getRoot()->bar->foo->{'~~'}
        );

        $this->assertSame(
            $fooDoc2->getRoot()->foo->{'~~'}->{'0'},
            $fooDoc->getRoot()->bar->foo->{'~~'}->{'0'}
        );
    }

    public function testImportArrayNode()
    {
        $fooDoc = $this->getFooDoc();

        $fooDoc2 = $this->getFooDoc();

        $fooDoc->getRoot()->foo = JsonNode::importArrayNode(
            $fooDoc,
            $fooDoc2->getRoot()->bar->baz->qux[6],
            JsonPtr::newFromString('/foo'),
            JsonNode::COPY_UPON_IMPORT
        );

        $fooDoc->checkStructure();

        $this->assertSame(43, $fooDoc->getRoot()->foo[0][0]);

        $this->assertNotSame(
            $fooDoc2->getRoot()->bar->baz->qux[6][0][2],
            $fooDoc->getRoot()->foo[0][2]
        );

        $fooDoc->getRoot()->foo[0][1] = JsonNode::importArrayNode(
            $fooDoc,
            $fooDoc2->getRoot()->bar->baz->qux[6][1],
            JsonPtr::newFromString('/foo/0/1')
        );

        $fooDoc->checkStructure();

        $this->assertSame(
            json_encode($fooDoc2->getRoot()->bar->baz->qux[6][1]),
            json_encode($fooDoc->getRoot()->foo[0][1])
        );

        $this->assertSame(
            $fooDoc2->getRoot()->bar->baz->qux[6][1][1],
            $fooDoc->getRoot()->foo[0][1][1]
        );
    }

    public function testRebase(): void
    {
        $baseUri = (new FileUriFactory())->create(self::FOO_FILENAME);

        $factory = new FooDocumentFactory();

        $fooDoc = $factory->createFromUrl($baseUri);

        // rebase due to change in base URI

        $fooDoc2 = new FooDocument((object)[]);

        $node2a = JsonNode::importObjectNode(
            $fooDoc2,
            clone $fooDoc->getRoot(),
            new JsonPtr()
        );

        $this->assertSame((string)$baseUri, $node2a->oldBase);

        $this->assertSame((string)$baseUri, $node2a->foo->{'/'}->oldBaseSlash);

        $this->assertSame(
            (string)$baseUri,
            $node2a->bar->baz->qux[6][0][2]->oldBaseQuux
        );

        $node2b = JsonNode::importObjectNode(
            $fooDoc2,
            clone $fooDoc->getRoot(),
            new JsonPtr(),
            JsonNode::COPY_UPON_IMPORT
        );

        $this->assertSame((string)$baseUri, $node2b->oldBase);

        $this->assertSame((string)$baseUri, $node2b->foo->{'/'}->oldBaseSlash);

        $this->assertSame(
            (string)$baseUri,
            $node2b->bar->baz->qux[6][0][2]->oldBaseQuux
        );

        // no rebase because base URI does not change

        $fooDoc3 = $factory->createFromUrl($baseUri);

        $node3a = JsonNode::importObjectNode(
            $fooDoc3,
            clone $fooDoc->getRoot(),
            new JsonPtr()
        );

        $this->assertFalse(isset($node3a->oldBase));

        $this->assertFalse(isset($node3a->foo->{'/'}->oldBaseSlash));

        $this->assertFalse(isset($node3a->bar->baz->qux[6][0][2]->oldBaseQuux));

        $node3b = JsonNode::importObjectNode(
            $fooDoc3,
            clone $fooDoc->getRoot(),
            new JsonPtr(),
            JsonNode::COPY_UPON_IMPORT
        );

        $this->assertFalse(isset($node3b->oldBase));

        $this->assertFalse(isset($node3b->foo->{'/'}->oldBaseSlash));

        $this->assertFalse(isset($node3b->bar->baz->qux[6][0][2]->oldBaseQuux));
    }
}
