<?php

namespace alcamo\json;

use alcamo\exception\Recursion;
use Ds\Set;

/**
 * @brief Resolver of JSON references
 */
class ReferenceResolver
{
    public const RESOLVE_INTERNAL = 1; ///< Resolve refs within the document
    public const RESOLVE_EXTERNAL = 2; ///< Resolve refs outside the document
    public const RESOLVE_ALL      = 3; ///< Resolve all refs

    private $flags_;           ///< int

    /**
     * @param $flags One of
     * - @ref RESOLVE_INTERNAL
     * - @ref RESOLVE_EXTERNAL
     * - @ref RESOLVE_ALL
     */
    public function __construct(int $flags = self::RESOLVE_ALL)
    {
        $this->flags_ = $flags;
    }

    public function resolve(JsonNode $startNode)
    {
        $result = $this->internalResolve($startNode, $this->flags_, new Set());

        if ($result !== $startNode && $startNode->getJsonPtr() != '/') {
            $startNode->getOwnerDocument()
                ->setNode($startNode->getJsonPtr(), $result);
        }

        return $result;
    }

    private function internalResolve(JsonNode $node, int $flags, Set $history)
    {
        $factory = $node->getOwnerDocument()->getDocumentFactory();

        $walker =
            new RecursiveWalker($node, RecursiveWalker::JSON_OBJECTS_ONLY);

        foreach ($walker as $jsonPtr => $subNode) {
            if (!isset($subNode->{'$ref'})) {
                continue;
            }

            $ref = $subNode->{'$ref'};

            /* In a JSON schema document, `$ref` might be a key that is being
             * defined instead of indicating a reference object, in which case
             * its value is an object rather than a string. */
            if (!is_string($ref)) {
                continue;
            }

            switch (true) {
                case $ref[0] == '#' && $flags & self::RESOLVE_INTERNAL:
                    $isExternal = false;

                    $newNode =
                        $subNode->getOwnerDocument()->getNode(substr($ref, 1));

                    if ($newNode instanceof JsonNode) {
                        $newNode = $newNode->createDeepCopy();
                    }

                    break;

                case $ref[0] != '#' && $flags & self::RESOLVE_EXTERNAL:
                    $isExternal = true;

                    /* The new document must not be created in its final place
                     * in the existing document, because then it might be
                     * impossible to resolve references inside it. So it must
                     * first be created as a standalone document and later be
                     * imported. */

                    $newNode =
                        $factory->createFromUrl($subNode->resolveUri($ref));

                    break;

                default:
                    continue 2;
            }

            if ($history->contains([ $jsonPtr, $ref ])) {
                throw new Recursion(
                    "Recursion detected: attempting to resolve "
                    . "$ref at $jsonPtr for the second time"
                );
            }

            /* When importing an external node, any internal references in
             * that node must be resolved even when not requested by $flags
             * because after import this might not be possible any more. Even
             * when an entire external JSON document is imported, the JSON
             * pointers do not work any more after import into a place which
             * is not the document root. */

            if ($newNode instanceof JsonNode) {
                $nextHistory = clone $history;
                $nextHistory->add([ $jsonPtr, $ref ]);

                $newNode = $this->internalResolve(
                    $newNode,
                    $isExternal ? self::RESOLVE_ALL : $flags,
                    $nextHistory
                );
            } elseif (is_array($newNode)) {
                $nextHistory = clone $history;
                $nextHistory->add([ $jsonPtr, $ref ]);

                $newNode = $this->resolveInArray(
                    $newNode,
                    $isExternal ? self::RESOLVE_ALL : $flags,
                    $nextHistory
                );
            }

            /* COPY_UPON_IMPORT is necessary because the nodes might get a
             * different PHP class upon copying. */
            if ($newNode instanceof JsonNode) {
                $newNode = $node->importObjectNode(
                    $newNode,
                    $jsonPtr,
                    JsonNode::COPY_UPON_IMPORT
                );
            } elseif (is_array($newNode)) {
                $newNode = $node->importArrayNode(
                    $newNode,
                    $jsonPtr,
                    JsonNode::COPY_UPON_IMPORT
                );
            }

            $walker->replaceCurrent($newNode);
            $walker->skipChildren();
        }

        return $node;
    }

    private function resolveInArray(array $node, int $flags, Set $history)
    {
        $walker =
            new RecursiveWalker($node, RecursiveWalker::JSON_OBJECTS_ONLY);

        foreach ($walker as $subNode) {
            $walker->replaceCurrent(
                $this->internalResolve($subNode, $flags, $history)
            );

            $walker->skipChildren();
        }

        return $node;
    }
}
