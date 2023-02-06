<?php

namespace alcamo\json;

/**
 * @brief Document that represents a JSON schema
 */
class SchemaDocument extends SchemaNode implements JsonDocumentInterface
{
    use TypedNodeDocumentTrait;
}
