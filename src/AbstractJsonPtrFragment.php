<?php

namespace alcamo\json;

use alcamo\collection\{
    ArrayIteratorTrait,
    CountableTrait,
    ReadArrayAccessTrait,
    PreventWriteArrayAccessTrait
};

/**
 * @brief Base class for JSON pointer and JSON pointer segments
 *
 * @invariant Immutable class.
 *
 * Implemented as an array of segments.
 */
abstract class AbstractJsonPtrFragment implements
    \Countable,
    \Iterator,
    \ArrayAccess
{
    use CountableTrait;
    use ArrayIteratorTrait;
    use ReadArrayAccessTrait;
    use PreventWriteArrayAccessTrait;

    /// Input to strtr() to encode a segment
    public const ENCODE_MAP = [ '~' => '~0', '/' => '~1' ];

    /// Input to strtr() to decode a segment
    public const DECODE_MAP = [ '~1' => '/', '~0' => '~' ];

    protected $data_; ///< array of segment strings

    /**
     * @param $segments Numerically-indexed array of not yet encoded segments
     */
    public function __construct(?array $segments = null)
    {
        $this->data_ = $segments ?? [];
    }

    public function toArray(): array
    {
        return $this->data_;
    }

    /// Return new object with last segment removed, or `null` if top-level
    public function getParent(): ?self
    {
        return $this->data_
            ? new static(array_slice($this->data_, 0, -1))
            : null;
    }

    /// Return new object with new segment appended
    public function appendSegment(string $segment): self
    {
        $segments = $this->data_;
        $segments[] = $segment;

        return new static($segments);
    }

    /// Return new object with new segments appended
    public function appendSegments(JsonPtrSegments $segments): self
    {
        return new static(array_merge($this->data_, $segments->data_));
    }
}
