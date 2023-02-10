<?php

namespace alcamo\json;

use PHPUnit\Framework\TestCase;
use alcamo\exception\{DataValidationFailed, SyntaxError, Unsupported};
use alcamo\json\exception\NodeNotFound;

class JsonDocumentTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    public function testConstruct()
    {
        $factory = new JsonDocumentFactory();
        $jsonText = file_get_contents(self::FOO_FILENAME);

        $jsonDoc = $factory->createFromJsonText($jsonText);

        $this->assertSame(
            json_encode(json_decode($jsonText)),
            (string)$jsonDoc->getRoot()
        );

        $this->assertSame(
            $jsonDoc,
            $jsonDoc->getRoot()->getOwnerDocument()
        );

        $this->assertSame(
            $jsonDoc,
            $jsonDoc->getRoot()->foo->getOwnerDocument()
        );

        $this->assertSame(
            $jsonDoc,
            $jsonDoc->getRoot()->bar->baz->getOwnerDocument()
        );

        $this->assertNull($jsonDoc->getBaseUri());
    }

    /**
     * @dataProvider getNodeProvider
     */
    public function testGetNode($jsonDoc, $jsonPtr, $expectedData)
    {
        $this->assertSame(
            $expectedData,
            $jsonDoc->getNode(JsonPtr::newFromString($jsonPtr))
        );
    }

    public function getNodeProvider()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        return [
            [ $jsonDoc, '/foo/~1/~0~1', 42 ],
            [ $jsonDoc, '/foo/~0~0/~1~0', [ 3, 5, 7, 11, 13, 17 ] ],
            [ $jsonDoc, '/foo/~0~0/~1~0/0', 3 ],
            [ $jsonDoc, '/foo/~0~0/~1~0/1', 5 ],
            [ $jsonDoc, '/foo/~0~0/~1~0/5', 17 ],
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

    public function testGetNodeWrite()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $jsonDoc->setNode(JsonPtr::newFromString('/foo/~1/~0~1'), 43);

        $this->assertSame(43, $jsonDoc->getRoot()->foo->{'/'}->{'~/'});

        $jsonDoc->setNode(
            JsonPtr::newFromString('/bar/baz/qux/0'),
            'sed diam nonumy'
        );

        $this->assertSame('sed diam nonumy', $jsonDoc->getRoot()->bar->baz->qux[0]);

        $jsonDoc->setNode(
            JsonPtr::newFromString('/bar/baz/qux/6/0/2/QUUX/Corge'),
            true
        );

        $this->assertSame(true, $jsonDoc->getRoot()->bar->baz->qux[6][0][2]->QUUX->Corge);
    }

    public function testException2()
    {
        $factory = new JsonDocumentFactory();

        $url = 'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME);

        $jsonDoc = $factory->createFromUrl($url);

        $this->expectException(NodeNotFound::class);
        $this->expectExceptionMessage(
            "Node at <" . JsonPtr::class . ">\"/FOO\" not found in <"
            . JsonNode::class
            . ">\"{\"foo\":{\"\/\":{\"~\/\":42},\"~~\":{\"\/~\":[...\" "
            . "at URI \"$url\""
        );

        $jsonDoc->getNode(JsonPtr::newFromString('/FOO/1/2/bar'));
    }

    public function testException3()
    {
        $factory = new JsonDocumentFactory();

        $url = 'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME);

        $jsonDoc = $factory->createFromUrl($url);

        $this->expectException(NodeNotFound::class);
        $this->expectExceptionMessage(
            "Node at <" . JsonPtr::class . ">\"/bar/baz/qux/42\" not found in "
            . "[1, \"Lorem ipsum\", <null>, <true>, <fa...] "
            . "at URI \"$url\""
        );

        $jsonDoc->getNode(JsonPtr::newFromString('/bar/baz/qux/42/7/baz'));
    }

    public function testException5()
    {
        $factory = new JsonDocumentFactory();

        $url = 'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME);

        $jsonDoc = $factory->createFromUrl($url);

        $this->expectException(NodeNotFound::class);
        $this->expectExceptionMessage(
            "Node at <" . JsonPtr::class . ">\"/foo/bar\" not found in <"
            . JsonNode::class
            . ">\"{\"\/\":{\"~\/\":42},\"~~\":{\"\/~\":[3,5,7,1...\" "
            . "at URI \"$url\""
        );

        $jsonDoc->setNode(JsonPtr::newFromString('/foo/bar/1/2/3'), 42);
    }

    public function testException6()
    {
        $factory = new JsonDocumentFactory();

        $url = 'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME);

        $jsonDoc = $factory->createFromUrl($url);

        $this->expectException(NodeNotFound::class);
        $this->expectExceptionMessage(
            "Node at <" . JsonPtr::class . ">\"/bar/baz/qux/43\" not found in "
            . "[1, \"Lorem ipsum\", <null>, <true>, <fa...] "
            . "at URI \"$url\""
        );

        $jsonDoc->setNode(
            JsonPtr::newFromString('/bar/baz/qux/43/foo/bar'),
            false
        );
    }
}
