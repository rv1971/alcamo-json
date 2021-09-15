<?php

namespace alcamo\json;

/**
 * @brief Container for a reference
 *
 * RecursiveWalker::replaceCurrent() must be able to replace a node also
 * when it is an array item. This requires a reference to the parent array to
 * be available. For this purpose, for each array encountered while walking, a
 * reference to the array is stored in a ReferenceContainer.
 */
class ReferenceContainer
{
    public $value;

    public function __construct(&$value)
    {
        $this->value =& $value;
    }
}
