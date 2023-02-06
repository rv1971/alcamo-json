<?php

namespace alcamo\json;

use Psr\Http\Message\UriInterface;

interface JsonDocumentInterface
{
    /// Get URI used to to resolve any relative URIs in the document
    public function getBaseUri(): ?UriInterface;

    /// Get node identified by JSON pointer
    public function getNode(JsonPtr $jsonPtr);

    /// Set node identified by JSON pointer to new node
    public function setNode(JsonPtr $jsonPtr, $newNode): void;

    /// Get class that should be used to create a JSON object node
    public function getNodeClassToUse(JsonPtr $jsonPtr, object $value): string;
}
