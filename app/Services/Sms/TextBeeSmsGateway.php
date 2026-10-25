<?php

namespace App\Services\Sms;

use App\Exceptions\Sms\SmsGatewayException;
use App\Support\Sms\PhilippineSmsRecipient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TextBeeSmsGateway
{
    /**
     * Submit one SMS message through TextBee.
     *
     * We intentionally do not perform blind automatic HTTP retries.
     *
     * A timeout can happen after TextBee already accepted the request.
     * Automatically retrying could therefore send the same SMS twice.
     *
     * @throws SmsGatewayException
     */
    public function send(
        string $number,
        string $message
    ): SmsSendResult {
        /*
        |--------------------------------------------------------------------------
        | API Key
        |--------------------------------------------------------------------------
        */

        $apiKey =
            config(
                'sms.textbee.api_key'
            );

        if (
            ! is_string($apiKey)
            || trim($apiKey) === ''
        ) {
            throw SmsGatewayException::definite(
                'TextBee API key is not configured.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Endpoint
        |--------------------------------------------------------------------------
        */

        $endpoint =
            config(
                'sms.textbee.endpoint'
            );

        if (
            ! is_string($endpoint)
            || trim($endpoint) === ''
        ) {
            throw SmsGatewayException::definite(
                'TextBee endpoint is not configured.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize Recipient
        |--------------------------------------------------------------------------
        |
        | StyleHub stores Philippine numbers in local format:
        |
        | 09171234567
        |
        | TextBee requires international E.164 format:
        |
        | +639171234567
        |
        */

        $recipient =
            $this->toE164PhilippineNumber(
                $number
            );

        if ($recipient === null) {
            throw SmsGatewayException::definite(
                'TextBee requires a valid Philippine mobile number.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Message
        |--------------------------------------------------------------------------
        */

        $message =
            trim(
                $message
            );

        if ($message === '') {
            throw SmsGatewayException::definite(
                'SMS message cannot be empty.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Request Payload
        |--------------------------------------------------------------------------
        */

        $payload = [
            'recipients' => [
                $recipient,
            ],

            'message' =>
                $message,
        ];

        /*
        |--------------------------------------------------------------------------
        | Specific TextBee Gateway Device
        |--------------------------------------------------------------------------
        |
        | You already added your OPPO TextBee Device ID to .env.
        |
        | When supplied, TextBee will use this exact registered Android
        | device instead of automatically choosing another device.
        |
        */

        $deviceId =
            config(
                'sms.textbee.device_id'
            );

        if (
            is_string($deviceId)
            && trim($deviceId) !== ''
        ) {
            $payload['deviceId'] =
                trim(
                    $deviceId
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Send Request
        |--------------------------------------------------------------------------
        */

        try {
            $response =
                Http::withHeaders([
                    'x-api-key' =>
                        trim(
                            $apiKey
                        ),
                ])
                    ->acceptJson()
                    ->asJson()
                    ->connectTimeout(
                        (int) config(
                            'sms.textbee.connect_timeout',
                            5
                        )
                    )
                    ->timeout(
                        (int) config(
                            'sms.textbee.timeout',
                            15
                        )
                    )
                    ->post(
                        trim(
                            $endpoint
                        ),
                        $payload
                    );
        } catch (ConnectionException $exception) {
            /*
             * A timeout or network failure does not guarantee that TextBee
             * failed to receive the request.
             *
             * We therefore classify this as an uncertain outcome so that
             * StyleHub does not automatically send a duplicate SMS.
             */

            throw SmsGatewayException::uncertain(
                message:
                    'Unable to confirm the TextBee delivery request outcome.',

                previous:
                    $exception,
            );
        }

        /*
        |--------------------------------------------------------------------------
        | HTTP Errors
        |--------------------------------------------------------------------------
        */

        if (! $response->successful()) {
            $status =
                $response->status();

            /*
             * A 4xx response means TextBee explicitly rejected the request.
             *
             * Examples:
             *
             * 400 = invalid request/device or unavailable gateway
             * 401 = invalid/revoked API key
             * 404 = specified device cannot be found
             * 429 = plan/message limit reached
             *
             * No successful send was accepted, so a later deliberate retry
             * is safe after the underlying problem has been corrected.
             */

            if (
                $status >= 400
                && $status < 500
            ) {
                throw SmsGatewayException::definite(
                    sprintf(
                        'TextBee rejected the request with HTTP status %d.',
                        $status
                    )
                );
            }

            /*
             * With a 5xx response we use the conservative classification.
             *
             * The provider could potentially have processed the request
             * before returning the server error.
             */

            throw SmsGatewayException::uncertain(
                sprintf(
                    'TextBee returned HTTP status %d and the delivery outcome is uncertain.',
                    $status
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Parse Response
        |--------------------------------------------------------------------------
        */

        $data =
            $response->json(
                'data'
            );

        if (! is_array($data)) {
            throw SmsGatewayException::uncertain(
                'TextBee returned an invalid success response.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Queued Response
        |--------------------------------------------------------------------------
        |
        | Standard TextBee response:
        |
        | {
        |     "data": {
        |         "success": true,
        |         "message": "SMS added to queue for processing",
        |         "smsBatchId": "...",
        |         "recipientCount": 1
        |     }
        | }
        |
        */

        $batchId =
            $data[
                'smsBatchId'
            ]
            ?? null;

        if (
            is_string($batchId)
            && trim($batchId) !== ''
        ) {
            if (
                array_key_exists(
                    'success',
                    $data
                )
                && $data['success'] === false
            ) {
                throw SmsGatewayException::definite(
                    'TextBee rejected the SMS message.'
                );
            }

            return new SmsSendResult(
                providerMessageId:
                    trim(
                        $batchId
                    ),

                providerStatus:
                    'Queued',
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Immediate Dispatch Response
        |--------------------------------------------------------------------------
        |
        | TextBee may return successCount and failureCount instead of
        | smsBatchId when the request is immediately pushed to the phone.
        |
        */

        $successCount =
            $data[
                'successCount'
            ]
            ?? null;

        $failureCount =
            $data[
                'failureCount'
            ]
            ?? null;

        if (
            is_numeric($successCount)
            && is_numeric($failureCount)
        ) {
            $successCount =
                (int) $successCount;

            $failureCount =
                (int) $failureCount;

            /*
             * StyleHub currently sends one recipient per gateway call.
             */
            if (
                $successCount >= 1
                && $failureCount === 0
            ) {
                return new SmsSendResult(
                    providerMessageId:
                        null,

                    providerStatus:
                        'Dispatched',
                );
            }

            if (
                $successCount === 0
                && $failureCount >= 1
            ) {
                throw SmsGatewayException::definite(
                    'TextBee could not push the SMS to the gateway device.'
                );
            }

            /*
             * A mixed result is unusual for our one-recipient calls.
             * Treat it conservatively rather than accidentally resending.
             */

            throw SmsGatewayException::uncertain(
                'TextBee returned a partial SMS dispatch result.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Explicit Failure
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'success',
                $data
            )
            && $data['success'] === false
        ) {
            throw SmsGatewayException::definite(
                'TextBee rejected the SMS message.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Unknown Successful HTTP Response
        |--------------------------------------------------------------------------
        |
        | HTTP succeeded, but we cannot confidently identify whether the
        | provider queued or dispatched the SMS.
        |
        | Do not blindly retry.
        |
        */

        throw SmsGatewayException::uncertain(
            'TextBee returned an incomplete success response.'
        );
    }

    /**
     * Convert the StyleHub Philippine mobile format into E.164.
     *
     * Examples:
     *
     * 09171234567
     *      ↓
     * +639171234567
     *
     * +639171234567
     *      ↓
     * +639171234567
     */
    private function toE164PhilippineNumber(
        string $number
    ): ?string {
        $normalized =
            PhilippineSmsRecipient::normalize(
                $number
            );

        if ($normalized === null) {
            return null;
        }

        return '+63'.substr(
            $normalized,
            1
        );
    }
}