<?php

namespace alcamo\json;

use alcamo\exception\{ExceptionInterface, SyntaxError};
use alcamo\uri\Uri;
use Psr\Http\Message\UriInterface;

/**
 * @brief Factory for JsonDocument objects or parts thereof
 */
class JsonDocumentFactory
{
    /// Default class to use to create a document
    public const DOCUMENT_CLASS = JsonDocument::class;

    private $documentClass_; ///< string
    private $depth_;         ///< int
    private $flags_;         ///< int

    public function __construct(
        ?string $documentClass = null,
        ?int $depth = null,
        ?int $flags = null
    ) {
        $this->documentClass_ = $documentClass ?? static::DOCUMENT_CLASS;
        $this->depth_ = $depth ?? 512;
        $this->flags_ = $flags ?? JSON_THROW_ON_ERROR;
    }

    public function getClass(): string
    {
        return $this->documentClass_;
    }

    /// Wrapper for json_decode()
    public function decodeJson(string $jsonText)
    {
        return json_decode($jsonText, false, $this->depth_, $this->flags_);
    }

    public function createFromJsonText(
        string $jsonText,
        ?UriInterface $baseUri = null,
        ?string $documentClass = null
    ): JsonDocument {
        if (!isset($documentClass)) {
            $documentClass = $this->documentClass_;
        }

        if (isset($baseUri) && !($baseUri instanceof UriInterface)) {
            $baseUri = new Uri($baseUri);
        }

        try {
            return new $documentClass($this->decodeJson($jsonText), $baseUri);
        } catch (\Throwable $e) {
            if (!($e instanceof ExceptionInterface)) {
                $e = SyntaxError::newFromPrevious($e);
            }

            if (!isset($e->getMessageContext()['atUri'])) {
                throw $e->addMessageContext([ 'atUri' => $baseUri ]);
            }

            if (!isset($e->getMessageContext()['inData'])) {
                $e->addMessageContext([ 'inData' => $jsonText ]);
            }

            throw $e;
        }
    }

    /**
     * @param $url If this URL contains a fragment, return the node indicated
     * by the fragment, otherwise the entire document.
     */
    public function createFromUrl($url, ?string $documentClass = null)
    {
        if (!$url instanceof UriInterface) {
            $url = new Uri($url);
        }

        $fragment = $url->getFragment();

        if ($fragment == '') {
            return static::createFromJsonText(
                file_get_contents($url),
                $url,
                $documentClass
            );
        } else {
            $urlWithoutFragment = $url->withFragment('');

            return static::createFromJsonText(
                file_get_contents($urlWithoutFragment),
                $urlWithoutFragment,
                $documentClass
            )->getNode(JsonPtr::newFromString($fragment));
        }
    }
}
