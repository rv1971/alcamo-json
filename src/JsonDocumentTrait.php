<?php

namespace alcamo\json;

use alcamo\exception\{DataValidationFailed, SyntaxError, Unsupported};

/**
 * @brief JSON document trait
 *
 * Implemented as a trait so that new document classes can be implemented as
 * child classes of classes derived from JsonNode that use the present trait.
 */
trait JsonDocumentTrait
{
    private $documentFactory_; ///< JsonDocumentFactory

    public function getDocumentFactory(): JsonDocumentFactory
    {
        if (!isset($this->documentFactory_)) {
            $this->documentFactory_ = new JsonDocumentFactory();
        }

        return $this->documentFactory_;
    }

    public function setDocumentFactory(
        JsonDocumentFactory $documentFactory
    ): void {
        $this->documentFactory_ = $documentFactory;
    }

    /// Get JSON node identified by JSON pointer
    public function getNode(string $jsonPtr)
    {
        if ($jsonPtr[0] != '/') {
            /** @throw alcamo::exception::SyntaxError if $jsonPtr does not
             *  start with a slash. */
            throw new SyntaxError($jsonPtr, 0, '; not a valid JSON pointer');
        }

        $current = $this;

        for (
            $refToken = strtok($jsonPtr, '/');
            $refToken !== false;
            $refToken = strtok('/')
        ) {
            if (is_object($current)) {
                $refToken =
                    str_replace([ '~1', '~0' ], [ '/', '~' ], $refToken);

                $current = $current->$refToken;
            } else {
                $current = $current[$refToken];
            }
        }

        return $current;
    }

    /// Set JSON node identified by JSON pointer to new node
    public function setNode(string $jsonPtr, $newNode): void
    {
        if ($jsonPtr[0] != '/') {
            /** @throw alcamo::exception::SyntaxError if $jsonPtr does not
             *  start with a slash. */
            throw new SyntaxError($jsonPtr, 0, '; not a valid JSON pointer');
        }

        if ($jsonPtr == '/') {
            /** @throw alcamo::exception::Unsupported when attempting to
             *  replace the root node. */
            throw new Unsupported('"/"', '; root node cannot be replaced');
        }

        $current = new ReferenceContainer($this);

        for (
            $refToken = strtok($jsonPtr, '/');
            $refToken !== false;
            $refToken = strtok('/')
        ) {
            if ($current->value instanceof JsonNode) {
                $refToken =
                    str_replace([ '~1', '~0' ], [ '/', '~' ], $refToken);

                $current = new ReferenceContainer($current->value->$refToken);
            } else {
                $current = new ReferenceContainer($current->value[$refToken]);
            }
        }

        $current->value = $newNode;
    }

    /// Get class that should be used to create a node
    public function getExpectedNodeClass(string $jsonPtr): string
    {
        return JsonNode::class;
    }
}
