<?php

namespace alcamo\json;

interface JsonDocumentInterface
{
    /// Get JSON node identified by JSON pointer
    public function getNode(JsonPtr $jsonPtr);

    /// Set JSON node identified by JSON pointer to new node
    public function setNode(JsonPtr $jsonPtr, $newNode): void;
}
