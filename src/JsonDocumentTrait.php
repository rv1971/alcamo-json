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
    public static function newFromJsonText(
        string $jsonText,
        ?int $depth = null,
        ?int $flags = null
    ): self {
        return new static(
            json_decode($jsonText, false, $depth ?? 512, $flags ?? 0)
        );
    }

    /// Get JSON node identified by JSON pointer
    public function getNode(string $jsonPtr)
    {
        if ($jsonPtr[0] != '/') {
            /** @throw alcamo:.exception::SyntaxError if $jsonPtr does not
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
}
