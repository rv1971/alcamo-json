<?php

namespace alcamo\json;

/// Node that represents a JSON schema
class SchemaNode extends JsonNode
{
    public const CLASS_MAP = [
        '$defs'                => SchemaMapNode::class,
        'additionalProperties' => '#',
        'allOf'                => [ '*' => '#' ],
        'anyOf'                => [ '*' => '#' ],
        'contains'             => '#',
        'default'              => JsonNode::class,
        'dependentSchemas'     => SchemaMapNode::class,
        'else'                 => '#',
        'examples'             => [ '*' => '#' ],
        'if'                   => '#',
        'items'                => '#',
        'not'                  => '#',
        'oneOf'                => [ '*' => '#' ],
        'patternProperties'    => SchemaMapNode::class,
        'prefixItems'          => [ '*' => '#' ],
        'properties'           => SchemaMapNode::class,
        'propertyNames'        => '#',
        'then'                 => '#'
    ];
}
