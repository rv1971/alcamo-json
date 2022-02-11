<?php

namespace alcamo\json;

interface JsonDocumentInterface
{
    /// Get JSON node identified by JSON pointer
    public function getNode(string $jsonPtr);

    /// Set JSON node identified by JSON pointer to new node
    public function setNode(string $jsonPtr, $newNode): void;
}
