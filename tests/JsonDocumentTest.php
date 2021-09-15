<?php

namespace alcamo\json;

use PHPUnit\Framework\TestCase;

class JsonDocumentTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

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
            $jsonDoc->foo->getParent()
        );

        $this->assertSame(
            $jsonDoc->bar,
            $jsonDoc->bar->baz->getParent()
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
    public function testGetJsonPtr($node, $expectedJsonPtr)
    {
        $expectedBaseUri =
            'file://' . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME);

        $this->assertSame($expectedBaseUri, (string)$node->getBaseUri());

        $this->assertSame($expectedJsonPtr, $node->getJsonPtr());
    }

    public function getJsonPtrProvider()
    {
        $jsonDoc = JsonDocument::newFromUrl(
            'file://' . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $qux = $jsonDoc->bar->baz->qux;

        return [
            [ $jsonDoc, '/' ],
            [ $jsonDoc->foo, '/foo' ],
            [ $jsonDoc->foo->{'/'}, '/foo/~1' ],
            [ $jsonDoc->foo->{'~~'}, '/foo/~0~0' ],
            [ $jsonDoc->bar, '/bar' ],
            [ $jsonDoc->bar->baz, '/bar/baz' ],
            [ $qux[5], '/bar/baz/qux/5' ],
            [ $qux[5]->FOO, '/bar/baz/qux/5/FOO' ],
            [ $qux[5]->FOO->BAZ, '/bar/baz/qux/5/FOO/BAZ' ],
            [ $qux[6][0][2]->QUUX, '/bar/baz/qux/6/0/2/QUUX' ],
            [ $qux[6][1][1], '/bar/baz/qux/6/1/1' ]
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
        $jsonDoc = JsonDocument::newFromJsonText(
            file_get_contents(self::FOO_FILENAME)
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
}
