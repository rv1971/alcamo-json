<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;

/**
 * @brief Sequence of JSON pointer segments not beginning with slash
 *
 * @invariant Immutable class.
 *
 * Implemented as an array of segments with a cached string representation.
 */
class JsonPtrSegments extends AbstractJsonPtrFragment
{
    private $string_; ///< cached string representation

    public static function newFromString(string $string): self
    {
        if ($string === '') {
            return new static();
        }

        if ($string[0] == '/') {
            /** @throw alcamo::exception::SyntaxError if $string starts with
             *  slash. */
            throw (new SyntaxError())->setMessageContext(
                [
                    'inData' => $string,
                    'atOffset' => 0,
                    'extraMessage' =>
                    'sequence of JSON pointer segments must not start with slash'
                ]
            );
        }

        $segments = [];

        foreach (explode('/', $string) as $segment) {
            $segments[] = strtr($segment, self::DECODE_MAP);
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

    /// Whether this is an empty sequence
    public function isEmpty(): bool
    {
        return !isset($this->data_[0]);
    }
}
