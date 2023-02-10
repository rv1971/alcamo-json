<?php

namespace alcamo\json;

class Foo3Document extends TypedNodeDocument
{
    public const NODE_CLASS = Foo3RootNode::class;
}

class Foo3RootNode extends JsonNode
{
    public const CLASS_MAP = [
        'foo' => Foo3Node::class,
        'bar' => BarNode::class,
        '*'   => OtherNode::class
    ];
}

class Foo3Node extends JsonNode
{
}
