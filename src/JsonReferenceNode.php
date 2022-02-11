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
        ?UriInterface $baseUri = null,
        JsonNode $ownerDocument,
        string $jsonPtr
    ): self {
        return new self(
            (object)[ '$ref' => $uri ],
            $baseUri,
            $ownerDocument,
            $jsonPtr
        );
    }
}
