<?php

namespace alcamo\json;

class Foo2Document extends JsonNode
{
    use TypedNodeDocumentTrait;

    public const CLASS_MAP = [
        'foo' => FooNode::class,
        'bar' => BarNode::class
    ];
}
