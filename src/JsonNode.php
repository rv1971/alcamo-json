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
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\UriInterface;

/**
 * @brief Object node in a JSON tree
 */
class JsonNode
{
    public const COPY_UPON_IMPORT = 1; ///< Clone nodes in import methods

    private $baseUri_;         ///< ?UriInterface
    private $ownerDocument_;   ///< self
    private $jsonPtr_;         ///< JsonPtr

    /**
     * @brief Construct from object or iterable, creating a public property
     * for each key
     *
     * @param $ownerDocument Document this node belongs to. If unset, the node
     * is considered to be itself its owner document.
     *
     * @param $jsonPtr JSON pointer to this node. If unset, the node is
     * assumed to be the root node.
     *
     * @param $data If JsonNode, a shallow copy of all its public properties
     * is created. (Use createDeepCopy() beforehand if you need a deep copy.)
     * Otherwise use createNode() to build a JSON tree recursively.
     *
     * @param $baseUri URI used to to resolve any relative URIs in the
     * document.
     */
    public function __construct(
        object $data,
        ?UriInterface $baseUri = null,
        ?self $ownerDocument = null,
        ?JsonPtr $jsonPtr = null
    ) {
        $this->baseUri_ = $baseUri ?? $ownerDocument->baseUri_ ?? null;

        $this->ownerDocument_ = $ownerDocument ?? $this;

        $this->jsonPtr_ = $jsonPtr ?? new JsonPtr();

        if ($data instanceof self) {
            foreach ((array)$data as $key => $value) {
                if (((string)$key)[0] != "\0") {
                    $this->$key = $value;
                }
            }
        } else {
            foreach ($data as $prop => $value) {
                $this->$prop = $this->createNode(
                    $this->jsonPtr_->appendSegment($prop),
                    $value
                );
            }
        }
    }

    /// Call toJsonText()
    public function __toString(): string
    {
        return $this->toJsonText();
    }

    /// Base URI, if specified
    public function getBaseUri(): ?UriInterface
    {
        return $this->baseUri_;
    }

    /**
     * @brief Get the document (i.e. the ultimate parent) this node belongs to
     *
     * The owner document does not need to be of a specific document type. It
     * can be a JsonNode or any class derived from it.
     */
    public function getOwnerDocument(): self
    {
        return $this->ownerDocument_;
    }

    /// JSON pointer identifying the present node
    public function getJsonPtr(): JsonPtr
    {
        return $this->jsonPtr_;
    }

    /**
     * @brief URI reference of this node
     *
     * @param $segment Extra segment to append to the JSON pointer. This is
     * useful to generate URIs for child nodes, especially if the child nodes
     * are not objects and therefore have no getUri() method.
     */
    public function getUri(?string $segment = null): UriInterface
    {
        $jsonPtr = isset($segment)
            ? $this->jsonPtr_->appendSegment($segment)
            : $this->jsonPtr_;

        return isset($this->ownerDocument_->baseUri_)
            ? $this->ownerDocument_->baseUri_->withFragment((string)$jsonPtr)
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

        return isset($this->baseUri_)
            ? UriResolver::resolve($this->baseUri_, $uri)
            : $uri;
    }

    public function toJsonText(?int $flags = null, ?int $depth = null): string
    {
        return json_encode($this, $flags ?? 0, $depth ?? 512);
    }

    public function createDeepCopy(): self
    {
        $node = clone $this;

        /** If getJsonPtr() is the root pointer, the copy is its own owner
         *  document. Otherwise it belongs to the same document as the
         *  original node. */
        if ($node->jsonPtr_->isRoot()) {
            $node->ownerDocument_ = $node;
        }

        $walker = new RecursiveWalker(
            $node,
            RecursiveWalker::JSON_OBJECTS_ONLY
            | RecursiveWalker::OMIT_START_NODE
        );

        foreach ($walker as $pair) {
            $subNode = clone $pair[1];
            $subNode->ownerDocument_ = $node->ownerDocument_;
            $walker->replaceCurrent($subNode);
        }

        return $node;
    }

