<?php

declare(strict_types=1);

require_once __DIR__ . '/bot_api_message_core_scenarios.php';
require_once __DIR__ . '/bot_api_media_method_scenarios.php';
require_once __DIR__ . '/bot_api_structured_method_scenarios.php';

/**
 * @param array<string, mixed> $context
 */
function runBotApiMessageScenarios(array $context): void {
    runBotApiMessageCoreScenarios($context);
    runBotApiMediaMethodScenarios($context);
    runBotApiStructuredMethodScenarios($context);
}
