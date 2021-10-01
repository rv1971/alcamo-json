<?php

namespace alcamo\json;

use PHPUnit\Framework\TestCase;

class SchemaDocumentTest extends TestCase
{
    public const SCHEMA_FILENAME =
        __DIR__ . DIRECTORY_SEPARATOR . 'schema.json';

    public function testBasics()
    {
        $schemaDoc = (new SchemaDocumentFactory())
            ->createFromUrl(self::SCHEMA_FILENAME);

        $this->assertInstanceOf(
            SchemaMapNode::class,
            $schemaDoc->properties
        );

        $this->assertInstanceOf(
            JsonReferenceNode::class,
            $schemaDoc->properties->foo
        );

        $this->assertInstanceOf(
            SchemaMapNode::class,
            $schemaDoc->{'$defs'}
        );

        $this->assertInstanceOf(
            SchemaNode::class,
            $schemaDoc->{'$defs'}->Foo
        );

        $this->assertInstanceOf(
            JsonReferenceNode::class,
            $schemaDoc->{'$defs'}->Foo->anyOf[0]
        );

        $this->assertInstanceOf(
            SchemaNode::class,
            $schemaDoc->{'$defs'}->Foo->anyOf[2]
        );

        $this->assertInstanceOf(
            SchemaNode::class,
            $schemaDoc->{'$defs'}->Bar
        );

        $this->assertInstanceOf(
            SchemaMapNode::class,
            $schemaDoc->{'$defs'}->Bar->properties
        );

        $this->assertInstanceOf(
            SchemaNode::class,
            $schemaDoc->{'$defs'}->Bar->properties->bar
        );

        $this->assertInstanceOf(
            SchemaNode::class,
            $schemaDoc->{'$defs'}->Baz->items
        );
    }
}
