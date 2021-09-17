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

    public static function newFromJsonText(
        string $jsonText,
        ?self $parent = null,
        ?string $key = null,
        ?UriInterface $baseUri = null,
        ?int $depth = null,
        ?int $flags = null
    ): self {
        return new static(
            json_decode($jsonText, false, $depth ?? 512, $flags ?? 0),
            $parent,
            $key,
            $baseUri
        );
    }

    public static function newFromUrl(
        $url,
        ?self $parent = null,
        ?string $key = null,
        ?int $depth = null,
        ?int $flags = null
    ): self {
        return static::newFromJsonText(
            file_get_contents($url),
            $parent,
            $key,
            $url instanceof UriInterface ? $url : new Uri($url)
        );
    }

    /**
     * @brief Construct from object or iterable, creating a public property
     * for each key
     */
    public function __construct(
        $data,
        ?self $parent = null,
        ?string $key = null,
        ?UriInterface $baseUri = null
    ) {
        $this->ownerDocument_ = $parent->ownerDocument_ ?? $this;

        if (isset($parent)) {
            $this->jsonPtr_ = $parent->jsonPtr_ == '/'
                ? "/$key"
                : "$parent->jsonPtr_/$key";
        } else {
            $this->jsonPtr_ = '/';
        }

        $this->baseUri_ = $baseUri ?? $parent->baseUri_ ?? null;

        foreach ($data as $subKey => $value) {
            $this->$subKey = $this->createNode(
                str_replace([ '~', '/' ], [ '~0', '~1' ], $subKey),
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
    public function createNode($key, $value)
    {
        switch (true) {
            case is_array($value)
                && (isset($value[0]) || array_key_exists(0, $value))
                && array_keys($value) === range(0, count($value) - 1):
                $result = [];

                foreach ($value as $subKey => $subValue) {
                    $result[] =
                        $this->createNode("$key/$subKey", $subValue);
                }

                return $result;

            case is_object($value) || is_array($value):
                return $this->createNodeObject($value, $this, $key);

            default:
                return $value;
        }
    }

    /**
     * @brief Create a JSON node
     *
     * This can be overridden in derived classes to create nodes of different
     * types.
     */
    public function createNodeObject(
        $data,
        ?self $parent = null,
        ?string $key = null,
        ?UriInterface $baseUri = null
    ): self {
        return new self($data, $parent, $key, $baseUri);
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

        foreach ($walker as $jsonPtr => $subNode
        ) {
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
        $slashPos = strrpos($jsonPtr, '/');
        $key = substr($jsonPtr, $slashPos + 1);

        $tmpNode = new self([]);
        $tmpNode->$key = $node;

        return
            $this->importObjectNode(
                $tmpNode,
                substr($jsonPtr, 0, $slashPos),
                $flags
            )
            ->$key;
    }

    /**
     * @warning Event though this method may also modify $this, the return
     * value may be different from $this and does not even need to be an
     * object.
     */
    public function resolveReferences(int $flags = self::RESOLVE_ALL)
    {
        $result = $this;

        $walker =
            new RecursiveWalker($result, RecursiveWalker::JSON_OBJECTS_ONLY);

        foreach ($walker as $jsonPtr => $node) {
            /* A loop is necessary because the replacement of the current node
             * could itself be a reference. */
            while ($node instanceof self && isset($node->{'$ref'})) {
                $ref = $node->{'$ref'};

                if ($ref[0] == '#' && $flags & self::RESOLVE_INTERNAL) {
                    $node = $result->ownerDocument_->getNode(substr($ref, 1));

                    /* Internal references are replaced by their target and do
                     * not need to be resolvbed further here because they will
                     * be reconsidered in the next iteration of the while loop
                     * if they are objects, or the next iteration of the
                     * foreach loop if they are arrays. */

                    if ($node instanceof self) {
                        $node = $result->importObjectNode(
                            $node,
                            $jsonPtr,
                            self::CLONE_UPON_IMPORT
                        );
                    } elseif (is_array($node)) {
                        $node = $result->importArrayNode(
                            $node,
                            $jsonPtr,
                            self::CLONE_UPON_IMPORT
                        );
                    }

                    $walker->replaceCurrent($node);
                } elseif ($ref[0] != '#' && $flags & self::RESOLVE_EXTERNAL) {
                    $url = new Uri($ref);

                    $node = $url->getFragment() == ''
                        ? self::newFromUrl($url)
                        : (self::newFromUrl($url->withFragment(''))
                            ->getNode($url->getFragment()));

                    /* External references must be completely resolved before
                     * import because they may contain internal references to
                     * their document which become unavailabvle after
                     * importing. */

                    if ($node instanceof self) {
                        $node = $node->resolveReferences($flags);

                        if ($node instanceof self) {
                            $node = $result->importObjectNode($node, $jsonPtr);
                        } elseif (is_array($node)) {
                            $node = $result->importArrayNode($node, $jsonPtr);
                        }

                        $walker->skipChildren();
                    } elseif (is_array($node)) {
                        $this->resolveReferencesInArray($node, $flags);

                        $node = $result->importArrayNode($node, $jsonPtr);
                    }

                    $walker->replaceCurrent($node);
                } else {
                    // exit loop if ref cannot be replaced
                    break;
                }
            }
        }

        return $result;
    }
}
