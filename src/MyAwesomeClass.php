<?php

namespace MyVendor\MyPackageName;

class MyAwesomeClass
{
    public function __construct()
    {
        $js = file_get_contents(__DIR__ . '/../node_modules/lodash/lodash.js');
        // Use the JavaScript code here.
    }
}
