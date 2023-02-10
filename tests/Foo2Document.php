<?php

namespace alcamo\json;

class Foo2Document extends TypedNodeDocument
{
    public const NODE_CLASS = Foo2RootNode::class;
}

class Foo2RootNode extends JsonNode
{
    public const CLASS_MAP = [
        'foo' => FooNode::class,
        'bar' => BarNode::class
    ];
}
