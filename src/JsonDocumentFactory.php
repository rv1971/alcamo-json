<?php

namespace alcamo\json;

use alcamo\ietf\Uri;
use Psr\Http\Message\UriInterface;

/**
 * @brief Factory for JsonDocument objects or parts thereof
 */
class JsonDocumentFactory
{
    /// Class to use to create a document
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
        $baseUri = null
    ): JsonNode {
        $class = static::DOCUMENT_CLASS;

        if (isset($baseUri) && !$baseUri instanceof UriInterface) {
            $baseUri = new Uri($baseUri);
        }

        return new $class(
            json_decode($jsonText, false, $this->depth_, $this->flags_),
            null,
            null,
            $baseUri
        );
    }

    /**
     * @param $url If this URL contains a fragment, return the node indicated
     * by the fragment, otherwise the entire document.
     */
    public function createFromUrl($url)
    {
        if (!$url instanceof UriInterface) {
            $url = new Uri($url);
        }

        $fragment = $url->getFragment();

        if ($fragment == '') {
            return static::createFromJsonText(file_get_contents($url), $url);
        } else {
            $urlWithoutFragment = $url->withFragment('');

            return static::createFromJsonText(
                file_get_contents($urlWithoutFragment),
                $urlWithoutFragment
            )->getNode($fragment);
        }
    }
}