    /**
     * @brief Create a JSON node
     * - If $value is a nonempty numerically-indexed array, create an array.
     * - Else, if $value is an object or associative array, call createNode()
     *   recursively.
     * - Else, use $value as-is.
     *
     * @sa [How to check if PHP array is associative or sequential?](https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential)
     */
    public function createNode(JsonPtr $jsonPtr, $value)
    {
        switch (true) {
            case is_array($value)
                && ($value == []
                    || (isset($value[0]) || array_key_exists(0, $value))
                    && array_keys($value) === range(0, count($value) - 1)):
                $result = [];

                foreach ($value as $prop => $subValue) {
                    $result[] = $this->createNode(
                        $jsonPtr->appendSegment($prop),
                        $subValue
                    );
                }

                return $result;

            case is_object($value) || is_array($value):
                $class = $this->ownerDocument_
                    ->getNodeClassToUse($jsonPtr, $value);

                return new $class(
                    (object)$value,
                    $this->baseUri_,
                    $this->ownerDocument_,
                    $jsonPtr
                );

            default:
                return $value;
        }
    }

    /**
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
     * prepares it so that it can then be inserted into the right place.
     *
     * Even though the only member of `$this` used in this method is @ref
     * $ownerDocument_, this method is implemented in JsonNode and not in
     * JsonDocumentTrait in order to have write access to $node's @ref
     * $ownerDocument_ and @ref $jsonPtr_.
     */
    public function importObjectNode(
        JsonNode $node,
        JsonPtr $jsonPtr,
        ?int $flags = null
    ): self {
        if ($flags & self::COPY_UPON_IMPORT) {
            $class = $this->ownerDocument_
                ->getNodeClassToUse($jsonPtr, $node);

            $node = new $class(
                $node,
                $node->baseUri_,
                $this->ownerDocument_,
                $jsonPtr
            );
        } else {
            $node->ownerDocument_ = $this->ownerDocument_;
            $node->jsonPtr_ = $jsonPtr;
        }

        $walker = new RecursiveWalker(
            $node,
            RecursiveWalker::JSON_OBJECTS_ONLY
            | RecursiveWalker::OMIT_START_NODE
        );

        foreach ($walker as $pair) {
            [ $jsonPtr, $subNode ] = $pair;

            if ($flags & self::COPY_UPON_IMPORT) {
                $class = $this->ownerDocument_
                    ->getNodeClassToUse($jsonPtr, $subNode);

                $subNode = new $class(
                    $subNode,
                    $subNode->baseUri_,
                    $this->ownerDocument_,
                    $jsonPtr
                );

                $walker->replaceCurrent($subNode);
            } else {
                $subNode->ownerDocument_ = $this->ownerDocument_;
                $subNode->jsonPtr_ = $jsonPtr;
            }
        }

        return $node;
    }

    /**
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
     *
     * Even though the only part of `$this` used in this method is @ref
     * $ownerDocument_, this method is implemented in JsonNode in order to
     * have write access to $node's @ref $ownerDocument_ and @ref $jsonPtr_.
     */
    public function importArrayNode(
        array $node,
        JsonPtr $jsonPtr,
        ?int $flags = null
    ): array {
        $walker =
            new RecursiveWalker($node, RecursiveWalker::JSON_OBJECTS_ONLY);

        foreach ($walker as $pair) {
            [ $jsonPtrSegments, $subNode ] = $pair;

            if ($flags & self::COPY_UPON_IMPORT) {
                $class = $this->ownerDocument_
                    ->getNodeClassToUse($jsonPtr, $subNode);

                $subNode = new $class(
                    $subNode,
                    $subNode->baseUri_,
                    $this->ownerDocument_,
                    $jsonPtr->appendSegments($jsonPtrSegments)
                );

                $walker->replaceCurrent($subNode);
            } else {
                $subNode->ownerDocument_ = $this->ownerDocument_;
                $subNode->jsonPtr_ = $jsonPtr->appendSegments($jsonPtrSegments);
            }
        }

        return $node;
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
}
