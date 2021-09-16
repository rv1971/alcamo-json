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

    /// Create deep copy when cloning
    public function __clone()
    {
        $walker = new RecursiveWalker(
            $this,
            RecursiveWalker::JSON_OBJECTS_ONLY
            | RecursiveWalker::OMIT_START_NODE
        );

        foreach ($walker as $node) {
            $walker->replaceCurrent(clone $node);
            $walker->skipChildren();
        }
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
     * @warning This method modifies $node. To import a copy, pass a clone.
     *
     * @note This method does not insert the node into the tree. It only
     * prepares it so that it can then be inserted into the right place.
     */
    public function importObjectNode(JsonNode $node, string $jsonPtr): self
    {
        $node->jsonPtr_ = $jsonPtr;

        foreach (
            new RecursiveWalker(
                $node,
                RecursiveWalker::JSON_OBJECTS_ONLY
            ) as $jsonPtr => $subNode
        ) {
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
     * @warning This method modifies $node. To import a copy, pass a clone.
     *
     * @note This method does not insert the node into the tree. It only
     * prepares it so that it can then be inserted into the right place.
     */
    public function importArrayNode(array $node, string $jsonPtr): array
    {
        $slashPos = strrpos($jsonPtr, '/');
        $key = substr($jsonPtr, $slashPos + 1);

        $tmpNode = new self([]);
        $tmpNode->$key = $node;

        return
            $this->importObjectNode($tmpNode, substr($jsonPtr, 0, $slashPos))
            ->$key;
    }

    public function resolveReferences(int $flags = self::RESOLVE_ALL): self
    {
        $walker =
            new RecursiveWalker($this, RecursiveWalker::JSON_OBJECTS_ONLY);

        foreach ($walker as $node) {
            if (!isset($node->{'$ref'})) {
                continue;
            }

            $ref = $node->{'$ref'};

            if ($ref[0] == '#' && $flags & self::RESOLVE_INTERNAL) {
                $newNode = $this->ownerDocument_->getNode(substr($ref, 1));

                if ($newNode instanceof self) {
                    $newNode = clone $newNode;

                    $newNode = $this->importNodeObject(
                        $newNode,
                        $walker->getCurrentChildKey()
                    );
                } elseif (is_array($newNode)) {
                    /** @todo ... */
                }

                $walker->replaceCurrent($newNode);
            } elseif ($ref[0] != '#' && $flags & self::RESOLVE_EXTERNAL) {
                $url = new Uri($ref);

                $newNode =
                    self::newFromUrl($url->withFragment(''))
                    ->getNode($url->getFragment());

                if ($newNode instanceof self) {
                    $newNode->resolveReferences();

                    $newNode = $this->importNodeObject(
                        $newNode,
                        $walker->getCurrentChildKey()
                    );
                } elseif (is_array($newNode)) {
                    /** @todo ... */
                }

                $walker->replaceCurrent($newNode);
            }
        }

        return $this;
    }

    public function testImportObjectNode()
    {
        $jsonDoc = JsonDocument::newFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $jsonDoc2 = JsonDocument::newFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $jsonDoc->bar->foo =
            $jsonDoc->importObjectNode($jsonDoc2->foo, '/bar/foo');

        $jsonDoc->bar->baz->qux[2] =
            $jsonDoc->importObjectNode(
                clone $jsonDoc2->foo->{'~~'}, '/bar/baz/qux/2'
            );

        $jsonDoc->checkStructure();

        $this->assertSame((string)$jsonDoc->foo, (string)$jsonDoc->bar->foo);

        $this->assertSame(
            (string)$jsonDoc->bar->baz->qux[2],
            (string)$jsonDoc2->foo->{'~~'}
        );
    }

    public function testImportArrayNode()
    {
        $jsonDoc = JsonDocument::newFromUrl(
            'file://'
            . str_replace(DIRECTORY_SEPARATOR, '/', self::FOO_FILENAME)
        );

        $jsonDoc2 = clone $jsonDoc;

        $this->assertNotSame(
            $jsonDoc->bar->baz->qux[6],
            $jsonDoc2->bar->baz->qux[6]
        );

        $jsonDoc->foo = $jsonDoc->importArrayNode(
            $jsonDoc2->bar->baz->qux[6],
            '/foo'
        );
        /*
        $jsonDoc->foo[0][1] = $jsonDoc->importArrayNode(
            $jsonDoc2->bar->baz->qux[6][1],
            '/foo/0/1'
        );
        */

        var_dump((string)$jsonDoc);

        $jsonDoc->checkStructure();

        $this->assertSame(43, $jsonDoc->foo[0][0]);
        /*
        $this->assertSame(
            (string)$jsonDoc->bar->baz->qux[6][1],
            (string)$jsonDoc->foo[0][1]
        );
        */
    }
}
