<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;

/**
 * @brief JSON document
 */
class JsonDoc extends JsonNode
{
    use JsonDocTrait;
}
