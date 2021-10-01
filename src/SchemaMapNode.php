<?php

namespace alcamo\json;

/**
 * @brief Node that maps properties of any kind to JSON schemas
 */
class SchemaMapNode extends JsonNode
{
    public const CLASS_MAP = [
        '*' => SchemaNode::class
    ];
}
