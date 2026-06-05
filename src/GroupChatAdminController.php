<?php

declare(strict_types=1);

namespace App;

/**
 * UI-маршруты управления group/supergroup чатами и участниками.
 */
final readonly class GroupChatAdminController {

    public function __construct(
        private ChatRepository $chats,
        private ProfileRepository $profiles,
        private BotRepository $bots,
        private View $view,
    ) {
    }

    public function handle(string $method, string $path): bool {
        if ($method === 'GET' && $path === '/group-chats') {
            $this->index();
            return true;
        }

        if ($method === 'GET' && preg_match('#^/group-chats/(-?\d+)$#', $path, $matches) === 1) {
            $this->show((int) $matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/group-chats/(-?\d+)/title$#', $path, $matches) === 1) {
            $this->updateTitle((int) $matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/group-chats/(-?\d+)/members$#', $path, $matches) === 1) {
            $this->addMember((int) $matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/group-chats/(-?\d+)/members/(\d+)/delete$#', $path, $matches) === 1) {
            $this->removeMember((int) $matches[1], (int) $matches[2]);
            return true;
        }

        return false;
    }

    private function index(): void {
        $this->render('group-chats/index', [
            'title' => 'Групповые чаты',
            'chats' => $this->chats->groupChats(),
        ]);
    }

    private function show(int $chatId, array $errors = []): void {
        $chat = $this->chats->findGroupByChatId($chatId);
        if ($chat === null) {
            Response::json(['ok' => false, 'error' => 'Групповой чат не найден'], 404);
            return;
        }

        $members = $this->chats->membersByChatId($chatId);
        $memberIds = [];
        foreach ($members as $member) {
            $memberIds[(int) $member['id']] = true;
        }

        $availableProfiles = array_values(array_filter(
            $this->profiles->all(),
            fn(array $profile): bool => !isset($memberIds[(int) $profile['id']]),
        ));

        $this->render('group-chats/show', [
            'title' => 'Участники группы',
            'chat' => $chat,
            'members' => $members,
            'availableProfiles' => $availableProfiles,
            'errors' => $errors,
        ]);
    }

    private function updateTitle(int $chatId): void {
        $chat = $this->chats->findGroupByChatId($chatId);
        $title = trim((string) ($_POST['title'] ?? ''));

        if ($chat === null) {
            Response::json(['ok' => false, 'error' => 'Групповой чат не найден'], 404);
            return;
        }

        if ($title === '' || mb_strlen($title) > 128) {
            http_response_code(422);
            $this->show($chatId, ['title' => 'Title должен быть непустой строкой до 128 символов.']);
            return;
        }

        $this->chats->updateGroupTitle($chatId, $title);

        Response::redirect('/group-chats/' . $chatId);
    }

    private function addMember(int $chatId): void {
        $chat = $this->chats->findGroupByChatId($chatId);
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $profile = $profileId > 0 ? $this->profiles->find($profileId) : null;

        if ($chat === null) {
            Response::json(['ok' => false, 'error' => 'Групповой чат не найден'], 404);
            return;
        }

        if ($profile === null) {
            http_response_code(422);
            $this->show($chatId, ['profile_id' => 'Выберите существующего пользователя.']);
            return;
        }

        $this->profiles->update($profileId, array_replace($profile, [
            'chat_id' => $chatId,
            'chat_type' => (string) $chat['type'],
            'enabled' => ((int) $profile['enabled']) === 1 ? '1' : null,
        ]));

        Response::redirect('/group-chats/' . $chatId);
    }

    private function removeMember(int $chatId, int $profileId): void {
        $chat = $this->chats->findGroupByChatId($chatId);
        $profile = $this->profiles->find($profileId);

        if ($chat === null) {
            Response::json(['ok' => false, 'error' => 'Групповой чат не найден'], 404);
            return;
        }

        if ($profile === null || (int) $profile['chat_id'] !== $chatId) {
            Response::json(['ok' => false, 'error' => 'Участник не найден'], 404);
            return;
        }

        $this->profiles->update($profileId, array_replace($profile, [
            'chat_id' => (int) $profile['user_id'],
            'chat_type' => 'private',
            'enabled' => ((int) $profile['enabled']) === 1 ? '1' : null,
        ]));

        Response::redirect('/group-chats/' . $chatId);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data = []): void {
        $data['allUsers'] = $this->profiles->all();
        $data['allBots'] = $this->bots->all();
        $this->view->render($template, $data);
    }
}
