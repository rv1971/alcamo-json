<?php

namespace alcamo\json;

use alcamo\collection\{
    ArrayIteratorTrait,
    CountableTrait,
    ReadArrayAccessTrait,
    PreventWriteArrayAccessTrait
};
use alcamo\exception\SyntaxError;

/**
 * @brief JSON pointer
 *
 * Implemented as an array of segments with a cached string representation.
 */
class JsonPtr implements \Countable, \Iterator, \ArrayAccess
{
    use CountableTrait;
    use ArrayIteratorTrait;
    use ReadArrayAccessTrait;
    use PreventWriteArrayAccessTrait;

    public const ENCODE_MAP = [ '~' => '~0', '/' => '~1' ];
    public const DECODE_MAP = [ '~1' => '/', '~0' => '~' ];

    private $data_; ///< array of segment strings
    private $string_;   ///< cached string representation

    public static function newFromString(string $string): self
    {
        $segments = [];

        if ($string[0] != '/') {
            throw (new SyntaxError())->setMessageContext(
                [
                    'inData' => $string,
                    'atOffset' => 0,
                    'extraMessage' => 'JSON pointer must begin with slash'
                ]
            );
        }

        if ($string != '/') {
            foreach (explode('/', substr($string, 1)) as $segment) {
                $segments[] = strtr($segment, self::DECODE_MAP);
            }
        }

        return new static($segments);
    }

    public function __construct(array $segments)
    {
        $this->data_ = $segments;
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

    public function appendSegment(string $segment): self
    {
        $this->string_ = null;
        $this->data_[] = $segment;

        return $this;
    }
}
