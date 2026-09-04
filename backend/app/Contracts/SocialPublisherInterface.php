<?php

namespace App\Contracts;

interface SocialPublisherInterface
{
    /**
     * Publish a text post to the social platform.
     *
     * @param string $accountId External Account ID (e.g., Facebook Page ID)
     * @param string $accessToken Access Token for the platform
     * @param string $message The formatted text content
     * @param string $idempotencyKey A unique key to prevent duplicate publishing
     * @return array
     * @throws \Exception
     */
    public function publishText(
        string $accountId,
        string $accessToken,
        string $message,
        string $idempotencyKey
    ): array;
}
