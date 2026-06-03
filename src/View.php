<?php

declare(strict_types=1);

namespace App;

final readonly class View {

    public function __construct(private string $templatesPath) {
    }

    /**
     * Рендерит шаблон внутри общего layout.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): void {
        extract($data, EXTR_SKIP);

        $contentTemplate = $this->templatesPath . '/' . $template . '.php';
        require $this->templatesPath . '/layout.php';
    }

    /**
     * Рендерит шаблон без общего layout для HTMX-фрагментов.
     *
     * @param array<string, mixed> $data
     */
    public function renderPartial(string $template, array $data = []): void {
        extract($data, EXTR_SKIP);

        require $this->templatesPath . '/' . $template . '.php';
    }
}
