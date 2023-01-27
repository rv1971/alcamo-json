<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;

/**
 * @brief JSON pointer segments
 *
 * @invariant Immutable class.
 *
 * Implemented as an array of segments with a cached string representation.
 */
class JsonPtrSegments extends AbstractJsonPtrFragment
{
    private $string_;   ///< cached string representation

    public static function newFromString(string $string): self
    {
        $segments = [];

        if (($string[0] ?? null) === '/') {
            throw (new SyntaxError())->setMessageContext(
                [
                    'inData' => $string,
                    'atOffset' => 0,
                    'extraMessage' => 'JSON pointer segments must not begin with slash'
                ]
            );
        }

        if ($string !== '') {
            foreach (explode('/', $string) as $segment) {
                $segments[] = strtr($segment, self::DECODE_MAP);
            }
        }

        return new static($segments);
    }

    public function __toString(): string
    {
        if (!isset($this->string_)) {
            $segments = [];

            foreach ($this->data_ as $segment) {
                $segments[] = strtr($segment, self::ENCODE_MAP);
            }

            $this->string_ = implode('/', $segments);
        }

        return $this->string_;
    }

    /// Whether this is an empty sequence of segments
    public function isEmpty(): bool
    {
        return !isset($this->data_[0]);
    }
}
