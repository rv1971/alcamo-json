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

    /// Do not change the current node
    public const SKIP = 1;

    /// Replace node by new one but do not apply recursive resolution to it
    public const STOP_RECURSION = 2;

    /// Replace node by new one and continue recursive resolution
    public const CONTINUE_RECURSION = 3;

    private $flags_; ///< int

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

    public function resolve($startNode)
    {
        $result =
            $this->resolveRecursively($startNode, $this->flags_, new Set());

        if ($startNode instanceof JsonNnode && $result !== $startNode) {
            $startNode->getOwnerDocument()
                ->setNode($startNode->getJsonPtr(), $result);
        }

        return $result;
    }

    /**
     * @brief Resolve an internal URL.
     *
     * @param $node A node which is an internal JSON reference.
     *
     * @param[out] $action one of
     * - @ref SKIP
     * - @ref STOP_RECURSION
     * - @ref CONTINUE_RECURSION
     *
     * This implementation calls JsonDocument::getNode() (which may be a
     * JsonNode object, an array or a primitive type), creates a clone of
     * it if it is a JsonNode, and asks the caller to continue recursive
     * resolution on the new node.
     *
     * Child classes may override this to return a modified node
     * (e.g. containing information about its source), to leave the node
     * unchanged, or to prevent further recursion on this node.
     *
     * @warning When overriding this method to return a JsonNode from $node's
     * owner document, a clone must be created.
     */
    protected function resolveInternalRef(
        JsonReferenceNode $node,
        ?int &$action
    ) {
        $newNode = $node->getTarget();

        if ($newNode instanceof JsonNode) {
            $newNode = clone $newNode;
        }

        $action = self::CONTINUE_RECURSION;

        return $newNode;
    }

    /**
     * @brief Resolve an external URL.
     *
     * @param $node A node which is a JSON reference.
     *
     * @param[out] $action one of
     * - @ref SKIP
     * - @ref STOP_RECURSION
     * - @ref CONTINUE_RECURSION
     *
     * This implementation calls JsonDocumentFactory::createFromUrl() to
     * create a new node (which may be a JsonNode object, an array or a
     * primitive type) and asks the caller to continue
     * recursive resolution on the new node.
     *
     * Child classes may override this to return a modified node
     * (e.g. containing information about its source), to leave the node
     * unchanged, or to prevent further recursion on this node.
     */
    protected function resolveExternalRef(
        JsonReferenceNode $node,
        ?int &$action
    ) {
        $loadedObject = $node->getTarget();

        $action = self::CONTINUE_RECURSION;

        return $loadedObject instanceof JsonDocument
            ? $loadedObject->getRoot()
            : $loadedObject;
    }

    private function resolveRecursively(
        $node,
        int $flags,
        Set $history
    ) {
        $ownerDocument = $node->getOwnerDocument();

        $walker =
            new RecursiveWalker($node, RecursiveWalker::JSON_OBJECTS_ONLY);

        foreach ($walker as $jsonPtrString => $pair) {
            [ $jsonPtr, $subNode ] = $pair;

            if (!($subNode instanceof JsonReferenceNode)) {
                continue;
            }

            $ref = $subNode->{'$ref'};

            $isExternal = $subNode->isExternal();

            /* Action must be set by resolveInternalRef() or
               resolveExternalRef(). */
            $action = null;

            switch (true) {
                case !$isExternal && $flags & self::RESOLVE_INTERNAL:
                    $newNode = $this->resolveInternalRef($subNode, $action);

                    if ($action == self::SKIP) {
                        continue 2;
                    }

                    break;

                case $isExternal && $flags & self::RESOLVE_EXTERNAL:
                    $newNode = $this->resolveExternalRef($subNode, $action);

                    if ($action == self::SKIP) {
                        continue 2;
                    }

                    break;

                default:
                    continue 2;
            }

            if ($history->contains([ $jsonPtrString, $ref ])) {
                throw (new Recursion())->setMessageContext(
                    [
                        'atUri' => (string)$node->getUri(),
                        'extraMessage' =>
                        "attempting to resolve \"$ref\" at \"$jsonPtr\" "
                        . "for the second time"
                    ]
                );
            }

            /* When importing an external node, any internal references in
             * that node must be resolved even when not requested by $flags,
             * because after import this might not be possible any more. Even
             * when an entire external JSON document is imported, the JSON
             * pointers do not work any more after import into a place which
             * is not the document root. */

            if ($newNode instanceof JsonNode) {
                $nextHistory = clone $history;
                $nextHistory->add([ $jsonPtrString, $ref ]);

                if ($action == self::CONTINUE_RECURSION) {
                    $newNode = $this->resolveRecursively(
                        $newNode,
                        $isExternal ? self::RESOLVE_ALL : $flags,
                        $nextHistory
                    );
                }
            } elseif (is_array($newNode)) {
                $nextHistory = clone $history;
                $nextHistory->add([ $jsonPtrString, $ref ]);

                if ($action == self::CONTINUE_RECURSION) {
                    $newNode = $this->resolveInArray(
                        $newNode,
                        $isExternal ? self::RESOLVE_ALL : $flags,
                        $nextHistory
                    );
                }
            }

            /* COPY_UPON_IMPORT is necessary because the nodes might get a
             * different PHP class upon import. */
            if ($newNode instanceof JsonNode) {
                $newNode = JsonNode::importObjectNode(
                    $ownerDocument,
                    $newNode,
                    $jsonPtr,
                    JsonNode::COPY_UPON_IMPORT,
                    $subNode->getParent()
                );
            } elseif (is_array($newNode)) {
                $newNode = JsonNode::importArrayNode(
                    $ownerDocument,
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

        foreach ($walker as $pair) {
            $walker->replaceCurrent(
                $this->resolveRecursively($pair[1], $flags, $history)
            );

            $walker->skipChildren();
        }

        return $node;
    }
}
