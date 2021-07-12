<?php

namespace alcamo\json;

/**
 * @namespace alcamo::json
 *
 * @brief Easy-to-use JSON documents with JSON pointer support
 */

/**
 * @brief Object node in a JSON tree
 */
class JsonNode
{
    private $parent_;  ///< ?self
    private $key_;     ///< int|string
    private $jsonPtr_; ///< string

    /**
     * @brief Construct from object or iterable, creating a public property
     * for each key
     */
    protected function __construct($data, ?self $parent, $key)
    {
        $this->parent_ = $parent;
        $this->key_ = $key;

        if (isset($parent)) {
            $this->jsonPtr_ = "$parent->jsonPtr_/$key";
        } else {
            $this->jsonPtr_ = '';
        }

        foreach ($data as $subKey => $value) {
            $this->$subKey = $this->createNode(
                str_replace([ '~', '/' ], [ '~0', '~1' ], $subKey),
                $value
            );
        }
    }

    public function __toString()
    {
        return $this->toJsonText();
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

    public function toJsonText(?int $flags = null, ?int $depth = null): string
    {
        return json_encode($this, $flags ?? 0, $depth ?? 512);
    }

    /**
     * @brief Create a document node
     * - If $value is a nonempty numerically-indexed array, create an array.
     * - Else, if $value is an object or iterable, call createNode()
     *   recursively.
     * - Else, use $value as-is.
     *
     * @sa [How to check if PHP array is associative or sequential?](https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential)
     */
    private function createNode($key, $value)
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

            case is_object($value) || is_iterable($value):
                return new self($value, $this, $key);

            default:
                return $value;
        }
    }
}
