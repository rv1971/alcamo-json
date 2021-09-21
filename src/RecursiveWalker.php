<?php

namespace alcamo\json;

/**
 * @brief Depth-first iterator for a JSON (sub)tree
 */
class RecursiveWalker implements \Iterator
{
    public const JSON_OBJECTS_ONLY = 1; ///< Only return JSON object nodes
    public const OMIT_START_NODE   = 2; ///< Do not return the start node itself

    private $startNode_; ///< any type
    private $flags_;     ///< int

    private $stack_;                ///< array
    private $currentParent_;        ///< JsonNode|ReferenceContainer
    private $currentGenerator_;     ///< Generator for items of $currentParent_
    private $currentParentPtr_;     ///< JSON pointer string
    private $skipChildren_ = false; ///< bool

    private $currentKey_;  ///< JSON pointer string
    private $currentNode_; ///< mixed

    public function __construct(&$startNode, ?int $flags = null)
    {
        if (is_array($startNode)) {
            $this->startNode_ = new ReferenceContainer($startNode);
        } else {
            $this->startNode_ =& $startNode;
        }

        $this->flags_ = (int)$flags;

        $this->rewind();
    }

    /// Current node in the JSON tree, may be of any type
    public function current()
    {
        return $this->currentNode_ instanceof ReferenceContainer
            ? $this->currentNode_->value
            : $this->currentNode_;
    }

    /**
     * @brief Return JSON pointer to current node
     *
     * If the start node is not a JSON node, this is actually a fragment of a
     * JSON pointer.
     */
    public function key(): string
    {
        return $this->currentKey_;
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
            : '';
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
        $nodeValue =
            is_array($value) ? new ReferenceContainer($value) : $value;

        if ($this->startNode_ instanceof JsonNode) {
            if ($this->currentNode_ === $this->startNode_) {
                $this->startNode_ = $value;
                $this->currentNode_ = $nodeValue;
                return;
            }
        } elseif ($this->currentKey_ == '') {
            if ($this->startNode_ instanceof ReferenceContainer) {
                $this->startNode_->value = $value;
            }

            $this->startNode_ =  $value;
            $this->currentNode_ = $nodeValue;
            return;
        }

        $key = $this->currentGenerator_->key();

        if ($this->currentParent_ instanceof JsonNode) {
            $this->currentParent_->$key = $value;
        } else {
            $this->currentParent_->value[$key] = $value;
        }

        $this->currentNode_ = $nodeValue;
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

        $this->currentKey_ =
            ($this->currentParentPtr_ == '/' || $this->currentParentPtr_ == ''
             ? $this->currentParentPtr_
             : "$this->currentParentPtr_/")
            . str_replace(
                [ '~', '/' ],
                [ '~0', '~1' ],
                $this->currentGenerator_->key()
            );

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
