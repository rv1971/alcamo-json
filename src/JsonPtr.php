<?php

namespace alcamo\json;

use alcamo\exception\SyntaxError;

/**
 * @brief JSON pointer
 *
 * @invariant Immutable class.
 *
 * Implemented as an array of segments with a cached string representation.
 */
class JsonPtr extends AbstractJsonPtrFragment
{
    private $string_; ///< cached string representation

    public static function newFromString(string $string): self
    {
        if ($string === '/') {
            return new static();
        }

        if ($string[0] != '/') {
            throw (new SyntaxError())->setMessageContext(
                [
                    'inData' => $string,
                    'atOffset' => 0,
                    'extraMessage' => 'JSON pointer must begin with slash'
                ]
            );
        }

        $segments = [];

        foreach (explode('/', substr($string, 1)) as $segment) {
            $segments[] = strtr($segment, self::DECODE_MAP);
        }

        return new static($segments);
    }

    public function __toString(): string
    {
        if (!isset($this->string_)) {
            if ($this->data_) {
                foreach ($this->data_ as $segment) {
                    $this->string_ .= '/' . strtr($segment, self::ENCODE_MAP);
                }
            } else {
                $this->string_ = '/';
            }
        }

        return $this->string_;
    }

    /// Whether this is the root pointer
    public function isRoot(): bool
    {
        return !isset($this->data_[0]);
    }
}
