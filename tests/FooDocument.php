<?php

namespace alcamo\json;

use Psr\Http\Message\UriInterface;

class FooDocument extends TypedNodeDocument
{
    public const NODE_CLASS = FooRootNode::class;
}

class FooRootNode extends JsonNode
{
    public const CLASS_MAP = [
        'foo' => FooNode::class,
        'bar' => BarNode::class,
        '*'   => OtherNode::class
    ];

    public function rebase(UriInterface $oldBase): void
    {
        $this->oldBase = (string)$oldBase;
    }
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
    public function rebase(UriInterface $oldBase): void
    {
        $this->oldBaseSlash = (string)$oldBase;
    }
}

class TildeTildeNode extends JsonNode
{
    public const CLASS_MAP = [
        '*' =>  OtherNode::class
    ];
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
    public function rebase(UriInterface $oldBase): void
    {
        $this->oldBaseQuux = (string)$oldBase;
    }
}

class Foo2Node extends JsonNode
{
}

class OtherNode extends JsonNode
{
    public const CLASS_MAP = [ '*' => '#' ];

    public function rebase(UriInterface $oldBase): void
    {
        $this->oldBaseOther = (string)$oldBase;
    }
}

class FooDocumentFactory extends JsonDocumentFactory
{
    public const DOCUMENT_CLASS = FooDocument::class;
}
