<?php

namespace alcamo\json\exception;

use alcamo\json\JsonNode;

/**
 * @brief Exception thrown when a node indicated by a JSON pointer was not found
 */
class NodeNotFound extends \RuntimeException
{
    public $document; ///< JsonNode
    public $jsonPtr;  ///< string

    /**
     * @param $objectOrLabel @copybrief $objectOrLabel
     *
     * @param $method @copybrief $method
     *
     * @param $message If $message starts with a ';', it is appended to the
     * automatically generated message, otherwise it replaces the generated
     * one.
     */
    public function __construct(
        JsonNode $document,
        string $jsonPtr,
        string $message = '',
        int $code = 0,
        \Exception $previous = null
    ) {
        $this->document = $document;
        $this->jsonPtr = $jsonPtr;

        if (!$message || $message[0] == ';') {
            $text = (string)$document;

            /** Display at most the first 40 characters of @ref $text. */
            $shortText =
                strlen($text) <= 40 ? $text : (substr($text, 0, 40) . '...');

            $message = "Node at \"$jsonPtr\" not found in document "
                . "\"$shortText\" at {$document->getBaseUri()}"
                . $message;
        }

        parent::__construct($message, $code, $previous);
    }
}
