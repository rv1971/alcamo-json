<?php

namespace alcamo\json;

use PHPUnit\Framework\TestCase;

class JsonDocumentTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';
    public const BAR_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'bar.json';
    public const BAZ_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'baz.json';

    public function testConstruct()
    {
        $jsonText = file_get_contents(self::FOO_FILENAME);

        $jsonDoc = JsonDocument::newFromJsonText($jsonText);

        $this->assertSame(
            json_encode(json_decode($jsonText)),
            (string)$jsonDoc
        );

        $this->assertSame(
            $jsonDoc,
            $jsonDoc->getOwnerDocument()
        );

        $this->assertSame(
            $jsonDoc,
            $jsonDoc->foo->getOwnerDocument()
        );

        $this->assertSame(
            $jsonDoc,
            $jsonDoc->bar->baz->getOwnerDocument()
        );

        $this->assertNull($jsonDoc->getBaseUri());
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

        $this->assertSame($expectedKey, $node->getKey());
    }

    public function getJsonPtrProvider()
    {
        $jsonDoc = JsonDocument::newFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

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

    /**
     * @dataProvider getNodeProvider
     */
    public function testGetNode($jsonDoc, $jsonPtr, $expectedData)
    {
        $this->assertSame($expectedData, $jsonDoc->getNode($jsonPtr));
    }

    public function getNodeProvider()
    {
        $jsonDoc = JsonDocument::newFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        return [
            [ $jsonDoc, '/foo/~1/~0~1', 42 ],
            [ $jsonDoc, '/foo/~0~0/~1~0', [ 3, 5, 7, 11, 13, 17 ] ],
            [ $jsonDoc, '/foo/~0~0/~1~0/0', 3 ],
            [ $jsonDoc, '/foo/~0~0/~1~0/1', 5 ],
            [ $jsonDoc, '/foo/~0~0/~1~0//5', 17 ],
            [ $jsonDoc, '/bar/baz/qux/0', 1 ],
            [ $jsonDoc, '/bar/baz/qux/1', 'Lorem ipsum' ],
            [ $jsonDoc, '/bar/baz/qux/2', null ],
            [ $jsonDoc, '/bar/baz/qux/3', true ],
            [ $jsonDoc, '/bar/baz/qux/4', false ],
            [ $jsonDoc, '/bar/baz/qux/5/FOO/BAR', 'dolor sit amet' ],
            [ $jsonDoc, '/bar/baz/qux/5/FOO/BAZ/QUX', 'consetetur' ],
            [ $jsonDoc, '/bar/baz/qux/6/0/0', 43 ],
            [ $jsonDoc, '/bar/baz/qux/6/0/2/QUUX/CORGE', true ],
            [ $jsonDoc, '/bar/baz/qux/6/0/2/QUUX/corge', false ],
            [ $jsonDoc, '/bar/baz/qux/6/0/2/QUUX/Corge', 'sadipscing elitr' ]
        ];
    }

    public function testClone()
    {
        $jsonDoc = JsonDocument::newFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $bar2 = clone $jsonDoc->bar;

        $this->assertEquals($jsonDoc->bar, $bar2);

        $bar2->baz->qux[0] = 2;
        $this->assertSame(2, $bar2->baz->qux[0]);
        $this->assertSame(1, $jsonDoc->bar->baz->qux[0]);

        $bar2->baz->corge = 'Lorem ipsum';
        $this->assertSame('Lorem ipsum', $bar2->baz->corge);
        $this->assertFalse(isset($jsonDoc->bar->baz->corge));
    }

    public function testResolveInternal()
    {
        $jsonDoc = JsonDocument::newFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::BAR_FILENAME)
        );

        $jsonDoc2 = clone $jsonDoc;

        $this->assertEquals($jsonDoc, $jsonDoc2);

        $jsonDoc2->resolveReferences(JsonNode::RESOLVE_EXTERNAL);

        $this->assertEquals($jsonDoc, $jsonDoc2);

        /*
        $jsonDoc2->resolveReferences();

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        $this->assertSame(false, strpos($jsonDoc2, '$ref'));

        $this->assertSame('Lorem ipsum', $jsonDoc2->bar->foo);

        $this->assertSame(42, $jsonDoc2->bar->bar[0]);

        $this->assertSame(
            (string)$jsonDoc2->defs->baz,
            (string)$jsonDoc2->bar->bar[1]
        );

        $this->assertSame('dolor', $jsonDoc2->bar->bar[1]->qux2);

        $this->assertSame($jsonDoc2->defs->qux, $jsonDoc2->bar->bar[2]);

        $this->assertSame('dolor', $jsonDoc2->defs->baz->qux2);
        */
    }

    public function testResolveExternal()
    {
        $jsonDoc = JsonDocument::newFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::BAZ_FILENAME)
        );

        $jsonDoc2 = clone $jsonDoc;

        $this->assertEquals($jsonDoc, $jsonDoc2);
        /*
        $jsonDoc2->resolveReferences(JsonNode::RESOLVE_INTERNAL);

        $this->assertEquals($jsonDoc, $jsonDoc2);
        */

        /*
        $jsonDoc2->resolveReferences();

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        $this->assertSame(false, strpos($jsonDoc2, '$ref'));

        $this->assertSame('Lorem ipsum', $jsonDoc2->foo);
        */
    }
}
