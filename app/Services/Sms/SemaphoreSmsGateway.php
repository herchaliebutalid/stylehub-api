<?php

namespace App\Services\Sms;

use App\Exceptions\Sms\SmsGatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SemaphoreSmsGateway
{
    /**
     * Submit one SMS message.
     *
     * No blind HTTP retry is performed because a timeout can occur
     * after the remote provider already accepted the SMS.
     *
     * @throws SmsGatewayException
     */
    public function send(
        string $number,
        string $message
    ): SmsSendResult {
        $apiKey =
            config(
                'sms.semaphore.api_key'
            );

        if (
            ! is_string($apiKey)
            || trim($apiKey) === ''
        ) {
            /*
             * No network request was made.
             * This is therefore definitely safe to retry once
             * configuration is corrected.
             */
            throw SmsGatewayException::definite(
                'Semaphore API key is not configured.'
            );
        }

        $endpoint =
            config(
                'sms.semaphore.endpoint'
            );

        if (
            ! is_string($endpoint)
            || trim($endpoint) === ''
        ) {
            throw SmsGatewayException::definite(
                'Semaphore endpoint is not configured.'
            );
        }

        $payload = [
            'apikey' =>
                $apiKey,

            'number' =>
                $number,

            'message' =>
                $message,
        ];

        $senderName =
            config(
                'sms.semaphore.sender_name'
            );

        if (
            is_string($senderName)
            && trim($senderName) !== ''
        ) {
            $payload['sendername'] =
                trim(
                    $senderName
                );
        }

        try {
            $response =
                Http::asForm()
                    ->acceptJson()
                    ->connectTimeout(
                        (int) config(
                            'sms.semaphore.connect_timeout',
                            5
                        )
                    )
                    ->timeout(
                        (int) config(
                            'sms.semaphore.timeout',
                            15
                        )
                    )
                    ->post(
                        $endpoint,
                        $payload
                    );
        } catch (ConnectionException $exception) {
            /*
             * ConnectionException may also represent a timeout after the
             * remote server received the request.
             *
             * We therefore cannot safely assume the SMS was not accepted.
             */
            throw SmsGatewayException::uncertain(
                message:
                    'Unable to confirm the Semaphore delivery request outcome.',

                previous:
                    $exception,
            );
        }

        if (! $response->successful()) {
            /*
             * 4xx responses are explicit client/provider rejection.
             *
             * The request did not successfully enter our accepted flow.
             */
            if (
                $response->status() >= 400
                && $response->status() < 500
            ) {
                throw SmsGatewayException::definite(
                    sprintf(
                        'Semaphore rejected the request with HTTP status %d.',
                        $response->status()
                    )
                );
            }

            /*
             * For 5xx errors we take the conservative approach.
             *
             * The provider may have processed the request before the
             * server-side error occurred.
             */
            throw SmsGatewayException::uncertain(
                sprintf(
                    'Semaphore returned HTTP status %d and the delivery outcome is uncertain.',
                    $response->status()
                )
            );
        }

        $responseData =
            $response->json();

        if (
            is_array($responseData)
            && array_is_list(
                $responseData
            )
        ) {
            $messageData =
                $responseData[0]
                ?? null;
        } else {
            $messageData =
                $responseData;
        }

        if (! is_array($messageData)) {
            /*
             * We received success HTTP status but could not interpret
             * the result. Do not blindly resend.
             */
            throw SmsGatewayException::uncertain(
                'Semaphore returned an invalid success response.'
            );
        }

        $messageId =
            $messageData[
                'message_id'
            ]
            ?? null;

        $status =
            $messageData[
                'status'
            ]
            ?? null;

        if (
            $messageId === null
            || ! is_string($status)
            || trim($status) === ''
        ) {
            throw SmsGatewayException::uncertain(
                'Semaphore returned an incomplete success response.'
            );
        }

        $normalizedStatus =
            strtolower(
                trim(
                    $status
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Explicit Provider Failure
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $normalizedStatus,
                [
                    'failed',
                    'refunded',
                ],
                true
            )
        ) {
            throw SmsGatewayException::definite(
                'Semaphore rejected the SMS message.'
            );
        }

        return new SmsSendResult(
            providerMessageId:
                (string) $messageId,

            providerStatus:
                trim(
                    $status
                ),
        );
    }
}