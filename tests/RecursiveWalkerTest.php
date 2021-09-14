<?php

namespace alcamo\json;

use PHPUnit\Framework\TestCase;

class RecursiveWalkerTest extends TestCase
{
    public const FOO_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . 'foo.json';

    /**
     * @dataProvider walkerProvider
     */
    public function testWalker($startNode, $expectedNodes)
    {
        $nodes = [];

        foreach (new RecursiveWalker($startNode) as $ptr => $value) {
            $nodes[$ptr] = is_object($value)
                ? get_class($value)
                : (is_array($value) ? 'array' : $value);

            if (is_object($value)) {
                $this->assertSame($value->getJsonPtr(), $ptr);
            }
        }

        $this->assertSame($expectedNodes, $nodes);
    }

    public function walkerProvider()
    {
        $jsonDoc = JsonDocument::newFromJsonText(
            file_get_contents(self::FOO_FILENAME)
        );

        return [
            [
                $jsonDoc->foo,
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
                [
                    '/bar/baz/qux/5' => JsonNode::class,
                    '/bar/baz/qux/5/FOO' => JsonNode::class,
                    '/bar/baz/qux/5/FOO/BAR' => 'dolor sit amet',
                    '/bar/baz/qux/5/FOO/BAZ' => JsonNode::class,
                    '/bar/baz/qux/5/FOO/BAZ/QUX' => 'consetetur'
                ]
            ],
            [
                $jsonDoc,
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
            ]
        ];
    }
}
