<?php

namespace Paymentmd\Paynet;

use Carbon\Carbon;
use Exception;

/**
 * PaynetEcomAPI class works with paynet API
 */
class PaynetEcomAPI
{
    /**
     * Version
     */
	const API_VERSION = "Version 1.2";

	/**
		Merchant code.
	*/
	private $merchant_code;

	/**
		Merchant secret key.
	*/
	private $merchant_secret_key;

	/**
		Merchant user for access to API.
	*/
	private $merchant_user;

	/**
		Merchant user's password.
	*/
	private $merchant_user_password;

	/**
		The base URL to UI
	*/
    private $paynet_base_ui_url;

	/**
		The base URL to UI
	*/
    private $paynet_base_ui_server_url;

	/**
		The base URL to API
	*/
    private $api_base_url;

	/**
		The expiry time for this operation, in hours
	*/
	const EXPIRY_DATE_HOURS = 4 ;//  hours

    /**
     * Adapting hours
     */
	const ADAPTING_HOURS = 1 ;//  hours

	public function __construct()
	{
		$this->setConfig();
        $this->setProdLinks();
	}

    /**
     * @return void
     */
    private function setProdLinks()
    {
        $this->paynet_base_ui_url = 'https://test.paynet.md/acquiring/setecom';
        $this->paynet_base_ui_server_url = 'https://test.paynet.md/acquiring/getecom';

        if(config('app.env') == 'production') {
            $this->paynet_base_ui_url = 'https://paynet.md/acquiring/setecom';
            $this->paynet_base_ui_server_url = 'https://paynet.md/acquiring/getecom';
        }
    }

    /**
     * Set config use environment variable `config('app.env')`
     *
     * @return void
     */
    private function setConfig() {
        /*$env = 'dev';

        if(config('app.env') == 'production') {
            $env = 'production';
        }*/

        $env = config('app.env');

        $this->merchant_code = config("payment.paynet.{$env}.MERCHANT_CODE");
        $this->merchant_secret_key = config("payment.paynet.{$env}.MERCHANT_SEC_KEY");
        $this->merchant_user = config("payment.paynet.{$env}.MERCHANT_USER");
        $this->merchant_user_password = config("payment.paynet.{$env}.MERCHANT_USER_PASS");
        $this->api_base_url = config("payment.paynet.{$env}.PAYNET_BASE_API_URL");
    }

    /**
     * @return string
     */
	public function Version()
	{
		return self::API_VERSION;
	}

    /**
     * @param $addHeader
     * @return PaynetResult
     */
	public function TokenGet($addHeader = false)
	{
		$path = '/auth';
		$params = [
            'grant_type' 	=> 'password',
            'username'      => $this->merchant_user,
            'password'    	=> $this->merchant_user_password
        ];

		$tokenReq =  $this->callApi($path, 'POST', $params);

		$result = new PaynetResult();

		if($tokenReq->Code == PaynetCode::SUCCESS)
		{
			if(array_key_exists('access_token', $tokenReq->Data))
			{
				$result->Code = PaynetCode::SUCCESS;
				if($addHeader)
					$result->Data = ["Authorization: Bearer ".$tokenReq->Data['access_token']];
				else
					$result->Data = $tokenReq->Data['access_token'];
			}else
			{
				$result->Code = PaynetCode::USERNAME_OR_PASSWORD_WRONG;
				if(array_key_exists('Message', $tokenReq->Data))
					$result->Message = $tokenReq->Data['Message'];
				if(array_key_exists('error', $tokenReq->Data))
					$result->Message = $tokenReq->Data['error'];
			}
		} else
		{
			$result->Code = $tokenReq->Code;
			$result->Message = $tokenReq->Message;
		}
		return $result;
	}

    /**
     * @param $externalID
     * @return PaynetResult
     */
	public function PaymentGet($externalID)
	{
		$path = '/api/Payments';
		$params = [
            'ExternalID' => $externalID
        ];

		$tokenReq =  $this->TokenGet(true);
		$result = new PaynetResult();

		if($tokenReq->IsOk())
		{
			$resultCheck = $this->callApi($path, 'GET',null, $params, $tokenReq->Data);
			if($resultCheck->IsOk())
			{
				$result->Code = $resultCheck->Code;

				if(array_key_exists('Code',$resultCheck->Data))
				{
						$result->Code = $resultCheck->Data['Code'];
						$result->Message = $resultCheck->Data['Message'];
				}else
				{
					$result->Data = $resultCheck->Data;
				}

			}else
				$result = $resultCheck;
		}else
		{
			$result->Code = $tokenReq->Code;
			$result->Message = $tokenReq->Message;
		}
		return $result;
	}

