<?php

namespace alcamo\json;

use alcamo\exception\{DataValidationFailed, Recursion};
use alcamo\uri\FileUriFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class MyReferenceResolver extends ReferenceResolver
{
    public function resolveInternalRef(JsonReferenceNode $node, ?int &$action)
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

    public function resolveExternalRef(JsonReferenceNode $node, ?int &$action)
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

        $barDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::BAR_FILENAME)
        );

        $barDoc->checkStructure();

        $barDoc2 = clone $barDoc;

        $barDoc2->checkStructure();

        $this->assertEquals(
            (string)$barDoc->getRoot(),
            (string)$barDoc2->getRoot()
        );

        // does nothing, bar.json has no external references
        $barDoc2->resolveReferences(ReferenceResolver::RESOLVE_EXTERNAL);

        $barDoc2->checkStructure();

        $this->assertEquals(
            (string)$barDoc->getRoot(),
            (string)$barDoc2->getRoot()
        );

        $barDoc2->resolveReferences();

        $barDoc2->checkStructure();

        $this->assertNotEquals($barDoc, $barDoc2);

        // check that all references have been replaced.
        $this->assertSame(false, strpos($barDoc2->getRoot(), '$ref'));

        $this->assertSame('Lorem ipsum', $barDoc2->getRoot()->bar->foo);

        $this->assertSame(42, $barDoc2->getRoot()->bar->bar[0]);

        $this->assertSame(
            (string)$barDoc2->getRoot()->defs->baz,
            (string)$barDoc2->getRoot()->bar->bar[1]
        );

        $this->assertSame(true, $barDoc2->getRoot()->bar->bar[1]->qux2);

        $this->assertSame(
            $barDoc2->getRoot()->defs->qux,
            $barDoc2->getRoot()->bar->bar[2]
        );

        $this->assertSame(true, $barDoc2->getRoot()->defs->baz->qux2);

        $this->assertSame(
            [ "Lorem", "ipsum", true, 43, false, null ],
            $barDoc2->getRoot()->bar->bar[2]
        );

        $this->assertSame(null, $barDoc2->getRoot()->bar->bar[3]);

        // replace refs only in part of the document

        $barDoc3 = clone $barDoc;

        $barDoc3->getRoot()->bar->bar[3] =
            $barDoc3->getRoot()->bar->bar[3]->resolveReferences();

        $this->assertSame(null, $barDoc3->getRoot()->bar->bar[3]);

        $this->assertSame(
            '#/defs/qux/5',
            $barDoc3->getRoot()->defs->quux->{'$ref'}
        );
    }

    // replace a document node by another document node
    public function testResolveExternal1()
    {
        $bazUri = (new FileUriFactory())->create(self::BAZ_FILENAME);

        $factory = new JsonDocumentFactory();

        $bazDoc = $factory->createFromUrl($bazUri);

        $bazDoc->checkStructure();

        $bazDoc2 = clone $bazDoc;

        $this->assertEquals(
            (string)$bazDoc->getRoot(),
            (string)$bazDoc2->getRoot()
        );

        $bazDoc2->resolveReferences(ReferenceResolver::RESOLVE_INTERNAL);

        $bazDoc2->checkStructure();

        $this->assertEquals(
            (string)$bazDoc->getRoot(),
            (string)$bazDoc2->getRoot()
        );

        $bazDoc2->resolveReferences(ReferenceResolver::RESOLVE_EXTERNAL);

        $this->assertNotEquals($bazDoc, $bazDoc2);

        $this->assertFalse(isset($bazDoc2->getRoot()->foo->{'$ref'}));

        $bazDoc2->resolveReferences();

        $this->assertNotEquals($bazDoc, $bazDoc2);

        // check that all references have been replaced
        $this->assertSame(false, strpos($bazDoc2->getRoot(), '$ref'));

        $this->assertSame('Lorem ipsum', $bazDoc2->getRoot()->foo);
    }

    // other internal/external replacements
    public function testResolveExternal2()
    {
        $quxUri = (new FileUriFactory())->create(self::QUX_FILENAME);

        $factory = new JsonDocumentFactory();

        $quxDoc = $factory->createFromUrl($quxUri);

        $quxDoc->checkStructure();

        $quxDoc2 = clone $quxDoc;

        $quxDoc2->resolveReferences();

        $this->assertNotEquals($quxDoc, $quxDoc2);

        // check that all references have been replaced
        $this->assertSame(false, strpos($quxDoc2->getRoot(), '$ref'));

        // replace node by external node, which has been resolved internally
        $this->assertSame('Lorem ipsum', $quxDoc2->getRoot()->foo);

        // replace node by external node, which has been resolved internally
        // to an array
        $this->assertSame(42, $quxDoc2->getRoot()->bar[0]);
        $this->assertSame(true, $quxDoc2->getRoot()->bar[1]->qux2);

        // replacement via multiple files
        $this->assertSame(null, $quxDoc2->getRoot()->quux);

        $this->assertNotSame($quxDoc->getBaseUri(), $quxDoc2->getBaseUri());

        $this->assertEquals($quxDoc->getBaseUri(), $quxDoc2->getBaseUri());

        // node URI is computed from document URI
        $this->assertEquals(
            "$quxUri#/bar/1",
            $quxDoc2->getRoot()->bar[1]->getUri()
        );

        // check that key "0200" in quux.json is correctly preserved
        $this->assertSame(
            'data with weird key',
            $quxDoc2->getRoot()->corge->{'200'}
        );

        $this->assertSame(
            'data with very weird key',
            $quxDoc2->getRoot()->corge->{'0200'}
        );

        $this->assertSame(
            'data with extremely weird key',
            $quxDoc2->getRoot()->corge->{'00200'}
        );
    }

    public function testResolveRecursion()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::RECURSIVE_FILENAME)
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

        $barDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::BAR_FILENAME)
        );

        $barDoc->resolveReferences(
            new MyReferenceResolver(ReferenceResolver::RESOLVE_INTERNAL)
        );

        $this->assertSame(
            '#/defs/foo',
            $barDoc->getNode(JsonPtr::newFromString('/bar/foo/$ref'))
        );

        $this->assertSame(
            'http://www.example.org#bar',
            $barDoc->getNode(JsonPtr::newFromString('/bar/bar/0/$ref'))
        );

        $this->assertSame(
            'Resolved from #/defs/baz',
            $barDoc->getNode(JsonPtr::newFromString('/bar/bar/1/comment'))
        );
    }

    public function testCustomResolveExternal()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromUrl(
            (new FileUriFactory())->create(self::CUSTOM_EXTERNAL_FILENAME)
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
