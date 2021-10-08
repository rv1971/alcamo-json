<?php

namespace alcamo\json;

/**
 * @brief JSON reference objects
 */
class JsonReferenceNode extends JsonNode
{
    public static function newFromUri(
        string $uri,
        JsonNode $ownerDocument,
        string $jsonPtr
    ): self {
        return new self(
            (object)[ '$ref' => $uri ],
            $ownerDocument,
            $jsonPtr
        );
    }
}
