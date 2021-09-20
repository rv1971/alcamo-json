<?php

namespace alcamo\json;

/// Container for a reference
class ReferenceContainer
{
    public $value;

    public function __construct(&$value)
    {
        $this->value =& $value;
    }
}
