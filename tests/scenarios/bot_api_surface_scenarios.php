<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runBotApiSurfaceScenarios(array $context): void {
    extract($context);
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/deleteMyCommands'), 200, true);
    assertSameValue(true, $json['result'], 'deleteMyCommands возвращает true');
    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getMyCommands'), 200, true);
    assertSameValue([], $json['result'], 'deleteMyCommands очищает команды');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendInvoice', formBody([
        'chat_id' => '1001',
        'title' => 'Invoice',
        'description' => 'Unsupported invoice',
        'payload' => 'payload',
        'currency' => 'USD',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 501, false);
    assertSameValue(501, $json['error_code'], 'Неподдерживаемый Bot API метод возвращает 501');

}
