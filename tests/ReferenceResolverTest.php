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
                    $node->getBaseUri(),
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
                    $node->getBaseUri(),
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

    public static function checkStructure(JsonDocument $doc): void
    {
        foreach (
            new RecursiveWalker(
                $doc,
                RecursiveWalker::JSON_OBJECTS_ONLY
            ) as $pair
        ) {
            [ $jsonPtr, $node ] = $pair;

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

            if ((string)$node->getJsonPtr() != (string)$jsonPtr) {
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
        $jsonDoc2 =
            $jsonDoc2->resolveReferences(ReferenceResolver::RESOLVE_EXTERNAL);

        self::checkStructure($jsonDoc2);

        $jsonDoc->getDocumentFactory();
        $jsonDoc2->getDocumentFactory();
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

        $jsonDoc2 =
            $jsonDoc2->resolveReferences(ReferenceResolver::RESOLVE_INTERNAL);

        self::checkStructure($jsonDoc2);

        $jsonDoc->getDocumentFactory();
        $jsonDoc2->getDocumentFactory();
        $this->assertEquals($jsonDoc, $jsonDoc2);

        $jsonDoc2 =
            $jsonDoc2->resolveReferences(ReferenceResolver::RESOLVE_EXTERNAL);

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        $this->assertFalse(isset($jsonDoc2->foo->{'$ref'}));

        $this->assertSame($barUri, (string)$jsonDoc2->getBaseUri());

        $jsonDoc2 = $jsonDoc2->resolveReferences();

        $this->assertNotEquals($jsonDoc, $jsonDoc2);

        // check that all references have been replaced
        $this->assertSame(false, strpos($jsonDoc2, '$ref'));

        $this->assertSame('Lorem ipsum', $jsonDoc2->foo);
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

        // node URI is computed from document URI
        $this->assertEquals(
            "$quxUri#/bar/1",
            $jsonDoc2->bar[1]->getUri()
        );

        // check that key "0200" in quux.json is correctly preserved
        $this->assertSame(
            'data with weird key',
            $jsonDoc2->corge->{'200'}
        );

        $this->assertSame(
            'data with very weird key',
            $jsonDoc2->corge->{'0200'}
        );

        $this->assertSame(
            'data with extremely weird key',
            $jsonDoc2->corge->{'00200'}
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
            $jsonDoc->x->{'$ref'}
        );

        $this->assertSame(
            'http://www.example.org#bar',
            $jsonDoc->y->{'$ref'}
        );

        $this->assertSame(
            "Resolved from bar.json#/defs/baz",
            $jsonDoc->z->comment
        );
    }
}
