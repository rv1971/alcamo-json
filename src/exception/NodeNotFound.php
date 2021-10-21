<?php

namespace alcamo\json\exception;

use alcamo\exception\{ExceptionInterface, ExceptionTrait};
use alcamo\json\JsonNode;

/**
 * @brief Exception thrown when a node indicated by a JSON pointer was not found
 */
class NodeNotFound extends \RuntimeException implements
    ExceptionInterface
{
    use ExceptionTrait;

    public const NORMALIZED_MESSAGE = 'Node at {jsonPtr} not found';
}
