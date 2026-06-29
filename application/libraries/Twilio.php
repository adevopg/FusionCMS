<?php

use App\Config\Services;
use MX\CI;

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Twilio Verify (SMS) helper.
 *
 *  - start($phone)        sends an SMS code (POST /Verifications)
 *  - check($phone, $code) validates the code (POST /VerificationCheck)
 */
class Twilio
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->config->load('twilio', false, true);
    }

    public function enabled(): bool
    {
        return (bool) $this->CI->config->item('twilio_enabled')
            && $this->CI->config->item('twilio_account_sid')
            && $this->CI->config->item('twilio_auth_token')
            && $this->CI->config->item('twilio_verify_service_sid');
    }

    private function base(): string
    {
        return 'https://verify.twilio.com/v2/Services/' . $this->CI->config->item('twilio_verify_service_sid');
    }

    private function authHeader(): string
    {
        return 'Basic ' . base64_encode(
            $this->CI->config->item('twilio_account_sid') . ':' . $this->CI->config->item('twilio_auth_token')
        );
    }

    /**
     * Normalise a phone number to E.164-ish (keep leading + and digits).
     */
    public function normalize(string $phone): string
    {
        $phone = trim($phone);
        $plus  = (strncmp($phone, '+', 1) === 0) ? '+' : '';
        return $plus . preg_replace('/\D+/', '', $phone);
    }

    private function post(string $path, array $form): array
    {
        try {
            $response = Services::curlrequest()->post($this->base() . $path, [
                'headers'     => [
                    'Authorization' => $this->authHeader(),
                    'Accept'        => 'application/json',
                ],
                'form_params' => $form,
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $data = json_decode((string) $response->getBody(), true);
            return is_array($data) ? $data : [];
        } catch (Throwable $e) {
            log_message('error', 'Twilio error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Send an SMS verification code. Returns true if Twilio accepted it (status "pending").
     */
    public function start(string $phone): bool
    {
        $result = $this->post('/Verifications', [
            'To'      => $this->normalize($phone),
            'Channel' => 'sms',
        ]);

        return ($result['status'] ?? null) === 'pending';
    }

    /**
     * Check a verification code. Returns true when status is "approved".
     */
    public function check(string $phone, string $code): bool
    {
        $result = $this->post('/VerificationCheck', [
            'To'   => $this->normalize($phone),
            'Code' => $code,
        ]);

        return ($result['status'] ?? null) === 'approved';
    }
}
