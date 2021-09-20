<?php

namespace alcamo\json;

use alcamo\ietf\Uri;
use Psr\Http\Message\UriInterface;

/**
 * @brief Factory for JsonDocument objects
 */
class JsonDocumentFactory
{
    public const DOCUMENT_CLASS = JsonDocument::class;

    private $depth_; /// int
    private $flags_; /// int

    public function __construct(?int $depth = null, ?int $flags = null)
    {
        $this->depth_ = $depth ?? 512;
        $this->flags_ = $flags ?? JSON_THROW_ON_ERROR;
    }

    public function createFromJsonText(
        string $jsonText,
        ?UriInterface $baseUri = null
    ): JsonNode {
        $class = static::DOCUMENT_CLASS;

        return new $class(
            json_decode($jsonText, false, $this->depth_, $this->flags_),
            null,
            null,
            $baseUri
        );
    }

    public function createFromUrl($url): JsonNode
    {
        return static::createFromJsonText(
            file_get_contents($url),
            $url instanceof UriInterface ? $url : new Uri($url)
        );
    }
}
