<?php

declare(strict_types=1);

require_once __DIR__ . '/http_setup_scenarios.php';
require_once __DIR__ . '/import_export_scenarios.php';
require_once __DIR__ . '/bot_api_core_scenarios.php';
require_once __DIR__ . '/webhook_scenarios.php';
require_once __DIR__ . '/bot_api_message_scenarios.php';
require_once __DIR__ . '/chat_ui_scenarios.php';
require_once __DIR__ . '/long_polling_scenarios.php';
require_once __DIR__ . '/webhook_retry_scenarios.php';
require_once __DIR__ . '/imported_dialog_scenarios.php';
require_once __DIR__ . '/bot_api_surface_scenarios.php';
require_once __DIR__ . '/group_chat_scenarios.php';
require_once __DIR__ . '/media_scenarios.php';
require_once __DIR__ . '/callback_error_scenarios.php';

function runHttpTests(string $baseUrl, int $receiverPort): void {
    $context = [
        'baseUrl' => $baseUrl,
        'receiverPort' => $receiverPort,
        'token' => '123456:local-dev-token-test',
        'importedToken' => '654321:imported-local-dev-token',
    ];

    runHttpSetupScenarios($context);
    runImportExportScenarios($context);
    runBotApiCoreScenarios($context);
    runWebhookScenarios($context);
    runBotApiMessageScenarios($context);
    runChatUiScenarios($context);
    runLongPollingScenarios($context);
    runWebhookRetryScenarios($context);
    runImportedDialogScenarios($context);
    runBotApiSurfaceScenarios($context);
    runGroupChatScenarios($context);
    runMediaScenarios($context);
    runCallbackErrorScenarios($context);
}
