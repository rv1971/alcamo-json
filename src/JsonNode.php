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

use alcamo\exception\Recursion;
use alcamo\ietf\Uri;
use Ds\Set;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\UriInterface;

/**
 * @brief Object node in a JSON tree
 */
class JsonNode
{
    public const COPY_UPON_IMPORT = 1; ///< Clone nodes in import methods

    public const RESOLVE_INTERNAL = 1; ///< Resolve refs within the document
    public const RESOLVE_EXTERNAL = 2; ///< Resolve refs outside the document
    public const RESOLVE_ALL      = 3; ///< Resolve all refs

    private $ownerDocument_;   ///< self
    private $jsonPtr_;         ///< string
    private $baseUri_;         ///< ?UriInterface

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
        $data,
        ?self $ownerDocument = null,
        ?string $jsonPtr = null,
        ?UriInterface $baseUri = null
    ) {
        $this->ownerDocument_ = $ownerDocument ?? $this;

        $this->jsonPtr_ = $jsonPtr ?? '/';

        $this->baseUri_ = $baseUri ?? $ownerDocument->baseUri_ ?? null;

        if ($data instanceof self) {
            foreach ((array)$data as $key => $value) {
                if ($key[0] != "\0") {
                    $this->$key = $value;
                }
            }
        } else {
            foreach ($data as $subKey => $value) {
                $this->$subKey = $this->createNode(
                    ($this->jsonPtr_ == '/' ? '/' : "$this->jsonPtr_/")
                    . str_replace([ '~', '/' ], [ '~0', '~1' ], $subKey),
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
    public function getJsonPtr(): string
    {
        return $this->jsonPtr_;
    }

    /// Base URI, if specified
    public function getBaseUri(): ?UriInterface
    {
        return $this->baseUri_;
    }

    public function toJsonText(?int $flags = null, ?int $depth = null): string
    {
        return json_encode($this, $flags ?? 0, $depth ?? 512);
    }

    public function createDeepCopy(): self
    {
        $node = clone $this;

        /** If getJsonPtr() is `/`, the copy is its own owner
         *  document. Otherwise it belongs to the same document as the
         *  original node. */
        if ($node->jsonPtr_ == '/') {
            $node->ownerDocument_ = $node;
        }

        $walker = new RecursiveWalker(
            $node,
            RecursiveWalker::JSON_OBJECTS_ONLY
            | RecursiveWalker::OMIT_START_NODE
        );

        foreach ($walker as $subNode) {
            $subNode = clone $subNode;
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
    public function createNode(string $jsonPtr, $value)
    {
        switch (true) {
            case is_array($value)
                && (isset($value[0]) || array_key_exists(0, $value))
                && array_keys($value) === range(0, count($value) - 1):
                $result = [];

                foreach ($value as $subKey => $subValue) {
                    $result[] =
                        $this->createNode("$jsonPtr/$subKey", $subValue);
                }

                return $result;

            case is_object($value) || is_array($value):
                $class = $this->ownerDocument_
                    ->getExpectedNodeClass($jsonPtr, $value);

                return new $class($value, $this->ownerDocument_, $jsonPtr);

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
        string $jsonPtr,
        ?int $flags = null
    ): self {
        if ($flags & self::COPY_UPON_IMPORT) {
            $class = $this->ownerDocument_
                ->getExpectedNodeClass($jsonPtr, $node);

            $node = new $class(
                $node,
                $this->ownerDocument_,
                $jsonPtr,
                $node->baseUri_
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

        foreach ($walker as $jsonPtr => $subNode) {
            if ($flags & self::COPY_UPON_IMPORT) {
                $class = $this->ownerDocument_
                    ->getExpectedNodeClass($jsonPtr, $subNode);

                $subNode = new $class(
                    $subNode,
                    $this->ownerDocument_,
                    $jsonPtr,
                    $subNode->baseUri_
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
        string $jsonPtr,
        ?int $flags = null
    ): array {
        $walker =
            new RecursiveWalker($node, RecursiveWalker::JSON_OBJECTS_ONLY);

        foreach ($walker as $jsonPtrFragment => $subNode) {
            if ($flags & self::COPY_UPON_IMPORT) {
                $class = $this->ownerDocument_
                    ->getExpectedNodeClass($jsonPtr, $subNode);

                $subNode = new $class(
                    $subNode,
                    $this->ownerDocument_,
                    "$jsonPtr/$jsonPtrFragment",
                    $subNode->baseUri_
                );

                $walker->replaceCurrent($subNode);
            } else {
                $subNode->ownerDocument_ = $this->ownerDocument_;
                $subNode->jsonPtr_ = "$jsonPtr/$jsonPtrFragment";
            }
        }

        return $node;
    }

    /**
     * @param $flags One of
     * - @ref RESOLVE_INTERNAL
     * - @ref RESOLVE_EXTERNAL
     * - @ref RESOLVE_ALL
     *
     * @param $mayReplaceNode Whether this function is allowed to replace
     * $this in the JSON tree.
     *
     * @warning Event though this method may modify $this, the return value
     * may be different from $this and does not even need to be an object.
     */
    public function resolveReferences(
        int $flags = self::RESOLVE_ALL,
        bool $mayReplaceNode = true,
        ?Set $replacementHistory = null
    ) {
        $factory = $this->ownerDocument_->getDocumentFactory();

        $result = $this;

        if (!isset($replacementHistory)) {
            $replacementHistory = new Set();
        }

        $walker =
            new RecursiveWalker($result, RecursiveWalker::JSON_OBJECTS_ONLY);

        foreach ($walker as $jsonPtr => $node) {
            if (!isset($node->{'$ref'}) || !is_string($node->{'$ref'})) {
                continue;
            }

            $ref = $node->{'$ref'};

            switch (true) {
                case $ref[0] == '#' && $flags & self::RESOLVE_INTERNAL:
                    $newNode =
                        $result->ownerDocument_->getNode(substr($ref, 1));

                    if ($newNode instanceof self) {
                        $newNode = $newNode->createDeepCopy();
                    }

                    break;

                case $ref[0] != '#' && $flags & self::RESOLVE_EXTERNAL:
                    /* The new document must not be created in its final place
                     * in the existing document, because then it might be
                     * impossible to resolve references inside it. So it must
                     * first be created as a standalone document and later be
                     * imported. */

                    $newNode = $factory->createFromUrl(
                        UriResolver::resolve($node->baseUri_, new Uri($ref))
                    );

                    break;

                default:
                    continue 2;
            }

            if ($replacementHistory->contains([ $jsonPtr, $ref ])) {
                throw new Recursion(
                    "Recursion detected: attempting to resolve "
                    . "$ref at $jsonPtr for the second time"
                );
            }

            $nextReplacementHistory = clone $replacementHistory;
            $nextReplacementHistory->add([ $jsonPtr, $ref ]);

            if ($newNode instanceof self) {
                $newNode = $newNode->resolveReferences(
                    $flags,
                    false,
                    $nextReplacementHistory
                );
            } elseif (is_array($newNode)) {
                $newNode =
                    $this->resolveReferencesInArray(
                        $newNode,
                        $flags,
                        $nextReplacementHistory
                    );
            }

            /* COPY_UPON_IMPORT is necessary because the nodes might get a
             * different PHP class upon copying. */
            if ($newNode instanceof self) {
                $newNode = $result->importObjectNode(
                    $newNode,
                    $jsonPtr,
                    self::COPY_UPON_IMPORT
                );
            } elseif (is_array($newNode)) {
                $newNode = $result->importArrayNode(
                    $newNode,
                    $jsonPtr,
                    self::COPY_UPON_IMPORT
                );
            }

            $walker->replaceCurrent($newNode);
            $walker->skipChildren();
        }

        if ($mayReplaceNode && $result !== $this && $this->jsonPtr_ != '/') {
            $this->ownerDocument_->setNode($this->jsonPtr_, $result);
        }

        return $result;
    }

    private function resolveReferencesInArray(
        array $node,
        int $flags,
        Set $replacementHistory
    ) {
        $walker =
            new RecursiveWalker($node, RecursiveWalker::JSON_OBJECTS_ONLY);

        foreach ($walker as $subNode) {
            $walker->replaceCurrent(
                $subNode->resolveReferences($flags, false, $replacementHistory)
            );

            $walker->skipChildren();
        }

        return $node;
    }
}
