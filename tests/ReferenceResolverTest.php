<?php

namespace alcamo\json;

use alcamo\exception\{DataValidationFailed, Recursion};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class MyReferenceResolver extends ReferenceResolver
{
    public function resolveInternalRef(JsonNode $node, ?int &$action)
    {
        switch (substr($node->{'$ref'}, -3)) {
            case 'foo':
                $action = self::SKIP;
                return null;

            case 'bar':
                $action = self::STOP_RECURSION;

                return JsonReferenceNode::newFromUri(
                    'http://www.example.org#bar',
                    $node->getOwnerDocument(),
                    $node->getJsonPtr()
                );

            case 'baz':
                $newNode = parent::resolveInternalRef($node, $action);
                $newNode->comment = "Resolved from {$node->{'$ref'}}";
                return $newNode;

            default:
                return parent::resolveInternalRef($node, $action);
        }
    }

    public function resolveExternalRef(JsonNode $node, ?int &$action)
    {
        switch (substr($node->{'$ref'}, -3)) {
            case 'foo':
                $action = self::SKIP;
                return null;

            case 'bar':
                $action = self::STOP_RECURSION;

                return JsonReferenceNode::newFromUri(
                    'http://www.example.org#bar',
                    $node->getOwnerDocument(),
                    $node->getJsonPtr()
                );

            case 'baz':
                $newNode = parent::resolveExternalRef($node, $action);
                $newNode->comment = "Resolved from {$node->{'$ref'}}";
                return $newNode;

            default:
                return parent::resolveExternalRef($node, $action);
        }
    }
}

class ReferenceResolverTest extends TestCase
{
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
    public const RECURSIVE_FILENAME = __DIR__ . DIRECTORY_SEPARATOR
        . 'recursive.json';
    public const CUSTOM_EXTERNAL_FILENAME = __DIR__ . DIRECTORY_SEPARATOR
        . 'custom-external.json';

