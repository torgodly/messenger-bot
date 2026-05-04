<?php

namespace MessengerBot\Support;

/**
 * Read/write Messenger-related keys in a {@code .env} file (simple KEY=value lines).
 */
class MessengerBotEnvWriter
{
    public function __construct(
        protected string $path,
    ) {}

    public static function forApplicationBasePath(string $basePath): self
    {
        return new self(rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.env');
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function hasLine(string $key): bool
    {
        if (! $this->exists()) {
            return false;
        }

        $content = (string) file_get_contents($this->path);

        return (bool) preg_match('/^'.preg_quote($key, '/').'=/m', $content);
    }

    /**
     * Returns null when the key line is missing; otherwise the value (possibly empty string).
     */
    public function get(string $key): ?string
    {
        if (! $this->hasLine($key)) {
            return null;
        }

        $content = (string) file_get_contents($this->path);
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $content, $m)) {
            return null;
        }

        return $this->unquote(trim($m[1]));
    }

    /**
     * Set or replace one key. Value is escaped for .env rules.
     */
    public function put(string $key, string $value): void
    {
        $escaped = $this->escapeValue($value);
        if ($this->exists()) {
            $content = (string) file_get_contents($this->path);
        } else {
            $content = '';
        }

        $pattern = '/^'.preg_quote($key, '/').'=.*/m';
        $line = "{$key}={$escaped}";
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $line, $content);
        } else {
            $content = rtrim($content);
            if ($content !== '') {
                $content .= "\n";
            }
            $content .= $line."\n";
        }

        file_put_contents($this->path, $content);
    }

    /**
     * @param  array<string, string>  $pairs
     */
    public function putMany(array $pairs): void
    {
        foreach ($pairs as $k => $v) {
            $this->put((string) $k, (string) $v);
        }
    }

    /**
     * Append a small commented starter block (App ID, App Secret, and verify token if missing).
     * Skips if this section was already added or {@code MESSENGER_BOT_APP_ID} exists.
     */
    public function appendMessengerStarterIfMissing(): void
    {
        if ($this->exists()) {
            $raw = (string) file_get_contents($this->path);
            if (str_contains($raw, '# --- torgodly/messenger-bot ---') || $this->hasLine('MESSENGER_BOT_APP_ID')) {
                return;
            }
        }

        $lines = [
            '# --- torgodly/messenger-bot ---',
            '# Optional: add more MESSENGER_BOT_* lines here to override config/messenger-bot.php',
            '',
            'MESSENGER_BOT_APP_ID=',
            'MESSENGER_BOT_APP_SECRET=',
        ];
        if (! $this->hasLine('MESSENGER_BOT_VERIFY_TOKEN')) {
            $lines[] = 'MESSENGER_BOT_VERIFY_TOKEN=';
        }
        $lines[] = '';
        $block = implode("\n", $lines);

        $content = $this->exists() ? rtrim((string) file_get_contents($this->path)) : '';
        if ($content !== '') {
            $content .= "\n\n";
        }
        $content .= $block;
        file_put_contents($this->path, $content);
    }

    /**
     * Append keys that are not already defined (line missing entirely).
     *
     * @param  array<string, string>  $pairs
     */
    public function appendMissing(array $pairs): void
    {
        $content = $this->exists() ? (string) file_get_contents($this->path) : '';

        foreach ($pairs as $key => $value) {
            if ($content === '' || ! preg_match('/^'.preg_quote((string) $key, '/').'=/m', $content)) {
                $this->put((string) $key, (string) $value);
                $content = $this->exists() ? (string) file_get_contents($this->path) : '';
            }
        }
    }

    protected function unquote(string $raw): string
    {
        if (str_starts_with($raw, '"') && str_ends_with($raw, '"') && strlen($raw) >= 2) {
            return stripcslashes(substr($raw, 1, -1));
        }

        return $raw;
    }

    protected function escapeValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[\w@.\-+:\/]+$/', $value)) {
            return $value;
        }

        return '"'.addcslashes($value, "\\\"\n\r\t\v\f").'"';
    }
}
