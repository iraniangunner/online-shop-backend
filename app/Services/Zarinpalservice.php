<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ZarinpalService
{
    private string $merchantId;

    private bool $sandbox;

    public function __construct()
    {
        $this->merchantId = config('services.zarinpal.merchant_id');
        $this->sandbox = config('services.zarinpal.sandbox', true);
    }

    private function baseUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/'
            : 'https://payment.zarinpal.com/pg/v4/payment/';
    }

    private function startUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox.zarinpal.com/pg/StartPay/'
            : 'https://payment.zarinpal.com/pg/StartPay/';
    }

    /**
     * درخواست پرداخت. amount به تومان/ریال (بسته به تنظیمات حساب زرین‌پالت) است.
     *
     * @return array{success: bool, authority?: string, payment_url?: string, message?: string}
     */
    public function request(int $amount, string $callbackUrl, string $description, string $mobile = ''): array
    {
        $response = Http::post($this->baseUrl() . 'request.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'callback_url' => $callbackUrl,
            'description' => $description,
            'metadata' => ['mobile' => $mobile],
        ]);

        $data = $response->json();

        if (($data['data']['code'] ?? null) === 100) {
            $authority = $data['data']['authority'];

            return [
                'success' => true,
                'authority' => $authority,
                'payment_url' => $this->startUrl() . $authority,
            ];
        }

        return [
            'success' => false,
            'message' => $data['errors']['message'] ?? 'خطا در اتصال به درگاه پرداخت.',
        ];
    }

    /**
     * تأیید پرداخت بعد از بازگشت کاربر از درگاه.
     *
     * @return array{success: bool, ref_id?: string, message?: string}
     */
    public function verify(int $amount, string $authority): array
    {
        $response = Http::post($this->baseUrl() . 'verify.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'authority' => $authority,
        ]);

        $data = $response->json();

        if (in_array($data['data']['code'] ?? null, [100, 101], true)) {
            return [
                'success' => true,
                'ref_id' => (string) $data['data']['ref_id'],
            ];
        }

        return [
            'success' => false,
            'message' => $data['errors']['message'] ?? 'پرداخت تأیید نشد.',
        ];
    }
}
