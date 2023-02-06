<?php

namespace alcamo\json;

/**
 * @brief Node that represents a JSON schema
 */
class SchemaNode extends JsonNode
{
    public const CLASS_MAP = [
        '$defs'                => SchemaMapNode::class,
        'additionalProperties' => __CLASS__,
        'allOf'                => [ '*' => __CLASS__ ],
        'anyOf'                => [ '*' => __CLASS__ ],
        'contains'             => __CLASS__,
        'default'              => JsonNode::class,
        'dependentSchemas'     => SchemaMapNode::class,
        'else'                 => __CLASS__,
        'examples'             => [ '*' => __CLASS__ ],
        'if'                   => __CLASS__,
        'items'                => __CLASS__,
        'not'                  => __CLASS__,
        'oneOf'                => [ '*' => __CLASS__ ],
        'patternProperties'    => SchemaMapNode::class,
        'prefixItems'          => [ '*' => __CLASS__ ],
        'properties'           => SchemaMapNode::class,
        'propertyNames'        => __CLASS__,
        'then'                 => __CLASS__
    ];
}
