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
                $jsonDoc->bar->baz->qux[5],
                [
                    '/bar/baz/qux/5' => JsonNode::class,
                    '/bar/baz/qux/5/FOO' => JsonNode::class,
                    '/bar/baz/qux/5/FOO/BAR' => "dolor sit amet",
                    '/bar/baz/qux/5/FOO/BAZ' => JsonNode::class,
                    '/bar/baz/qux/5/FOO/BAZ/QUX' => "consetetur"
                ]
            ]
        ];
    }
}
