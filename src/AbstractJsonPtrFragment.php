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

    public const ENCODE_MAP = [ '~' => '~0', '/' => '~1' ];
    public const DECODE_MAP = [ '~1' => '/', '~0' => '~' ];

    protected $data_; ///< array of segment strings

    /**
     * @param Numerically-indexed array of not yet encoded segments
     */
    public function __construct(?array $segments = null)
    {
        $this->data_ = $segments ?? [];
    }

    public function toArray(): array
    {
        return $this->data_;
    }

    public function getParent(): ?self
    {
        if ($this->data_) {
            $parentData = $this->data_;
            array_pop($parentData);
            return new static($parentData);
        } else {
            return null;
        }
    }

    public function appendSegment(string $segment): self
    {
        $segments = $this->data_;
        $segments[] = $segment;

        return new static($segments);
    }

    public function appendSegments(JsonPtrSegments $segments): self
    {
        return new static(array_merge($this->data_, $segments->data_));
    }
}