    public function testResolveInternal()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::BAR_FILENAME)
        );

        self::checkStructure($jsonDoc);

        $jsonDoc2 = clone $jsonDoc;

        self::checkStructure($jsonDoc2);

        $this->assertEquals(
            (string)$jsonDoc->getRoot(),
            (string)$jsonDoc2->getRoot()
        );

        // does nothing, bar.json has no external references
        $jsonDoc2->resolveReferences(ReferenceResolver::RESOLVE_EXTERNAL);

        self::checkStructure($jsonDoc2);

        $this->assertEquals(
            (string)$jsonDoc->getRoot(),
            (string)$jsonDoc2->getRoot()
        );

        $jsonDoc2->resolveReferences();

        self::checkStructure($jsonDoc2);

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        // check that all references have been replaced.
        $this->assertSame(false, strpos($jsonDoc2->getRoot(), '$ref'));

        $this->assertSame('Lorem ipsum', $jsonDoc2->getRoot()->bar->foo);

        $this->assertSame(42, $jsonDoc2->getRoot()->bar->bar[0]);

        $this->assertSame(
            (string)$jsonDoc2->getRoot()->defs->baz,
            (string)$jsonDoc2->getRoot()->bar->bar[1]
        );

        $this->assertSame(true, $jsonDoc2->getRoot()->bar->bar[1]->qux2);

        $this->assertSame(
            $jsonDoc2->getRoot()->defs->qux,
            $jsonDoc2->getRoot()->bar->bar[2]
        );

        $this->assertSame(true, $jsonDoc2->getRoot()->defs->baz->qux2);

        $this->assertSame(
            [ "Lorem", "ipsum", true, 43, false, null ],
            $jsonDoc2->getRoot()->bar->bar[2]
        );

        $this->assertSame(null, $jsonDoc2->getRoot()->bar->bar[3]);

        // replace refs only in part of the document

        $jsonDoc3 = clone $jsonDoc;

        $jsonDoc3->getRoot()->bar->bar[3]->resolveReferences();

        $this->assertSame(null, $jsonDoc3->getRoot()->bar->bar[3]);

        $this->assertSame(
            '#/defs/qux/5',
            $jsonDoc3->getRoot()->defs->quux->{'$ref'}
        );
    }

    // replace a document node by another document node
    public function testResolveExternal1()
    {
        $bazUri = 'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::BAZ_FILENAME);

        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl($bazUri);

        self::checkStructure($jsonDoc);

        $jsonDoc2 = clone $jsonDoc;

        $this->assertEquals(
            (string)$jsonDoc->getRoot(),
            (string)$jsonDoc2->getRoot()
        );

        $jsonDoc2->resolveReferences(ReferenceResolver::RESOLVE_INTERNAL);

        self::checkStructure($jsonDoc2);

        $this->assertEquals(
            (string)$jsonDoc->getRoot(),
            (string)$jsonDoc2->getRoot()
        );

        $jsonDoc2->resolveReferences(ReferenceResolver::RESOLVE_EXTERNAL);

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        $this->assertFalse(isset($jsonDoc2->getRoot()->foo->{'$ref'}));

        $jsonDoc2->resolveReferences();

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        // check that all references have been replaced
        $this->assertSame(false, strpos($jsonDoc2->getRoot(), '$ref'));

        $this->assertSame('Lorem ipsum', $jsonDoc2->getRoot()->foo);
    }

    // other internal/external replacements
    public function testResolveExternal2()
    {
        $quxUri = 'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::QUX_FILENAME);

        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl($quxUri);

        self::checkStructure($jsonDoc);

        $jsonDoc2 = clone $jsonDoc;

        $jsonDoc2->resolveReferences();

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        // check that all references have been replaced
        $this->assertSame(false, strpos($jsonDoc2->getRoot(), '$ref'));

        // replace node by external node, which has been resolved internally
        $this->assertSame('Lorem ipsum', $jsonDoc2->getRoot()->foo);

        // replace node by external node, which has been resolved internally
        // to an array
        $this->assertSame(42, $jsonDoc2->getRoot()->bar[0]);
        $this->assertSame(true, $jsonDoc2->getRoot()->bar[1]->qux2);

        // replacement via multiple files
        $this->assertSame(null, $jsonDoc2->getRoot()->quux);

        $this->assertNotSame($jsonDoc->getBaseUri(), $jsonDoc2->getBaseUri());

        $this->assertEquals($jsonDoc->getBaseUri(), $jsonDoc2->getBaseUri());

        // node URI is computed from document URI
        $this->assertEquals(
            "$quxUri#/bar/1",
            $jsonDoc2->getRoot()->bar[1]->getUri()
        );

        // check that key "0200" in quux.json is correctly preserved
        $this->assertSame(
            'data with weird key',
            $jsonDoc2->getRoot()->corge->{'200'}
        );

        $this->assertSame(
            'data with very weird key',
            $jsonDoc2->getRoot()->corge->{'0200'}
        );

        $this->assertSame(
            'data with extremely weird key',
            $jsonDoc2->getRoot()->corge->{'00200'}
        );
    }

    public function testResolveRecursion()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::RECURSIVE_FILENAME)
        );

        $this->expectException(Recursion::class);
        $this->expectExceptionMessage('Recursion detected at URI "file:/');
        $this->expectExceptionMessage(
            'attempting to resolve "#/bar" at "/foo/0/0" for the second time'
        );

        $jsonDoc->resolveReferences();
    }

    public function testCustomResolveInternal()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::BAR_FILENAME)
        );

        $jsonDoc->resolveReferences(
            new MyReferenceResolver(ReferenceResolver::RESOLVE_INTERNAL)
        );

        $this->assertSame(
            '#/defs/foo',
            $jsonDoc->getNode(JsonPtr::newFromString('/bar/foo/$ref'))
        );

        $this->assertSame(
            'http://www.example.org#bar',
            $jsonDoc->getNode(JsonPtr::newFromString('/bar/bar/0/$ref'))
        );

        $this->assertSame(
            'Resolved from #/defs/baz',
            $jsonDoc->getNode(JsonPtr::newFromString('/bar/bar/1/comment'))
        );
    }

    public function testCustomResolveExternal()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            'file://'
            . str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                self::CUSTOM_EXTERNAL_FILENAME
            )
        );

        $jsonDoc->resolveReferences(
            new MyReferenceResolver(ReferenceResolver::RESOLVE_EXTERNAL)
        );

        $this->assertSame(
            'bar.json#/defs/foo',
            $jsonDoc->getRoot()->x->{'$ref'}
        );

        $this->assertSame(
            'http://www.example.org#bar',
            $jsonDoc->getRoot()->y->{'$ref'}
        );

        $this->assertSame(
            "Resolved from bar.json#/defs/baz",
            $jsonDoc->getRoot()->z->comment
        );
    }
}
