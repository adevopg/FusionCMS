<?php

use App\Config\Services;

/**
 * SumUp API client (Online Payments).
 *
 * Flow (no webhooks — status is polled via the API):
 *   1. createCheckout()  -> POST /v0.1/checkouts          (status PENDING)
 *   2. the card widget processes the payment on the client
 *   3. getCheckout()     -> GET  /v0.1/checkouts/{id}     (status PAID / FAILED)
 */
class Sumup_model extends CI_Model
{
    private function cfg($key)
    {
        $this->load->config('sumup');
        return $this->config->item($key);
    }

    private function base(): string
    {
        return rtrim((string) $this->cfg('sumup_api_base'), '/');
    }

    /**
     * Returns a Bearer token: the API key if set, otherwise an OAuth2
     * client_credentials access token.
     */
    private function token(): ?string
    {
        $apiKey = trim((string) $this->cfg('sumup_api_key'));
        if ($apiKey !== '') {
            return $apiKey;
        }

        $id = trim((string) $this->cfg('sumup_client_id'));
        $secret = trim((string) $this->cfg('sumup_client_secret'));
        if ($id === '' || $secret === '') {
            return null;
        }

        try {
            $response = Services::curlrequest()->post($this->base() . '/token', [
                'headers'     => ['Accept' => 'application/json'],
                'form_params' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $id,
                    'client_secret' => $secret,
                ],
                'http_errors' => false,
                'timeout'     => 30,
            ]);
            $data = json_decode((string) $response->getBody(), true);
            return $data['access_token'] ?? null;
        } catch (Throwable $e) {
            log_message('error', 'SumUp token error: ' . $e->getMessage());
            return null;
        }
    }

    private function request(string $method, string $path, ?array $body = null): ?array
    {
        $token = $this->token();
        if (!$token) {
            return ['error' => 'SumUp credentials are not configured.'];
        }

        $options = [
            'headers'     => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'http_errors' => false,
            'timeout'     => 30,
        ];

        if ($body !== null) {
            $options['body'] = json_encode($body);
        }

        try {
            $response = Services::curlrequest()->request($method, $this->base() . $path, $options);
            $decoded  = json_decode((string) $response->getBody(), true);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            log_message('error', 'SumUp API error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a checkout. Returns the SumUp checkout object (incl. 'id', 'status').
     *
     * @param string $reference   unique checkout reference
     * @param float  $amount      amount in major units (e.g. 12.00)
     * @param string $description shown to the customer
     */
    public function createCheckout(string $reference, float $amount, string $description): ?array
    {
        return $this->request('POST', '/v0.1/checkouts', [
            'checkout_reference' => $reference,
            'amount'             => round($amount, 2),
            'currency'           => $this->cfg('sumup_currency'),
            'merchant_code'      => $this->cfg('sumup_merchant_code'),
            'description'        => $description,
        ]);
    }

    /**
     * Get a checkout by id (to poll its status).
     */
    public function getCheckout(string $id): ?array
    {
        return $this->request('GET', '/v0.1/checkouts/' . rawurlencode($id));
    }
}
