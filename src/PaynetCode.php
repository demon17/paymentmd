<?php

namespace Paymentmd\Paynet;

/**
 * Paynet Error Code
 */
class PaynetCode
{
    const SUCCESS = 0;
    const TECHNICAL_ERROR = 1;
    const DATABASE_ERROR = 2;
    const USERNAME_OR_PASSWORD_WRONG = 3;
    const CONNECTION_ERROR = 12;
}
