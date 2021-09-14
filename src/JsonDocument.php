<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;

/**
 * @brief JSON document
 */
class JsonDocument extends JsonNode
{
    use JsonDocumentTrait;
}
