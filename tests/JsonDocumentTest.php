<?php

namespace alcamo\json;

use PHPUnit\Framework\TestCase;
use alcamo\exception\{DataValidationFailed, SyntaxError, Unsupported};

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

        $jsonDoc->checkStructure();
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

    public function testGetNodeWrite()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $jsonDoc->setNode('/foo/~1/~0~1', 43);

        $this->assertSame(43, $jsonDoc->foo->{'/'}->{'~/'});

        $jsonDoc->setNode('/bar/baz/qux/0', 'sed diam nonumy');

        $this->assertSame('sed diam nonumy', $jsonDoc->bar->baz->qux[0]);

        $jsonDoc->setNode('/bar/baz/qux/6/0/2/QUUX/Corge', true);

        $this->assertSame(true, $jsonDoc->bar->baz->qux[6][0][2]->QUUX->Corge);
    }

    public function testException1()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage(
            'Syntax error in "foo" at 0: "foo"; not a valid JSON pointer'
        );

        $jsonDoc->getNode('foo');
    }

    public function testException2()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $this->expectException(Unsupported::class);
        $this->expectExceptionMessage(
            '"/" not supported; root node cannot be replaced'
        );

        $jsonDoc->setNode('/', 'foo');
    }
}
