<?php

namespace UtanvetEllenor;

use Exception;

class Client
{
    const SANDBOX_BASE_URL = 'https://sandbox.utanvet-ellenor.hu/';
    const PRODUCTION_BASE_URL = 'https://utanvet-ellenor.hu/';

    /**
     * API config-related parameters
     */
    public string $publicKey;
    public string $privateKey;
    public bool $sandbox = false;

    /**
     * Universally required parameters
     */
    public string $email;

    /**
     * Request-related required parameters
     */
    public float $threshold;

    /**
     * Signal-related required parameters
     */
    public int $outcome;
    public string $orderId;

    /**
     * Optional parameters
     */
    public ?string $countryCode;
    public ?string $postalCode;
    public ?string $phoneNumber;
    public ?string $addressLine;

    public function __construct(string $publicKey, string $privateKey)
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }

    public function getBaseUrl() : string
    {
        return ($this->sandbox ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL) . 'api/v2';
    }

    public function requireEmail()
    {
        if (!$this->email || $this->email == '') {
            throw new Exception('E-mail is required.');
        }
    }

    public function requireOutcome()
    {
        if (!in_array($this->outcome, [-1, 1], true)) {
            throw new Exception('Outcome value is not allowed. Possible values: -1, 1.');
        }
    }

    public function requireOrderId()
    {
        if (!$this->orderId || $this->orderId == '') {
            throw new Exception('Order ID is required.');
        }
    }

    public function requireThreshold()
    {
        if (filter_var($this->threshold, FILTER_VALIDATE_FLOAT) === false) {
            throw new Exception('Threshold value type is invalid, should be float.');
        }

        if ($this->threshold < -1 || 1 < $this->threshold) {
            throw new Exception('Threshold value is not allowed, should be between -1 and +1.');
        }
    }

    public function preparePayload()
    {
        $payload = [
            'email' => $this->email
        ];

        if (isset($this->outcome)) {
            $payload['outcome'] = $this->outcome;
        }

        if (isset($this->orderId)) {
            $payload['orderId'] = $this->orderId;
        }

        if (isset($this->threshold)) {
            $payload['threshold'] = $this->threshold;
        }

        if (isset($this->countryCode)) {
            $payload['countryCode'] = $this->countryCode;
        }

        if (isset($this->postalCode)) {
            $payload['postalCode'] = $this->postalCode;
        }

        if (isset($this->phoneNumber)) {
            $payload['phoneNumber'] = $this->phoneNumber;
        }

        if (isset($this->addressLine)) {
            $payload['addressLine'] = $this->addressLine;
        }

        return json_encode($payload);
    }
    
    public function prepareCurlOptions(string $endpoint) : array
    {
        return [
            CURLOPT_URL => $this->getBaseUrl() . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_NONE,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $this->preparePayload(),
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Authorization: Basic " . base64_encode($this->publicKey . ':' . $this->privateKey),
                "Content-Type: application/json"
            ],
        ];
    }

    public function execute(string $endpoint)
    {
        $curl = curl_init();
        $curlOptions = $this->prepareCurlOptions($endpoint);
        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if (!$err) {
            return json_decode($response, false);
        }

        return null;
    }

    public function sendRequest() : ?object
    {
        $this->requireEmail();
        $this->requireThreshold();

        return $this->execute('/request');
    }

    public function sendSignal() : ?object
    {
        $this->requireEmail();
        $this->requireOutcome();
        $this->requireOrderId();

        return $this->execute('/signal');
    }
}
