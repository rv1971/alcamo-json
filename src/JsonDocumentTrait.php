<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;

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

    /**
     * @warning This method modifies $node. To import a copy, pass a clone.
     */
    public function importObjectNode(JsonNode $node, string $jsonPtr)
    {
        foreach (
            new RecursiveWalker(
                $node,
                RecursiveWalker::JSON_OBJECTS_ONLY
            ) as $subNode
        ) {
            $subNode->ownerDocument_ = $this;
            /** @todo recompute $jsonPtr_ */
        }
    }
}
