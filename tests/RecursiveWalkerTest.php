<?php

namespace alcamo\json;

use PHPUnit\Framework\TestCase;

class RecursiveWalkerTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    /**
     * @dataProvider walkerProvider
     */
    public function testWalker($startNode, $flags, $expectedNodes)
    {
        $nodes = [];

        foreach (new RecursiveWalker($startNode, $flags) as $ptr => $value) {
            $nodes[$ptr] = is_object($value)
                ? get_class($value)
                : (is_array($value) ? 'array' : $value);

            if ($startNode instanceof Json && $value instanceof JsonNode) {
                $this->assertSame($value->getJsonPtr(), $ptr);
            }
        }

        $this->assertSame($expectedNodes, $nodes);
    }

    public function walkerProvider()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromJsonText(
            file_get_contents(self::FOO_FILENAME)
        );

        return [
            [
                $jsonDoc->foo,
                null,
                [
                    '/foo' => JsonNode::class,
                    '/foo/~1' => JsonNode::class,
                    '/foo/~1/~0~1' => 42,
                    '/foo/~0~0' => JsonNode::class,
                    '/foo/~0~0/~1~0' => 'array',
                    '/foo/~0~0/~1~0/0' => 3,
                    '/foo/~0~0/~1~0/1' => 5,
                    '/foo/~0~0/~1~0/2' => 7,
                    '/foo/~0~0/~1~0/3' => 11,
                    '/foo/~0~0/~1~0/4' => 13,
                    '/foo/~0~0/~1~0/5' => 17
                ]
            ],
            [
                $jsonDoc->bar->baz->qux[5],
                RecursiveWalker::OMIT_START_NODE,
                [
                    '/bar/baz/qux/5/FOO' => JsonNode::class,
                    '/bar/baz/qux/5/FOO/BAR' => 'dolor sit amet',
                    '/bar/baz/qux/5/FOO/BAZ' => JsonNode::class,
                    '/bar/baz/qux/5/FOO/BAZ/QUX' => 'consetetur'
                ]
            ],
            [
                $jsonDoc,
                null,
                [
                    '/' => JsonDocument::class,
                    '/foo' => JsonNode::class,
                    '/foo/~1' => JsonNode::class,
                    '/foo/~1/~0~1' => 42,
                    '/foo/~0~0' => JsonNode::class,
                    '/foo/~0~0/~1~0' => 'array',
                    '/foo/~0~0/~1~0/0' => 3,
                    '/foo/~0~0/~1~0/1' => 5,
                    '/foo/~0~0/~1~0/2' => 7,
                    '/foo/~0~0/~1~0/3' => 11,
                    '/foo/~0~0/~1~0/4' => 13,
                    '/foo/~0~0/~1~0/5' => 17,
                    '/bar' => JsonNode::class,
                    '/bar/baz' => JsonNode::class,
                    '/bar/baz/qux' => 'array',
                    '/bar/baz/qux/0' => 1,
                    '/bar/baz/qux/1' => "Lorem ipsum",
                    '/bar/baz/qux/2' => null,
                    '/bar/baz/qux/3' => true,
                    '/bar/baz/qux/4' => false,
                    '/bar/baz/qux/5' => JsonNode::class,
                    '/bar/baz/qux/5/FOO' => JsonNode::class,
                    '/bar/baz/qux/5/FOO/BAR' => 'dolor sit amet',
                    '/bar/baz/qux/5/FOO/BAZ' => JsonNode::class,
                    '/bar/baz/qux/5/FOO/BAZ/QUX' => 'consetetur',
                    '/bar/baz/qux/6' => 'array',
                    '/bar/baz/qux/6/0' => 'array',
                    '/bar/baz/qux/6/0/0' => 43,
                    '/bar/baz/qux/6/0/1' => 44,
                    '/bar/baz/qux/6/0/2' => JsonNode::class,
                    '/bar/baz/qux/6/0/2/QUUX' => JsonNode::class,
                    '/bar/baz/qux/6/0/2/QUUX/CORGE' => true,
                    '/bar/baz/qux/6/0/2/QUUX/corge' => false,
                    '/bar/baz/qux/6/0/2/QUUX/Corge' => 'sadipscing elitr',
                    '/bar/baz/qux/6/1' => 'array',
                    '/bar/baz/qux/6/1/0'
                    => 'sed diam nonumy eirmod tempor invidunt',
                    '/bar/baz/qux/6/1/1' => JsonNode::class,
                    '/bar/baz/qux/6/1/1/foo' => 'ut labore et dolore magna'
                ]
            ],
            [
                $jsonDoc,
                RecursiveWalker::JSON_OBJECTS_ONLY,
                [
                    '/' => JsonDocument::class,
                    '/foo' => JsonNode::class,
                    '/foo/~1' => JsonNode::class,
                    '/foo/~0~0' => JsonNode::class,
                    '/bar' => JsonNode::class,
                    '/bar/baz' => JsonNode::class,
                    '/bar/baz/qux/5' => JsonNode::class,
                    '/bar/baz/qux/5/FOO' => JsonNode::class,
                    '/bar/baz/qux/5/FOO/BAZ' => JsonNode::class,
                    '/bar/baz/qux/6/0/2' => JsonNode::class,
                    '/bar/baz/qux/6/0/2/QUUX' => JsonNode::class,
                    '/bar/baz/qux/6/1/1' => JsonNode::class
                ]
            ],
            [
                $jsonDoc->bar->baz->qux,
                null,
                [
                    '' => 'array',
                    '0' => 1,
                    '1' => "Lorem ipsum",
                    '2' => null,
                    '3' => true,
                    '4' => false,
                    '5' => JsonNode::class,
                    '5/FOO' => JsonNode::class,
                    '5/FOO/BAR' => 'dolor sit amet',
                    '5/FOO/BAZ' => JsonNode::class,
                    '5/FOO/BAZ/QUX' => 'consetetur',
                    '6' => 'array',
                    '6/0' => 'array',
                    '6/0/0' => 43,
                    '6/0/1' => 44,
                    '6/0/2' => JsonNode::class,
                    '6/0/2/QUUX' => JsonNode::class,
                    '6/0/2/QUUX/CORGE' => true,
                    '6/0/2/QUUX/corge' => false,
                    '6/0/2/QUUX/Corge' => 'sadipscing elitr',
                    '6/1' => 'array',
                    '6/1/0'
                    => 'sed diam nonumy eirmod tempor invidunt',
                    '6/1/1' => JsonNode::class,
                    '6/1/1/foo' => 'ut labore et dolore magna'
                ]
            ],
            [
                $jsonDoc->bar->baz->qux,
                RecursiveWalker::JSON_OBJECTS_ONLY,
                [
                    '5' => JsonNode::class,
                    '5/FOO' => JsonNode::class,
                    '5/FOO/BAZ' => JsonNode::class,
                    '6/0/2' => JsonNode::class,
                    '6/0/2/QUUX' => JsonNode::class,
                    '6/1/1' => JsonNode::class
                ]
            ],
            [
                $jsonDoc->bar->baz->qux[6],
                null,
                [
                    '' => 'array',
                    '0' => 'array',
                    '0/0' => 43,
                    '0/1' => 44,
                    '0/2' => JsonNode::class,
                    '0/2/QUUX' => JsonNode::class,
                    '0/2/QUUX/CORGE' => true,
                    '0/2/QUUX/corge' => false,
                    '0/2/QUUX/Corge' => 'sadipscing elitr',
                    '1' => 'array',
                    '1/0'
                    => 'sed diam nonumy eirmod tempor invidunt',
                    '1/1' => JsonNode::class,
                    '1/1/foo' => 'ut labore et dolore magna'
                ]
            ],
            [
                $jsonDoc->bar->baz->qux[0],
                null,
                [
                    '' => 1
                ]
            ],
            [
                $jsonDoc->bar->baz->qux[1],
                null,
                [
                    '' => 'Lorem ipsum'
                ]
            ],
            [
                $jsonDoc->bar->baz->qux[2],
                null,
                [
                    '' => null
                ]
            ],
            [
                $jsonDoc->bar->baz->qux[3],
                null,
                [
                    '' => true
                ]
            ],
            [
                $jsonDoc->bar->baz->qux[4],
                null,
                [
                    '' => false
                ]
            ],
            [
                $jsonDoc->bar->baz->qux[0],
                RecursiveWalker::OMIT_START_NODE,
                []
            ],
            [
                $jsonDoc->bar->baz->qux[0],
                RecursiveWalker::JSON_OBJECTS_ONLY,
                []
            ]
        ];
    }

    public function testSkipChildren()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromJsonText(
            file_get_contents(self::FOO_FILENAME)
        );

        $nodes = [];

        $walker = new RecursiveWalker($jsonDoc);

        foreach ($walker as $ptr => $value) {
            switch ($ptr)
            {
                case '/foo/~0~0':
                case '/bar/baz/qux':
                    $walker->skipChildren();

                default:
                    $nodes[] = $ptr;
            }
        }

        $this->assertSame(
            [
                '/',
                '/foo',
                '/foo/~1',
                '/foo/~1/~0~1',
                '/foo/~0~0',
                '/bar',
                '/bar/baz',
                '/bar/baz/qux'
            ],
            $nodes
        );
    }

    // replaceCurrent() when start node is a JsonNode
    public function testReplaceCurrent1()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromJsonText(
            file_get_contents(self::FOO_FILENAME)
        );

        // replaceCurrent() on a node in an object
        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $walker = new RecursiveWalker($jsonDoc2->bar);

        $walker->next();

        $walker->replaceCurrent('Lorem ipsum');

        $this->assertSame('Lorem ipsum', $jsonDoc2->bar->baz);

        // replaceCurrent() on a node in an array
        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $walker = new RecursiveWalker($jsonDoc2->bar->baz);

        $walker->next();

        $walker->next();

        $walker->replaceCurrent('dolor');

        $this->assertSame('dolor', $jsonDoc2->bar->baz->qux[0]);

        // replaceCurrent() on a node in a nested array
        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $walker = new RecursiveWalker(
            $jsonDoc2->bar->baz,
            RecursiveWalker::JSON_OBJECTS_ONLY
        );

        for ($i = 0; $i < 4; $i++) {
            $walker->next();
        }

        $walker->replaceCurrent('dolor');

        $this->assertSame('dolor', $jsonDoc2->bar->baz->qux[6][0][2]);

        // replaceCurrent() on the start node
        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $walker = new RecursiveWalker($jsonDoc2->foo);

        $walker->replaceCurrent('Lorem ipsum');

        $this->assertSame('Lorem ipsum', $jsonDoc2->foo);

        // replaceCurrent() on the document node
        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $walker = new RecursiveWalker($jsonDoc2);

        $walker->replaceCurrent($jsonDoc2->foo);

        $this->assertSame(42, $jsonDoc2->{'/'}->{'~/'});

        // replace by array
        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $walker = new RecursiveWalker($jsonDoc2);

        $walker->next();

        $walker->replaceCurrent($jsonDoc2->bar->baz->qux[6]);

        $walker->next();
        $walker->next();

        $this->assertSame(43, $walker->current());

        // replace start node by array
        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $walker = new RecursiveWalker($jsonDoc2->foo->{'/'});

        $walker->replaceCurrent($jsonDoc2->bar->baz->qux[6]);

        $walker->next();
        $walker->next();

        $this->assertSame(43, $walker->current());

        // replace again the same node
        $walker->replaceCurrent($jsonDoc2->bar->baz->qux[6][0][2]->QUUX);

        $this->assertSame(true, $walker->current()->CORGE);
    }

    // replaceCurrent() when start node is an array
    public function testReplaceCurrent2()
    {
        $factory = new JsonDocumentFactory();

        $jsonDoc = $factory->createFromJsonText(
            file_get_contents(self::FOO_FILENAME)
        );

        // replaceCurrent() on a node in an array
        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $walker = new RecursiveWalker($jsonDoc2->bar->baz->qux);

        $walker->next();

        $walker->replaceCurrent('consetetur');

        $this->assertSame('consetetur', $jsonDoc2->bar->baz->qux[0]);

        // replaceCurrent() on a node in an object
        for ($i = 0; $i < 6; $i++) {
            $walker->next();
        }

        $walker->replaceCurrent(4242);

        $this->assertSame(4242, $jsonDoc2->bar->baz->qux[5]->FOO);

        // replaceCurrent() on a node in a nested array
        for ($i = 0; $i < 3; $i++) {
            $walker->next();
        }

        $walker->replaceCurrent(false);

        $this->assertSame(false, $jsonDoc2->bar->baz->qux[6][0][0]);

        // replaceCurrent() on the start node
        $jsonDoc2 = $jsonDoc->createDeepCopy();

        $walker = new RecursiveWalker($jsonDoc2->bar->baz->qux[6]);

        $walker->replaceCurrent('sit amet');

        $this->assertSame('sit amet', $jsonDoc2->bar->baz->qux[6]);
    }
}
