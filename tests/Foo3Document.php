<?php

namespace alcamo\json;

class Foo3Document extends JsonNode implements JsonDocumentInterface
{
    use TypedNodeDocumentTrait;

    public const CLASS_MAP = [
        'foo' => Foo3Node::class,
        'bar' => BarNode::class,
        '*'   => OtherNode::class
    ];
}

class Foo3Node extends JsonNode
{
}
