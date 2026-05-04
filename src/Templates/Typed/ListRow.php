<?php

namespace MessengerBot\Templates\Typed;

/**
 * One row in a list template (Meta {@code elements} item).
 *
 * When using {@see ListTopElementStyle::Large}, the first row should include {@see $imageUrl}.
 */
readonly class ListRow
{
    /**
     * @param  list<array<string, mixed>>|null  $buttons
     * @param  array<string, mixed>|null  $defaultAction  e.g. web_url default_action
     */
    public function __construct(
        public string $title,
        public ?string $subtitle = null,
        public ?string $imageUrl = null,
        public ?array $defaultAction = null,
        public ?array $buttons = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toMetaFields(): array
    {
        $out = ['title' => $this->title];
        if ($this->subtitle !== null && $this->subtitle !== '') {
            $out['subtitle'] = $this->subtitle;
        }
        if ($this->imageUrl !== null && $this->imageUrl !== '') {
            $out['image_url'] = $this->imageUrl;
        }
        if ($this->defaultAction !== null && $this->defaultAction !== []) {
            $out['default_action'] = $this->defaultAction;
        }
        if ($this->buttons !== null && $this->buttons !== []) {
            $out['buttons'] = $this->buttons;
        }

        return $out;
    }
}
