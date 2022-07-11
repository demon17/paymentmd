<?php

namespace Paymentmd\Paynet;

/**
 * Paynet result
 */
class PaynetResult
{
    public $Code ;
    public $Message ;
    public $Data ;

    /**
     * @return bool
     */
    public function IsOk()
    {
        return $this->Code === PaynetCode::SUCCESS;
    }
}
