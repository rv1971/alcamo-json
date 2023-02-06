<?php

namespace alcamo\json;

use Psr\Http\Message\UriInterface;

/**
 * @brief JSON reference objects
 */
class JsonReferenceNode extends JsonNode
{
    public static function newFromUri(
        string $uri,
        ?JsonNode $ownerDocument = null,
        ?JsonPtr $jsonPtr = null,
        ?JsonNode $parent = null
    ): self {
        return new self(
            (object)[ '$ref' => $uri ],
            $ownerDocument,
            $jsonPtr,
            $parent
        );
    }
}
