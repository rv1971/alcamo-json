<?php

namespace alcamo\json;

use alcamo\uri\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\UriInterface;

/// JSON reference objects
class JsonReferenceNode extends JsonNode
{
    private $target_;

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

    public function isExternal(): bool
    {
        return $this->{'$ref'}[0] != '#';
    }

    public function getTarget()
    {
        if (!isset($this->target_)) {
            if ($this->{'$ref'}[0] == '#') {
                $this->target_ = $this->getOwnerDocument()
                    ->getNode(
                        JsonPtr::newFromString(substr($this->{'$ref'}, 1))
                    );
            } else {
                $this->target_ = $this->getOwnerDocument()
                    ->getDocumentFactory()
                    ->createFromUrl($this->resolveUri($this->{'$ref'}));
            }
        }

        return $this->target_;
    }

    protected function rebase(UriInterface $oldBase): void
    {
        $this->{'$ref'} = (string)UriResolver::resolve(
            $oldBase,
            new Uri($this->{'$ref'})
        );
    }
}