    /**
     * @param $pRequest
     * @return PaynetResult
     */
	public function FormCreate($pRequest)
	{
		$result = new PaynetResult();
		$result->Code = PaynetCode::SUCCESS;

			//----------------- preparing a service  ----------------------------
			$_service_name = '';
			$product_line = 0;
			$_service_item = "";
			//-------------------------------------------------------------------
			$pRequest->ExpiryDate = $this->ExpiryDateGet(self::EXPIRY_DATE_HOURS);

			$amount = 0;
			foreach ( $pRequest->Service["Products"] as $item ) {
					$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][LineNo]" value="'.htmlspecialchars_decode($item['LineNo']).'"/>';
					$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][Code]" value="'.htmlspecialchars_decode($item['Code']).'"/>';
					$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][BarCode]" value="'.htmlspecialchars_decode($item['Barcode']).'"/>';
					$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][Name]" value="'.htmlspecialchars_decode($item['Name']).'"/>';
					$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][Description]" value="'.htmlspecialchars_decode($item['Description']).'"/>';
					$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][Quantity]" value="'.htmlspecialchars_decode($item['Quantity'] ).'"/>';
					$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][UnitPrice]" value="'.htmlspecialchars_decode(($item['UnitPrice'])).'"/>';
					$product_line++;
					$amount += $item['Quantity']/100 * $item['UnitPrice'];
			}

			$pRequest->Service["Amount"] = $amount;
		    $signature = $this->SignatureGet($pRequest);
			$pp_form =  '<form method="POST" action="'.$this->paynet_base_ui_url.'">'.
						'<input type="hidden" name="ExternalID" value="'.$pRequest->ExternalID.'"/>'.
						'<input type="hidden" name="Services[0][Description]" value="'.htmlspecialchars_decode($pRequest->Service["Description"]).'"/>'.
						'<input type="hidden" name="Services[0][Name]" value="'.htmlspecialchars_decode($pRequest->Service["Name"]).'"/>'.
						'<input type="hidden" name="Services[0][Amount]" value="'.$amount.'"/>'.
						$_service_item.
						'<input type="hidden" name="Currency" value="'.$pRequest->Currency.'"/>'.
						'<input type="hidden" name="Merchant" value="'.$this->merchant_code.'"/>'.
						'<input type="hidden" name="Customer.Code"   value="'.htmlspecialchars_decode($pRequest->Customer['Code']).'"/>'.
						'<input type="hidden" name="Customer.Name"   value="'.htmlspecialchars_decode($pRequest->Customer['Name']).'"/>'.
						'<input type="hidden" name="Customer.Address"   value="'.htmlspecialchars_decode($pRequest->Customer['Address']).'"/>'.
						'<input type="hidden" name="Payer.Email"   value="v.bragari@ggg.md"/>'.
						'<input type="hidden" name="Payer.Name"   value="Oleg"/>'.
						'<input type="hidden" name="Payer.Surname"   value="Stoianov"/>'.
						'<input type="hidden" name="Payer.Mobile"   value="37360000000"/>'.
						'<input type="hidden" name="ExternalDate" value="'.htmlspecialchars_decode($this->ExternalDate()).'"/>'.
						'<input type="hidden" name="LinkUrlSuccess" value="'.htmlspecialchars_decode($pRequest->LinkSuccess).'"/>'.
						'<input type="hidden" name="LinkUrlCancel" value="'.htmlspecialchars_decode($pRequest->LinkCancel).'"/>'.
						'<input type="hidden" name="ExpiryDate"   value="'.htmlspecialchars_decode($pRequest->ExpiryDate).'"/>'.
						'<input type="hidden" name="Signature" value="'.$signature.'"/>'.
						'<input type="hidden" name="Lang" value="'.$pRequest->Lang.'"/>'.
						'<input type="submit" value="GO to a payment gateway of paynet" />'.
						'</form>';
		$result->Data = $pp_form;
		return $result;
	}

    /**
     * @param $pRequest
     * @return PaynetResult
     */
	public  function PaymentReg($pRequest)
	{
		$path = '/api/Payments/Send';
		$pRequest->ExpiryDate = $this->ExpiryDateGet(self::EXPIRY_DATE_HOURS);
		//------------- calculating total amount
		foreach ( $pRequest->Service[0]['Products'] as $item ) {
            $pRequest->Service[0]['Amount'] += ($item['Quantity']/100) * $item['UnitPrice'];
		}

		$params = [
			'Invoice' => $pRequest->ExternalID,
			'MerchantCode' => $this->merchant_code,
			'LinkUrlSuccess' =>  $pRequest->LinkSuccess,
			'LinkUrlCancel' => $pRequest->LinkCancel,
			'Customer' => $pRequest->Customer,
			'Payer' => $pRequest->Customer,
			'Currency' => 498,
			'ExternalDate' => $this->ExternalDate(),
			'ExpiryDate' => $this->ExpiryDateGet(self::EXPIRY_DATE_HOURS),
			'Services' => $pRequest->Service,
			'Lang' => $pRequest->Lang
        ];

		$tokenReq =  $this->TokenGet(true);
		$result = new PaynetResult();

		if($tokenReq->IsOk())
		{
			$resultCheck = $this->callApi($path, 'POST', $params,[], $tokenReq->Data);

			if($resultCheck->IsOk())
			{
				$result->Code = $resultCheck->Code;

				if(array_key_exists('Code',$resultCheck->Data))
				{
						$result->Code = $resultCheck->Data['Code'];
						$result->Message = $resultCheck->Data['Message'];
				} else {
					$pp_form =  '<form method="POST" action="'.$this->paynet_base_ui_server_url.'">'.
					'<input type="hidden" name="operation" value="'.htmlspecialchars_decode($resultCheck->Data['PaymentId']).'"/>'.
					'<input type="hidden" name="LinkUrlSucces" value="'.htmlspecialchars_decode($pRequest->LinkSuccess).'"/>'.
					'<input type="hidden" name="LinkUrlCancel" value="'.htmlspecialchars_decode($pRequest->LinkCancel).'"/>'.
					'<input type="hidden" name="ExpiryDate"   value="'.htmlspecialchars_decode($pRequest->ExpiryDate).'"/>'.
					'<input type="hidden" name="Signature" value="'.$resultCheck->Data['Signature'].'"/>'.
					'<input type="hidden" name="Lang" value="'.$pRequest->Lang.'"/>'.
					'<input type="submit" value="GO to a payment gateway of paynet" />'.
					'</form>';
					$result->Data = $pp_form;
				}
			} else {
                $result = $resultCheck;
            }
		}else {
			$result->Code = $tokenReq->Code;
			$result->Message = $tokenReq->Message;
		}

		return $result;
	}

    /**
     * @param $path
     * @param $method
     * @param $params
     * @param $query_params
     * @param $headers
     * @return PaynetResult
     */
	private function callApi($path, $method = 'GET', $params = [], $query_params = [], $headers = [])
    {
		$result = new PaynetResult();

        $url = $this->api_base_url . $path;
        if (count($query_params) > 0) {
            $url .= '?' . http_build_query($query_params);
        }

        $ch = curl_init($url);
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($method != 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));// json_encode($params));
		}

        $json_response = curl_exec($ch);

        if ($json_response === false) {
            /*
             * If an error occurred, remember the error
             * and return false.
             */
            $result->Message = curl_error($ch).', '.curl_errno($ch);
			$result->Code = PaynetCode::CONNECTION_ERROR;
            //print_r(curl_errno($ch));

            // Remember to close the cURL object
            curl_close($ch);

            return $result;
        }

        /*
         * No error, just decode the JSON response, and return it.
         */
        $result->Data = json_decode($json_response, true);

        // Remember to close the cURL object
        curl_close($ch);
		$result->Code = PaynetCode::SUCCESS;

        return $result;
    }

    /**
     * @param $addHours
     * @return string
     */
	private function ExpiryDateGet($addHours)
	{
		$date = strtotime("+".$addHours." hour");

		return date('Y-m-d', $date).'T'.date('H:i:s', $date);
	}

    /**
     * @param $addHours
     * @return string
     */
	public function ExternalDate($addHours = self::ADAPTING_HOURS)
	{
		$date = strtotime("+".$addHours." hour");

		return date('Y-m-d', $date).'T'.date('H:i:s', $date);
	}

    /**
     * @param $request
     * @return string
     */
	private function SignatureGet($request)
	{
			$_sing_raw  = $request->Currency;
			$_sing_raw .= $request->Customer['Address'].$request->Customer['Code'].$request->Customer['Name'];
			$_sing_raw .= $request->ExpiryDate.strval($request->ExternalID).$this->merchant_code;
			$_sing_raw .= $request->Service['Amount'].$request->Service['Name'].$request->Service['Description'];
			$_sing_raw .= $this->merchant_secret_key;

			return base64_encode(md5($_sing_raw, true));
	}

    /**
     * @param $name
     * @return null
     */
	public function __get ($name) {
        return $this->$name ?? null;
    }

    /**
     * @param $data
     * @return array|void
     */
    public function proceedCharge($data)
    {
        $prequest = $this->prepareDataForPayment($data);

        $formObj = $this->PaymentReg($prequest);

        if($formObj->Code == PaynetCode::SUCCESS) {
            $data = $formObj->Data;
           return ['status' => 'success', 'data' => $data];
        } else {
            logger()->channel('payment')->error("Paynet Error Code: " . $formObj->Code . " Paynet Error Message: " . $formObj->Message);
        }
    }

    /**
     * @param $data
     * @return PaynetRequest
     */
    private function prepareDataForPayment($data): PaynetRequest
    {
        $order = $data['order'];

        $order_items = $order->orderItems->map(function($item) {
            return [
                'Code' => $item->orderable->code,
                //'Barcode' => '1001',
                'Name' => $item->orderable->title,
                'Description' => $item->orderable->description,
                'Quantity' => 100, 	// 200 = 2.00  two
                'UnitPrice' => intval($item->price * 100)
            ];
        })->toArray();

        $prequest = new PaynetRequest();
        $prequest->ExternalID =  intval(microtime(true)) * 1000;
        $prequest->LinkSuccess = route('paynet.ok', ['id' => $prequest->ExternalID, 'order' => $order->id]);
        $prequest->LinkCancel =  route('paynet.cancel', ['id' => $prequest->ExternalID, 'order' => $order->id]);
        $prequest->Lang = app()->getLocale();
        $prequest->Products = $order_items;
        $prequest->Service =
            [
                [
                    'Name'		 => 'Invitro ',
                    'Description'=> '',
                    'Amount'	=> $prequest->Amount - $data['wallet_partial_sum']*100,
                    'Products'	=> $prequest->Products
                ],
            ];

        $prequest->Customer =
            [
                'Code' 		=> 'ivitro',
                'Address' 	=> 'www.paynet.md',
                'Name' 		=> 'IVITRO'
            ];

        return $prequest;
    }

    /**
     * @param $wallet_transaction
     * @return array
     * @throws Exception
     */
    public function topUpWallet($wallet_transaction)
    {
        $prequest = new PaynetRequest();
        $prequest->ExternalID =  intval(microtime(true)) * 1000;
        $prequest->LinkSuccess = route('paynet.wallet.ok', ['wallet_transaction' => $wallet_transaction->id,'id' => $prequest->ExternalID]);
        $prequest->LinkCancel =  route('paynet.wallet.cancel', ['wallet_transaction' => $wallet_transaction->id,'id' => $prequest->ExternalID]);
        $prequest->Lang = app()->getLocale();
        $prequest->Products = [];
        $prequest->Service =
            [
                [
                    'Name'		 => 'Invitro ',
                    'Description'=> 'Top up Wallet',
                    'Amount'	=> intval($wallet_transaction->amount * 100),
                    'Products'	=> []
                ],
            ];

        $prequest->Customer =
            [
                'Code' 		=> 'ivitro',
                'Address' 	=> 'www.paynet.md',
                'Name' 		=> 'IVITRO'
            ];

        $formObj = $this->PaymentReg($prequest);

        if($formObj->Code == PaynetCode::SUCCESS) {
            $data = $formObj->Data;
            return ['status' => 'success', 'data' => $data];
        }

        throw new Exception('Payment error' . json_encode($formObj));
    }

    /**
     * @return bool|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function callbackIsOk() {
        // get callback post data
        $api = new PaynetEcomAPI();
        $payment_info = file_get_contents('php://input');
        $payment_obj = json_decode($payment_info, true);

        // reformat date remove milliseconds
        $eventDate = Carbon::parse($payment_obj['EventDate'])->format('Y-m-d\TH:i:s');
        $statusDate = Carbon::parse($payment_obj['Payment']['StatusDate'])->format('Y-m-d\TH:i:s');

        // make signature
        $prepared_string = $eventDate . $payment_obj['Eventid'] . $payment_obj['EventType']
            . $payment_obj['Payment']['Amount'] . $payment_obj['Payment']['Customer'] . $payment_obj['Payment']['ExternalId']
            . $payment_obj['Payment']['Id'] . $payment_obj['Payment']['Merchant'] . $statusDate;

        $env = config('app.env');

        $secret_key = config("payment.paynet.{$env}.MERCHANT_SEC_KEY");
        $message = $prepared_string . $secret_key;
        $signature = base64_encode(md5($message, true));

        // Errors flow
        if(!$payment_obj){
            logger()->channel('payment')->error('Callback is empty');

            return response('Not found', 404);
        }
        if ($signature !== apache_request_headers()['Hash']) {
            logger()->channel('payment')->error('Hash not match');

            return response('Error', 403);
        }

        // Success flow
        $checkObj = $api->PaymentGet($payment_obj['Payment']['ExternalId']);

        return $checkObj->IsOk();
    }
}
