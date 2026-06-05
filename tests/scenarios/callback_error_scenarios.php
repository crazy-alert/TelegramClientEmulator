<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runCallbackErrorScenarios(array $context): void {
    extract($context);
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/answerCallbackQuery', formBody([]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "callback_query_id" is required', $json['description'], 'answerCallbackQuery требует callback_query_id');
}
