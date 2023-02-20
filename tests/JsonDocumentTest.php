<?php

namespace alcamo\json;

use alcamo\json\exception\NodeNotFound;
use alcamo\uri\{FileUriFactory, Uri};
use PHPUnit\Framework\TestCase;

class JsonDocumentTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    public const BAR_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'bar.json';

    public const BAZ_FILENAME = __DIR__ . DIRECTORY_SEPARATOR .
        'foo' . DIRECTORY_SEPARATOR . 'baz.json';

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
        $baseUri,
        $expectedRootType
    ): void {
        $jsonDoc = new JsonDocument($data, new Uri($baseUri));

        $this->assertSame(
            $expectedRootType,
            is_object($jsonDoc->getRoot())
            ? get_class($jsonDoc->getRoot())
            : gettype($jsonDoc->getRoot())
        );

        $this->assertSame((string)$baseUri, (string)$jsonDoc->getBaseUri());

        $this->assertInstanceOf(
            JsonDocumentFactory::class,
            $jsonDoc->getDocumentFactory()
        );

        $jsonDoc->checkStructure();

        $this->assertSame(
            $jsonDoc->getRoot(),
            $jsonDoc->getNode(new JsonPtr())
        );

        $jsonDoc2 = clone $jsonDoc;

        $jsonDoc2->checkStructure();

        $this->assertInstanceOf(
            JsonDocumentFactory::class,
            $jsonDoc2->getDocumentFactory()
        );

        $this->assertEquals($jsonDoc, $jsonDoc2);

        $this->assertNotSame($jsonDoc, $jsonDoc2);

        if (is_object($jsonDoc->getRoot())) {
            $this->assertNotSame($jsonDoc->getRoot(), $jsonDoc2->getRoot());
        }
    }

    public function constructProvider(): array
    {
        return [
            [ null, null, 'NULL' ],
            [ true, null, 'boolean' ],
            [ 42, 'https://42.example.edu', 'integer' ],
            [ 42.42, null, 'double' ],
            [ 'Lorem ipsum', 'http://lorem-ipsum.example.org', 'string' ],
            [
                [ null, 43, 43.7, 'Dolor', (object)[ 'foo' => 'bar' ], [] ],
                null,
                'array'
            ],
            [
                (object)[ 'baz' => true, 'qux' => [ 'quux' ] ],
                'http://object.example.org',
                JsonNode::class
            ]
        ];
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
        $fooDoc = $this->getFooDoc();

        return [
            [ $fooDoc, '/foo/~1/~0~1', 42 ],
            [ $fooDoc, '/foo/~0~0/~1~0', [ 3, 5, 7, 11, 13, 17 ] ],
            [ $fooDoc, '/foo/~0~0/~1~0/0', 3 ],
            [ $fooDoc, '/foo/~0~0/~1~0/1', 5 ],
            [ $fooDoc, '/foo/~0~0/~1~0/5', 17 ],
            [ $fooDoc, '/bar/baz/qux/0', 1 ],
            [ $fooDoc, '/bar/baz/qux/1', 'Lorem ipsum' ],
            [ $fooDoc, '/bar/baz/qux/2', null ],
            [ $fooDoc, '/bar/baz/qux/3', true ],
            [ $fooDoc, '/bar/baz/qux/4', false ],
            [ $fooDoc, '/bar/baz/qux/5/FOO/BAR', 'dolor sit amet' ],
            [ $fooDoc, '/bar/baz/qux/5/FOO/BAZ/QUX', 'consetetur' ],
            [ $fooDoc, '/bar/baz/qux/6/0/0', 43 ],
            [ $fooDoc, '/bar/baz/qux/6/0/2/QUUX/CORGE', true ],
            [ $fooDoc, '/bar/baz/qux/6/0/2/QUUX/corge', false ],
            [ $fooDoc, '/bar/baz/qux/6/0/2/QUUX/Corge', 'sadipscing elitr' ]
        ];
    }

    public function testSetNode()
    {
        $fooDoc = $this->getFooDoc();

        $fooDoc->setNode(JsonPtr::newFromString('/foo/~1/~0~1'), 43);

        $this->assertSame(43, $fooDoc->getRoot()->foo->{'/'}->{'~/'});

        $fooDoc->setNode(
            JsonPtr::newFromString('/bar/baz/qux/0'),
            'sed diam nonumy'
        );

        $this->assertSame(
            'sed diam nonumy',
            $fooDoc->getRoot()->bar->baz->qux[0]
        );

        $fooDoc->setNode(
            JsonPtr::newFromString('/bar/baz/qux/6/0/2/QUUX/Corge'),
            true
        );

        $this->assertSame(
            true,
            $fooDoc->getRoot()->bar->baz->qux[6][0][2]->QUUX->Corge
        );

        $fooDoc->setNode(new JsonPtr(), false);

        $this->assertFalse($fooDoc->getRoot());
    }

    public function testException1()
    {
        $fooDoc = $this->getFooDoc();

        $url = $fooDoc->getBaseUri();

        $this->expectException(NodeNotFound::class);
        $this->expectExceptionMessage(
            "Node at <" . JsonPtr::class . ">\"/FOO\" not found in <"
            . JsonNode::class
            . ">\"{\"foo\":{\"\/\":{\"~\/\":42},\"~~\":{\"\/~\":[...\" "
            . "at URI \"$url\""
        );

        $fooDoc->getNode(JsonPtr::newFromString('/FOO/1/2/bar'));
    }

    public function testException2()
    {
        $fooDoc = $this->getFooDoc();

        $url = $fooDoc->getBaseUri();

        $this->expectException(NodeNotFound::class);
        $this->expectExceptionMessage(
            "Node at <" . JsonPtr::class . ">\"/bar/baz/qux/42\" not found in "
            . "[1, \"Lorem ipsum\", <null>, <true>, <fa...] "
            . "at URI \"$url\""
        );

        $fooDoc->getNode(JsonPtr::newFromString('/bar/baz/qux/42/7/baz'));
    }

    public function testException3()
    {
        $fooDoc = $this->getFooDoc();

        $url = $fooDoc->getBaseUri();

        $this->expectException(NodeNotFound::class);
        $this->expectExceptionMessage(
            "Node at <" . JsonPtr::class . ">\"/foo/bar\" not found in <"
            . JsonNode::class
            . ">\"{\"\/\":{\"~\/\":42},\"~~\":{\"\/~\":[3,5,7,1...\" "
            . "at URI \"$url\""
        );

        $fooDoc->setNode(JsonPtr::newFromString('/foo/bar/1/2/3'), 42);
    }

    public function testException4()
    {
        $fooDoc = $this->getFooDoc();

        $url = $fooDoc->getBaseUri();

        $this->expectException(NodeNotFound::class);
        $this->expectExceptionMessage(
            "Node at <" . JsonPtr::class . ">\"/bar/baz/qux/43\" not found in "
            . "[1, \"Lorem ipsum\", <null>, <true>, <fa...] "
            . "at URI \"$url\""
        );

        $fooDoc->setNode(
            JsonPtr::newFromString('/bar/baz/qux/43/foo/bar'),
            false
        );
    }

    public function testGetNodeClassToCreate(): void
    {
        $factory = new JsonDocumentFactory();

        $barDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::BAR_FILENAME)
        );

        $this->assertInstanceOf(
            JsonReferenceNode::class,
            $barDoc->getRoot()->bar->foo
        );

        $this->assertInstanceOf(
            JsonReferenceNode::class,
            $barDoc->getRoot()->bar->bar[0]
        );
    }

    public function testResolveReferences(): void
    {
        $factory = new JsonDocumentFactory();

        $bazDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::BAZ_FILENAME)
        );

        $bazDoc2 = clone $bazDoc;

        $bazDoc2->resolveReferences(ReferenceResolver::RESOLVE_INTERNAL);

        $this->assertEquals(
            (string)$bazDoc->getRoot(),
            (string)$bazDoc2->getRoot()
        );

        $bazDoc2->resolveReferences(ReferenceResolver::RESOLVE_EXTERNAL);

        $this->assertSame('Lorem ipsum', $bazDoc2->getRoot()->foo);
    }
}
