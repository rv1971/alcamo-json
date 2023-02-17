<?php

/**
 * @namespace alcamo::json
 *
 * @brief Easy-to-use JSON documents with JSON pointer support
 *
 * @sa [JSON](https://datatracker.ietf.org/doc/html/rfc7159)
 * @sa [JSON Pointer](https://datatracker.ietf.org/doc/html/rfc6901)
 */

namespace alcamo\json;

use alcamo\uri\Uri;
use Ds\Map;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\UriInterface;

/// Object node in a JSON tree
class JsonNode
{
    public const COPY_UPON_IMPORT = 1; ///< Clone nodes in import methods

    private $ownerDocument_; ///< JsonDocument
    private $jsonPtr_;       ///< JsonPtr
    private $parent_;        ///< ?self

    /**
     * @param $ownerDocument Document to import into
     *
     * @param $node Node to import into the current document
     *
     * @param $jsonPtr JSON pointer pointing to the new node
     *
     * @param $flags 0 or @ref COPY_UPON_IMPORT
     *
     * @warning This method may modify $node or children thereof unless @ref
     * COPY_UPON_IMPORT is given in $flags.
     *
     * @note This method does not insert the node into the tree. It only
     * prepares it so that it can then be inserted into the position indicated
     * by $jsonPtr.
     */
    public static function importObjectNode(
        JsonDocument $ownerDocument,
        JsonNode $node,
        JsonPtr $jsonPtr,
        ?int $flags = null,
        ?self $parent = null
    ): self {
        $oldBaseUri = $node->ownerDocument_->getBaseUri();

        /* Rebase only if base URI has effectively changed. */
        if (
            isset($oldBaseUri)
            && (string)$oldBaseUri == (string)$ownerDocument->getBaseUri()
        ) {
            $oldBaseUri = null;
        }

        if ($flags & self::COPY_UPON_IMPORT) {
            $class = $ownerDocument->getNodeClassToCreate($jsonPtr, $node);

            $oldNewMap = new Map();

            $newNode = new $class($node, $ownerDocument, $jsonPtr, $parent);

            $oldNewMap[$node] = $newNode;

            $node = $newNode;
        } else {
            $node->ownerDocument_ = $ownerDocument;
            $node->jsonPtr_ = $jsonPtr;
            $node->parent_ = $parent;
        }

        if (isset($oldBaseUri)) {
            $node->rebase($oldBaseUri);
        }

        $walker = new RecursiveWalker(
            $node,
            RecursiveWalker::JSON_OBJECTS_ONLY
            | RecursiveWalker::OMIT_START_NODE
        );

        foreach ($walker as $pair) {
            [ $jsonPtr, $subNode ] = $pair;

            if ($flags & self::COPY_UPON_IMPORT) {
                $class = $ownerDocument
                    ->getNodeClassToCreate($jsonPtr, $subNode);

                $newNode = new $class(
                    $subNode,
                    $ownerDocument,
                    $jsonPtr,
                    isset($subNode->parent_)
                    ? $oldNewMap[$subNode->parent_]
                    : null
                );

                $oldNewMap[$subNode] = $newNode;

                $walker->replaceCurrent($newNode);

                if (isset($oldBaseUri)) {
                    $newNode->rebase($oldBaseUri);
                }
            } else {
                $subNode->ownerDocument_ = $ownerDocument;
                $subNode->jsonPtr_ = $jsonPtr;

                if (isset($oldBaseUri)) {
                    $subNode->rebase($oldBaseUri);
                }
            }
        }

        return $node;
    }

    /**
     * @param $ownerDocument Document to import into
     *
     * @param $node Array to import into the current document
     *
     * @param $jsonPtr JSON pointer pointing to the new node
     *
     * @param $flags 0 or @ref COPY_UPON_IMPORT
     *
     * @warning This method may modify children of $node unless @ref
     * COPY_UPON_IMPORT is given in $flags.
     *
     * @note This method does not insert the node into the tree. It only
     * prepares it so that it can then be inserted into the right place.
     */
    public static function importArrayNode(
        JsonDocument $ownerDocument,
        array $node,
        JsonPtr $jsonPtr,
        ?int $flags = null
    ): array {
        $walker =
            new RecursiveWalker($node, RecursiveWalker::JSON_OBJECTS_ONLY);

        $oldNewMap = new Map();

        foreach ($walker as $pair) {
            [ $jsonPtrSegments, $subNode ] = $pair;

            if (!isset($rebaseIsNeeded)) {
                $oldBaseUri = $subNode->ownerDocument_->getBaseUri();

                /* Rebase only if base URI has effectively changed. */
                $rebaseIsNeeded = isset($oldBaseUri)
                    && ((string)$oldBaseUri
                        != (string)$ownerDocument->getBaseUri());
            }

            if ($flags & self::COPY_UPON_IMPORT) {
                $class = $ownerDocument
                    ->getNodeClassToCreate($jsonPtr, $subNode);

                $newNode = new $class(
                    $subNode,
                    $ownerDocument,
                    $jsonPtr->appendSegments($jsonPtrSegments),
                    isset($subNode->parent_)
                    ? $oldNewMap[$subNode->parent_]
                    : null
                );

                $oldNewMap[$subNode] = $newNode;

                $walker->replaceCurrent($newNode);

                if ($rebaseIsNeeded) {
                    $newNode->rebase($oldBaseUri);
                }
            } else {
                $subNode->ownerDocument_ = $ownerDocument;
                $subNode->jsonPtr_ = $jsonPtr->appendSegments($jsonPtrSegments);

                if ($rebaseIsNeeded) {
                    $subNode->rebase($oldBaseUri);
                }
            }
        }

        return $node;
    }

