<?php

/**
 * @namespace alcamo::json
 *
 * @brief Easy-to-use JSON documents with JSON pointer support
 */

namespace alcamo\json;

use alcamo\ietf\Uri;
use Psr\Http\Message\UriInterface;

/**
 * @brief Object node in a JSON tree
 */
class JsonNode
{
    private $ownerDocument_;   ///< self
    private $parent_;          ///< ?self
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
        $this->ownerDocument_ =
            isset($parent) ? $parent->ownerDocument_ : $this;

        $this->parent_ = $parent;

        if (isset($parent)) {
            $this->jsonPtr_ = $parent->jsonPtr_ == '/'
                ? "/$key"
                : "$parent->jsonPtr_/$key";
        } else {
            $this->jsonPtr_ = '/';
        }

        $this->baseUri_ =
            $baseUri ?? (isset($parent) ? $parent->baseUri_ : null);

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

    /**
     * @brief Get the closest ancestor that is an object
     *
     * @attention This is not the immediate parent if the immediate parent is
     * an array.
     */
    public function getParent(): ?self
    {
        return $this->parent_;
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
}
