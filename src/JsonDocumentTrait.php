<?php

namespace alcamo\json;

use alcamo\exception\{DataValidationFailed, SyntaxError};

/**
 * @brief JSON document trait
 *
 * Implemented as a trait so that new document classes can be implemented as
 * child classes of classes derived from JsonNode that use the present trait.
 */
trait JsonDocumentTrait
{
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

    /// Check the internal structure, for debugging only
    public function checkStructure(): void
    {
        foreach (
            new RecursiveWalker(
                $this,
                RecursiveWalker::JSON_OBJECTS_ONLY
            ) as $jsonPtr => $node
        ) {
            if ($node->getOwnerDocument() !== $this) {
                /** @throw alcamo::exception::DataValidationFailed if node
                 *  has a wrong owner document. */
                throw new DataValidationFailed(
                    $node,
                    "{$this->getBaseUri()}#$jsonPtr",
                    null,
                    "; \$ownerDocument_ differs from document owning this node"
                );
            }

            if ($node->getJsonPtr() !== $jsonPtr) {
                /** @throw alcamo::exception::DataValidationFailed if node
                 *  has a wrong JSOn pointer. */
                throw new DataValidationFailed(
                    $node,
                    "{$this->getBaseUri()}#$jsonPtr",
                    null,
                    "; \$jsonPtr_=\"{$node->getJsonPtr()}\" differs from actual position \"$jsonPtr\""
                );
            }
        }
    }
}
