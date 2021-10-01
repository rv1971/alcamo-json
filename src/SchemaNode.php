<?php

namespace alcamo\json;

/**
 * @brief Node that represents a JSON schema
 */
class SchemaNode extends JsonNode
{
    public const CLASS_MAP = [
        '$defs'                => SchemaMapNode::class,
        'additionalProperties' => '#',
        'allOf'                => [ '*' => '#' ],
        'anyOf'                => [ '*' => '#' ],
        'contains'             => '#',
        'dependentSchemas'     => SchemaMapNode::class,
        'else'                 => '#',
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
