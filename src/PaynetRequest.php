<?php

namespace Paymentmd\Paynet;

/**
 * Paynet Request
 */
class PaynetRequest
{
    public $ExternalDate;
    public $ExternalID;
    public $Currency = 498;
    public $Merchant;
    public $LinkSuccess;
    public $LinkCancel;
    public $ExpiryDate;
    //---------  ru, ro, en
    public $Lang;
    public $Service = [];
    public $Products = [];
    public $Customer = [];
    public $Amount;
}
