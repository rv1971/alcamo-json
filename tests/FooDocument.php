<?php

namespace alcamo\json;

class FooDocument extends JsonNode
{
    use TypedNodeDocumentTrait;

    public const CLASS_MAP = [
        'foo' => FooNode::class,
        'bar' => BarNode::class,
        '*'   => OtherNode::class
    ];
}

class FooNode extends JsonNode
{
    public const CLASS_MAP = [
        '/' =>  SlashNode::class,
        '~~' => TildeTildeNode::class
    ];
}

class SlashNode extends JsonNode
{
}

class TildeTildeNode extends JsonNode
{
}

class BarNode extends JsonNode
{
    public const CLASS_MAP = [
        'baz' => BazNode::class,
        '*'   => '#'
    ];
}

class BazNode extends JsonNode
{
    public const CLASS_MAP = [
        'qux' =>  [
            5 => QuxNode::class,
            '*' => OtherNode::class,
            6 => [
                0 => [ '*' => QuuxNode::class ],
                1 => [ '*' => Foo2Node::class ]
            ]
        ]
    ];
}

class QuxNode extends JsonNode
{
    public const CLASS_MAP = [
        '*' =>  BarNode::class
    ];
}

class QuuxNode extends BarNode
{
}

class Foo2Node extends JsonNode
{
}

class OtherNode extends JsonNode
{
}
