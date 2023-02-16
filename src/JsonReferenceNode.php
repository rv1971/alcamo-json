<?php

namespace alcamo\json;

use Psr\Http\Message\UriInterface;

/// JSON reference objects
class JsonReferenceNode extends JsonNode
{
    public static function newFromUri(
        string $uri,
        JsonDocument $ownerDocument,
        JsonPtr $jsonPtr,
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