    /**
     * @brief Construct from object, creating a public property for each key
     *
     * @param $data If JsonNode, a shallow copy of all its public properties
     * is created. (Use `clone` if you need a deep copy.) Otherwise use
     * JsonDocument::createNode() to build a JSON tree recursively.
     *
     * @param $ownerDocument Document this node belongs to.
     *
     * @param $jsonPtr JSON pointer to this node.
     *
     * @param $parent Parent node, if any.
     */
    public function __construct(
        object $data,
        JsonDocument $ownerDocument,
        JsonPtr $jsonPtr,
        ?JsonNode $parent = null
    ) {
        $this->ownerDocument_ = $ownerDocument;
        $this->jsonPtr_ = $jsonPtr;
        $this->parent_ = $parent;

        if ($data instanceof self) {
            foreach ((array)$data as $prop => $value) {
                // copy public properties only
                if (((string)$prop)[0] != "\0") {
                    $this->$prop = $value;
                }
            }
        } else {
            foreach ((array)$data as $prop => $value) {
                // copy public properties only
                if (((string)$prop)[0] != "\0") {
                    $this->$prop = $ownerDocument->createNode(
                        $value,
                        $jsonPtr->appendSegment($prop),
                        $this
                    );
                }
            }
        }
    }

    public function __clone()
    {
        /* Map of old to new nodes, needed to replace parent nodes. */
        $oldNewMap = new Map();

        /* Retrieve the node the current one was cloned from, if possible. */
        foreach ((array)$this as $prop => $child) {
            if (((string)$prop)[0] != "\0" && $child instanceof self) {
                $oldNewMap[$child->parent_] = $this;
                break;
            }
        }

        /* Recursively replace descendent JSON objects by clones. */
        $walker = new RecursiveWalker(
            $this,
            RecursiveWalker::JSON_OBJECTS_ONLY
            | RecursiveWalker::OMIT_START_NODE
        );

        foreach ($walker as $pair) {
            $class = get_class($pair[1]);

            $subNode = new $class(
                $pair[1],
                $this->ownerDocument_,
                $pair[1]->jsonPtr_,
                $oldNewMap[$pair[1]->parent_] ?? null
            );

            $walker->replaceCurrent($subNode);

            $oldNewMap[$pair[1]] = $subNode;
        }
    }

    /// Call toJsonText()
    public function __toString(): string
    {
        return $this->toJsonText();
    }

    /// Get the document (i.e. the ultimate parent) this node belongs to
    public function getOwnerDocument(): JsonDocument
    {
        return $this->ownerDocument_;
    }

    /// JSON pointer identifying the present node
    public function getJsonPtr(): JsonPtr
    {
        return $this->jsonPtr_;
    }

    /// Parent node, if any
    public function getParent(): ?self
    {
        return $this->parent_;
    }

    /**
     * @brief URI reference of this node
     *
     * @param $segment Extra segment to append to the JSON pointer. This is
     * useful to generate URIs for child nodes, especially if the child nodes
     * are not objects and therefore have no getUri() method of their own.
     */
    public function getUri(?string $segment = null): UriInterface
    {
        $jsonPtr = isset($segment)
            ? $this->jsonPtr_->appendSegment($segment)
            : $this->jsonPtr_;

        $baseUri = $this->ownerDocument_->getBaseUri();

        return isset($baseUri)
            ? $baseUri->withFragment((string)$jsonPtr)
            : new Uri("#$jsonPtr");
    }

    /**
     * @brief Resolve potentially relative URI against base URI
     *
     * Leave $uri unchanged if base URI is not set.
     */
    public function resolveUri($uri): UriInterface
    {
        if (!($uri instanceof UriInterface)) {
            $uri = new Uri($uri);
        }

        $baseUri = $this->ownerDocument_->getBaseUri();

        return isset($baseUri)
            ? UriResolver::resolve($baseUri, $uri)
            : $uri;
    }

    public function toJsonText(?int $flags = null, ?int $depth = null): string
    {
        return json_encode($this, $flags ?? 0, $depth ?? 512);
    }

    /**
     * @param $resolver A ReferenceResolver object, or one of the
     * ReferenceResolver constants which is the used to construct a
     * ReferenceResolver object.
     *
     * @warning Even though this method may modify $this, the return value
     * may be different from $this and does not even need to be an object.
     */
    public function resolveReferences(
        $resolver = ReferenceResolver::RESOLVE_ALL
    ) {
        if (!($resolver instanceof ReferenceResolver)) {
            $resolver = new ReferenceResolver($resolver);
        }

        return $resolver->resolve($this);
    }

    /**
     * @brief Do any necessary modifications after change of document base URI
     *
     * Typically this modifies any properties containing relative URIs.
     */
    protected function rebase(UriInterface $oldBase): void
    {
    }
}
