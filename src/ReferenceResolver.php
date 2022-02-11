<?php

namespace alcamo\json;

use alcamo\exception\Recursion;
use Ds\Set;
use Psr\Http\Message\UriInterface;

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

    /**
     * @brief Resolve an internal URL.
     *
     * @param $node A node which is a JSON reference.
     *
     * @param[out] $action one of
     * - @ref SKIP
     * - @ref STOP_RECURSION
     * - @ref CONTINUE_RECURSION
     *
     * This implementation calls JsonDocument::getNode() to create a new node
     * (which may be a JsonNode object, an array or a primitive type), creates
     * a deep copy of it if it is a JsonNode, and asks the caller to continue
     * recursive resolution on the new node.
     *
     * Child classes may override this to return a modified node
     * (e.g. containing information about its source), to leave the node
     * unchanged, or to prevent further rercursion on this node.
     *
     * @warning When overriding this method to return a JsonNode from $node's
     * owner document, a deep copy must be created.
     */
    public function resolveInternalRef(JsonNode $node, ?int &$action)
    {
        $newNode =
            $node->getOwnerDocument()->getNode(substr($node->{'$ref'}, 1));

        if ($newNode instanceof JsonNode) {
            $newNode = $newNode->createDeepCopy();
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
     * unchanged, or to prevent further rercursion on this node.
     */
    public function resolveExternalRef(JsonNode $node, ?int &$action)
    {
        $action = self::CONTINUE_RECURSION;

        return $node->getOwnerDocument()->getDocumentFactory()
            ->createFromUrl($node->resolveUri($node->{'$ref'}));
    }

    private function internalResolve(JsonNode $node, int $flags, Set $history)
    {
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

            // must be set by resolveInternalRef() or resolveExternalRef()
            $action = null;

            switch (true) {
                case $ref[0] == '#' && $flags & self::RESOLVE_INTERNAL:
                    $isExternal = false;

                    $newNode = $this->resolveInternalRef($subNode, $action);

                    if ($action == self::SKIP) {
                        continue 2;
                    }

                    break;

                case $ref[0] != '#' && $flags & self::RESOLVE_EXTERNAL:
                    $isExternal = true;

                    /* The new document must not be created in its final place
                     * in the existing document, because then it might be
                     * impossible to resolve references inside it. So it must
                     * first be created as a standalone document and later be
                     * imported. */

                    $newNode = $this->resolveExternalRef($subNode, $action);

                    if ($action == self::SKIP) {
                        continue 2;
                    }

                    break;

                default:
                    continue 2;
            }

            if ($history->contains([ $jsonPtr, $ref ])) {
                throw (new Recursion())->setMessageContext(
                    [
                        'atUri' =>
                        "{$node->getOwnerDocument()->getUri()}#$jsonPtr",
                        'extraMessage' =>
                        "attempting to resolve \"$ref\" for the second time"
                    ]
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

                if ($action == self::CONTINUE_RECURSION) {
                    $newNode = $this->internalResolve(
                        $newNode,
                        $isExternal ? self::RESOLVE_ALL : $flags,
                        $nextHistory
                    );
                }
            } elseif (is_array($newNode)) {
                $nextHistory = clone $history;
                $nextHistory->add([ $jsonPtr, $ref ]);

                if ($action == self::CONTINUE_RECURSION) {
                    $newNode = $this->resolveInArray(
                        $newNode,
                        $isExternal ? self::RESOLVE_ALL : $flags,
                        $nextHistory
                    );
                }
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
