<?php

namespace alcamo\json;

use alcamo\ietf\Uri;
use Psr\Http\Message\UriInterface;

/**
 * @brief Factory for JsonNode objects
 */
class JsonNodeFactory
{
    private $depth_; /// int
    private $flags_; /// int

    public function __construct(?int $depth = null, ?int $flags = null)
    {
        $this->depth_ = $depth ?? 512;
        $this->flags_ = $flags ?? JSON_THROW_ON_ERROR;
    }

    public function createFromJsonText(
        string $jsonText,
        ?JsonNode $ownerDocument = null,
        ?string $jsonPtr = null,
        string $class = JsonDocument::class,
        ?UriInterface $baseUri = null
    ): JsonNode {
        return new $class(
            json_decode($jsonText, false, $this->depth_, $this->flags_),
            $ownerDocument,
            $jsonPtr,
            $baseUri
        );
    }

    public function createFromUrl(
        $url,
        ?JsonNode $ownerDocument = null,
        ?string $jsonPtr = null,
        string $class = JsonDocument::class
    ): JsonNode {
        return static::createFromJsonText(
            file_get_contents($url),
            $ownerDocument,
            $jsonPtr,
            $class,
            $url instanceof UriInterface ? $url : new Uri($url)
        );
    }
}
