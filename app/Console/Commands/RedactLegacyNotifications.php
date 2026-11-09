<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class RedactLegacyNotifications extends Command
{
    protected $signature = 'notifications:redact-legacy {--execute : Apply changes (default is dry-run)}';

    protected $description = 'Redact credential-like patterns from legacy notification rows (dry-run by default)';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $scanned = 0;
        $changed = 0;

        Notification::query()->orderBy('id')->chunkById(200, function ($rows) use (&$scanned, &$changed, $execute): void {
            foreach ($rows as $n) {
                $scanned++;
                $subject = (string) $n->subject;
                $data = (string) $n->data;
                $newSubject = $this->sanitize($subject);
                $newData = $this->sanitize($data);

                if ($newSubject === $subject && $newData === $data) {
                    continue;
                }

                $changed++;
                $this->line("Notification #{$n->id}: would redact");
                if ($execute) {
                    $n->subject = $newSubject;
                    $n->data = $newData;
                    $n->save();
                }
            }
        });

        $mode = $execute ? 'updated' : 'would update (dry-run)';
        $this->info("Scanned {$scanned} notifications, {$mode} {$changed}.");

        if (! $execute && $changed > 0) {
            $this->warn('Re-run with --execute to apply changes.');
        }

        return self::SUCCESS;
    }

    private function sanitize(string $text): string
    {
        $text = preg_replace('/(password|pword|passwd|pwd)\s*[:=]\s*[^\s&<"\']+/i', '$1=[redacted]', $text) ?? $text;
        $text = str_replace(['http://http://', 'https://https://'], ['http://', 'https://'], $text);

        return $text;
    }
}
