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
            $schemaDoc->getRoot()->properties
        );

        $this->assertInstanceOf(
            JsonReferenceNode::class,
            $schemaDoc->getRoot()->properties->foo
        );

        $this->assertInstanceOf(
            SchemaMapNode::class,
            $schemaDoc->getRoot()->{'$defs'}
        );

        $this->assertInstanceOf(
            SchemaNode::class,
            $schemaDoc->getRoot()->{'$defs'}->Foo
        );

        $this->assertInstanceOf(
            JsonReferenceNode::class,
            $schemaDoc->getRoot()->{'$defs'}->Foo->anyOf[0]
        );

        $this->assertInstanceOf(
            SchemaNode::class,
            $schemaDoc->getRoot()->{'$defs'}->Foo->anyOf[2]
        );

        $this->assertInstanceOf(
            SchemaNode::class,
            $schemaDoc->getRoot()->{'$defs'}->Bar
        );

        $this->assertInstanceOf(
            SchemaMapNode::class,
            $schemaDoc->getRoot()->{'$defs'}->Bar->properties
        );

        $this->assertInstanceOf(
            SchemaNode::class,
            $schemaDoc->getRoot()->{'$defs'}->Bar->properties->bar
        );

        $this->assertInstanceOf(
            SchemaNode::class,
            $schemaDoc->getRoot()->{'$defs'}->Baz->items
        );
    }
}
