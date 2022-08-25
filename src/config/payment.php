<?php

return [
    /*
     |-------------------------------------------------------------------------
     | Paynet configurations
     |-------------------------------------------------------------------------
     */
    'paynet' => [
        'local' => [
            'MERCHANT_CODE' => '741863',
            'SALE_AREA_CODE' => 'boomerang',
            'MERCHANT_SEC_KEY' => '643B413E-5BE5-4BBF-B824-2A45E25CA5B5',
            'MERCHANT_USER' => '661205',
            'MERCHANT_USER_PASS' => 'PT3BtOE0',
            'PAYNET_BASE_API_URL' => 'https://test.paynet.md:4446',
            'MERCHANT_MODE' => false,
        ],
       'production' => [
           'MERCHANT_CODE' => '',
           'SALE_AREA_CODE' => 'GeneralTest',
           'MERCHANT_SEC_KEY' => '',
           'MERCHANT_USER' => '',
           'MERCHANT_USER_PASS' => '',
           'PAYNET_BASE_API_URL' => 'https://paynet.md:4446',
           'MERCHANT_MODE' => false,
       ],
    ],
];
