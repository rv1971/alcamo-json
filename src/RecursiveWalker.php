<?php

namespace alcamo\json;

/// Depth-first iterator for a JSON subtree
class RecursiveWalker implements \Iterator
{
    private $startNode_;   ///< JsonNode

    private $currentStack_;     ///< array
    private $currentParent_;    ///< JsonNode|array
    private $currentProps_;     ///< array
    private $currentIndex_;     ///< int
    private $currentParentPtr_; ///< JSON pointer string

    private $currentKey_;  ///< JSON pointer string
    private $currentNode_; ///< mixed

    public function __construct(JsonNode $startNode)
    {
        $this->startNode_ = $startNode;

        $this->rewind();
    }

    public function current()
    {
        return $this->currentNode_;
    }

    public function key()
    {
        return $this->currentKey_;
    }

    public function next(): void
    {
        // if current node has children, go down to first child
        if (
            ($this->currentNode_ instanceof JsonNode
             && get_object_vars($this->currentNode_))
            || (is_array($this->currentNode_) && $this->currentNode_)
        ) {
            $this->currentStack_[] = [
                $this->currentParent_,
                $this->currentProps_,
                $this->currentIndex_,
                $this->currentParentPtr_
            ];

            $this->currentParent_ = $this->currentNode_;
            $this->currentProps_ =
                array_keys(get_object_vars($this->currentParent_));
            $this->currentIndex_ = 0;
            $this->currentParentPtr_ = $this->currentKey_;
        } else {
            /// otherwise, go up until finding a level where there is a sibling
            while (
                !isset($this->currentProps_[$this->currentIndex_++])
                && $this->currentStack_
            ) {
                [
                    $this->currentParent_,
                    $this->currentProps_,
                    $this->currentIndex_,
                    $this->currentParentPtr_
                ] = array_pop($this->currentStack_);
            }

            /// exit iteration if no more siblings on any level
            if (!isset($this->currentProps_[$this->currentIndex_])) {
                $this->currentKey_ = null;
                $this->currentNode_ = null;
                return;
            }
        }

        $currentProp = $this->currentProps_[$this->currentIndex_];

        $this->currentKey_ = $this->currentParentPtr_ . '/'
            . str_replace([ '~', '/' ], [ '~0', '~1' ], $currentProp);

        $this->currentNode_ = is_object($this->currentParent_)
            ? $this->currentParent_->$currentProp
            : $this->currentParent_[$currentProp];
    }

    public function rewind(): void
    {
        $this->currentStack_ = [];
        $this->currentParent_ = [ $this->startNode_ ];
        $this->currentProps_ = [ 0 ];
        $this->currentIndex_ = 0;
        $this->currentParentPtr_ = '';

        $this->currentKey_ = $this->startNode_->getJsonPtr();
        $this->currentNode_ = $this->startNode_;
    }

    public function valid(): bool
    {
        return isset($this->currentKey_);
    }
}
