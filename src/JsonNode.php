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

use alcamo\ietf\Uri;
use Psr\Http\Message\UriInterface;

/**
 * @brief Object node in a JSON tree
 */
class JsonNode
{
    public const CLONE_UPON_IMPORT = 1; ///< Clone nodes in import methods

    public const RESOLVE_INTERNAL = 1; ///< Resolve refs within the document
    public const RESOLVE_EXTERNAL = 2; ///< Resolve refs outside the document
    public const RESOLVE_ALL      = 3; ///< Resolve all refs

    private $ownerDocument_;   ///< self
    private $jsonPtr_;         ///< string
    private $baseUri_;         ///< ?UriInterface

    /**
     * @brief Create a JSON node
     *
     * This can be overridden in derived classes to create nodes of different
     * types.
     */
    public static function createNodeObject(
        $data,
        ?self $ownerDocument = null,
        ?string $jsonPtr = null,
        ?UriInterface $baseUri = null
    ): self {
        return new self($data, $ownerDocument, $jsonPtr, $baseUri);
    }

    /**
     * @brief Construct from object or iterable, creating a public property
     * for each key
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

        foreach ($data as $subKey => $value) {
            $this->$subKey = $this->createNode(
                ($this->jsonPtr_ == '/' ? '/' : $this->jsonPtr_ . '/')
                . str_replace([ '~', '/' ], [ '~0', '~1' ], $subKey),
                $value
            );
        }
    }

    public function __toString(): string
    {
        return $this->toJsonText();
    }

    public function createDeepCopy(): self
    {
        $node = clone $this;

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

    /// Key of this node in the parent node, or null if this is the root
    public function getKey(): ?string
    {
        return $this->jsonPtr_ == '/'
            ? null
            : substr($this->jsonPtr_, strrpos($this->jsonPtr_, '/') + 1);
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

    /**
     * @brief Create a JSON node
     * - If $value is a nonempty numerically-indexed array, create an array.
     * - Else, if $value is an object or associative array, call createNode()
     *   recursively.
     * - Else, use $value as-is.
     *
     * @sa [How to check if PHP array is associative or sequential?](https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential)
     */
    public function createNode($jsonPtr, $value)
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
                return $this::createNodeObject(
                    $value,
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
     * @param $flags 0 or @ref CLONE_UPON_IMPORT
     *
     * @warning This method modifies $node. To import a copy, pass a clone.
     *
     * @note This method does not insert the node into the tree. It only
     * prepares it so that it can then be inserted into the right place.
     */
    public function importObjectNode(
        JsonNode $node,
        string $jsonPtr,
        ?int $flags = null
    ): self {
        if ($flags & self::CLONE_UPON_IMPORT) {
            $node = clone $node;
        }

        $node->ownerDocument_ = $this->ownerDocument_;
        $node->jsonPtr_ = $jsonPtr;

        $walker = new RecursiveWalker(
            $node,
            RecursiveWalker::JSON_OBJECTS_ONLY
            | RecursiveWalker::OMIT_START_NODE
        );

        foreach ($walker as $jsonPtr => $subNode) {
            if ($flags & self::CLONE_UPON_IMPORT) {
                $subNode = clone $subNode;
                $walker->replaceCurrent($subNode);
            }

            $subNode->ownerDocument_ = $this->ownerDocument_;
            $subNode->jsonPtr_ = $jsonPtr;
        }

        return $node;
    }

    /**
     * @param $node Array to import into the current document
     *
     * @param $jsonPtr JSON pointer pointing to the new node
     *
     * @param $flags 0 or @ref CLONE_UPON_IMPORT
     *
     * @warning This method modifies $node. To import a copy, pass a clone.
     *
     * @note This method does not insert the node into the tree. It only
     * prepares it so that it can then be inserted into the right place.
     */
    public function importArrayNode(
        array $node,
        string $jsonPtr,
        ?int $flags = null
    ): array {
        $walker = new RecursiveWalker(
            $node,
            RecursiveWalker::JSON_OBJECTS_ONLY
            | RecursiveWalker::OMIT_START_NODE
        );

        foreach ($walker as $jsonPtrFragment => $subNode) {
            if ($flags & self::CLONE_UPON_IMPORT) {
                $subNode = clone $subNode;
                $walker->replaceCurrent($subNode);
            }

            $subNode->ownerDocument_ = $this->ownerDocument_;
            $subNode->jsonPtr_ = "$jsonPtr/$jsonPtrFragment";
        }

        return $node;
    }

    /**
     * @warning Event though this method may also modify $this, the return
     * value may be different from $this and does not even need to be an
     * object.
     */
    public function resolveReferences(
        int $flags = self::RESOLVE_ALL,
        ?JsonNodeFactory $factory = null
    ) {
        if (!isset($factory)) {
            $factory = new JsonNodeFactory();
        }

        $result = $this;

        $walker =
            new RecursiveWalker($result, RecursiveWalker::JSON_OBJECTS_ONLY);

        foreach ($walker as $jsonPtr => $node) {
            if ($node instanceof self && isset($node->{'$ref'})) {
                $ref = $node->{'$ref'};

                switch (true) {
                    case $ref[0] == '#' && $flags & self::RESOLVE_INTERNAL:
                        $newNode =
                            $result->ownerDocument_->getNode(substr($ref, 1));
                        break;

                    case $ref[0] != '#' && $flags & self::RESOLVE_EXTERNAL:
                        $url = new Uri($ref);

                        /* The new documents must not be created in their
                         * final place, because then it might be impossible to
                         * resolve references inside them. Sothey must first
                         * be created as standalone documents and later be
                         * imported into their final place. */

                        if ($url->getFragment() == '') {
                            $newNode = $factory->createFromUrl($url);
                        } else {
                            $newNode = $factory
                                ->createFromUrl($url->withFragment(''))
                                ->getNode($url->getFragment());
                        }
                        break;

                    default:
                        continue 2;
                }

                if ($newNode instanceof self) {
                    $newNode = $newNode->resolveReferences($flags);
                } elseif (is_array($newNode)) {
                    $newNode =
                        $this->resolveReferencesInArray($newNode, $flags);
                }

                if ($newNode instanceof self) {
                    $newNode = $result->importObjectNode(
                        $newNode,
                        $jsonPtr,
                        self::CLONE_UPON_IMPORT
                    );
                } elseif (is_array($newNode)) {
                    $newNode = $result->importArrayNode(
                        $newNode,
                        $jsonPtr,
                        self::CLONE_UPON_IMPORT
                    );
                }

                $walker->replaceCurrent($newNode);
                $walker->skipChildren();
            }
        }

        return $result;
    }

    private function resolveReferencesInArray(
        array $node,
        int $flags = self::RESOLVE_ALL
    ) {
        $walker =
            new RecursiveWalker($node, RecursiveWalker::JSON_OBJECTS_ONLY);

        foreach ($walker as $subNode) {
            $walker->replaceCurrent($subNode->resolveReferences($flags));
            $walker->skipChildren();
        }

        return $node;
    }
}
