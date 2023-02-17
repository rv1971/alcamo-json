<?php

namespace alcamo\json;

use alcamo\exception\Closed;

/// Depth-first iterator for a JSON (sub)tree
class RecursiveWalker implements \Iterator
{
    public const JSON_OBJECTS_ONLY = 1; ///< Only return JSON object nodes
    public const OMIT_START_NODE   = 2; ///< Do not return the start node itself

    private $startNode_; ///< JsonNode|ReferenceContainer
    private $flags_;     ///< int

    private $stack_;                ///< array
    private $currentParent_;        ///< JsonNode|ReferenceContainer
    private $currentGenerator_;     ///< Generator for items of $currentParent_
    private $currentParentPtr_;     ///< JSON pointer string
    private $skipChildren_ = false; ///< bool

    private $currentKey_;  ///< AbstractJsonPtrFragment
    private $currentNode_; ///< any type

    public function __construct(&$startNode, ?int $flags = null)
    {
        if (is_array($startNode)) {
            $this->startNode_ = new ReferenceContainer($startNode);
        } elseif ($startNode instanceof JsonDocument) {
            $this->startNode_ =& $startNode->getRoot();
        } else {
            $this->startNode_ =& $startNode;
        }

        $this->flags_ = (int)$flags;

        $this->rewind();
    }

    /// Pair consisting of AbstractJsonPtrFragment and node
    public function current()
    {
        return [
            $this->currentKey_,
            $this->currentNode_ instanceof ReferenceContainer
            ? $this->currentNode_->value
            : $this->currentNode_
        ];
    }

    /**
     * @brief Return JSON pointer (segments) to current node
     *
     * If the start node is a JSON node, return a JSON pointer literal,
     * otherwise a JSON pointer segments literal.
     */
    public function key(): string
    {
        return (string)$this->currentKey_;
    }

    public function next(): void
    {
        if ($this->flags_ & self::JSON_OBJECTS_ONLY) {
            $this->jsonObjectsOnlyNext();
        } else {
            $this->simpleNext();
        }
    }

    public function rewind(): void
    {
        /* Model the start node as the only child of an artificial parent
         * array. This greatly simplies the implementation of next(). */

        $parent = [ $this->startNode_ ];

        $this->stack_ = [];
        $this->currentParent_ = new ReferenceContainer($parent);
        $this->currentGenerator_ = $this->iterateArray($parent);

        $this->currentKey_ = $this->startNode_ instanceof JsonNode
            ? $this->startNode_->getJsonPtr()
            : new JsonPtrSegments();
        $this->currentNode_ = $this->startNode_;

        if (
            $this->flags_ & self::OMIT_START_NODE
            || ($this->flags_ & self::JSON_OBJECTS_ONLY
                && !($this->startNode_ instanceof JsonNode))
        ) {
            $this->next();
        }
    }

    public function valid(): bool
    {
        return isset($this->currentKey_);
    }

    public function iterateArray(array &$data)
    {
        /* Do not iterate the array itself in order to allow for concurrent
         * iterators on the same array. */
        foreach (array_keys($data) as $key) {
            yield $key => is_array($data[$key])
                ? new ReferenceContainer($data[$key])
                : $data[$key];
        }
    }

    public function iterateObject(JsonNode $data)
    {
        foreach (get_object_vars($data) as $key => $item) {
            yield $key => is_array($item)
                ? new ReferenceContainer($data->$key)
                : $item;
        }
    }

    /// do not iterate children of current node
    public function skipChildren(): void
    {
        $this->skipChildren_ = true;
    }

    /**
     * @brief Modify the document by replacing the current node
     *
     * The walker will then walk through the new node.
     */
    public function replaceCurrent($value): void
    {
        /**
         * @throw alcamo::exception::Closed if iterator has already
         * terminated.
         */
        if (!isset($this->currentNode_)) {
            throw (new Closed())->setMessageContext(
                [
                    'objectType' => static::class,
                    'object' => ''
                ]
            );
        }

        $this->currentNode_ =
            is_array($value) ? new ReferenceContainer($value) : $value;

        if ($this->currentKey_->isEmpty()) {
            if ($this->startNode_ instanceof ReferenceContainer) {
                $this->startNode_->value = $value;
            }

            $this->currentKey_ = $value instanceof JsonNnode
                ? new JsonPtr()
                : new JsonPtrSegments();

            $this->startNode_ = $this->currentNode_;
        } else {
            $key = $this->currentGenerator_->key();

            if ($this->currentParent_ instanceof JsonNode) {
                $this->currentParent_->$key = $value;
            } else {
                $this->currentParent_->value[$key] = $value;
            }
        }
    }

    /// next() implementation proceeding to the next node, if any
    protected function simpleNext(): void
    {
        switch (true) {
            case $this->skipChildren_:
                $this->skipChildren_ = false;
                $generator = null;
                break;

            case $this->currentNode_ instanceof JsonNode:
                $generator = $this->iterateObject($this->currentNode_);
                break;

            case $this->currentNode_ instanceof ReferenceContainer:
                $generator = $this->iterateArray($this->currentNode_->value);
                break;

                /* $this->currentNode_ is null */
            default:
                $generator = null;
        }

        // if current node has children, go down to first child
        if (isset($generator) && $generator->valid()) {
            $this->stack_[] = [
                $this->currentParent_,
                $this->currentGenerator_,
                $this->currentParentPtr_
            ];

            $this->currentParent_ = $this->currentNode_;
            $this->currentGenerator_ = $generator;
            $this->currentParentPtr_ = $this->currentKey_;
        } else {
            // otherwise, go up until finding a level where there is a sibling
            for (
                $this->currentGenerator_->next();
                $this->stack_ && !$this->currentGenerator_->valid();
                $this->currentGenerator_->next()
            ) {
                [
                    $this->currentParent_,
                    $this->currentGenerator_,
                    $this->currentParentPtr_
                ] = array_pop($this->stack_);
            }

            // exit iteration if no more siblings on any level
            if (!$this->currentGenerator_->valid()) {
                // ensure that replaceCurrent() fails

                $this->currentParent_ = null;
                $this->currentKey_ = null;
                $this->currentNode_ = null;

                return;
            }
        }

        $this->currentKey_ = $this->currentParentPtr_
            ->appendSegment($this->currentGenerator_->key());

        $this->currentNode_ = $this->currentGenerator_->current();
    }

    /// next() implementation proceeding to the next JSON object, if any
    protected function jsonObjectsOnlyNext(): void
    {
        do {
            $this->simpleNext();
        } while (
            isset($this->currentKey_)
            && !($this->currentNode_ instanceof JsonNode)
        );
    }
}
