<?php
declare(strict_types=1);

/**
 * Minimal mailer abstraction.
 *
 * Modes:
 *   - 'log'  (default): writes the outgoing message to a log file outside the
 *     web root, for local development. The message body is stored verbatim so
 *     developers can click reset links etc.; the log must never be web-served.
 *   - 'mail': uses PHP's mail().
 *   - 'smtp': reserved for a future SMTP implementation; throws until added.
 *
 * The interface (send()) is the single seam, so SMTP can be added later without
 * changing callers.
 */
final class Mailer
{
    public function __construct(
        private array $config,
        private string $logPath
    ) {
    }

    public function send(string $to, string $subject, string $htmlBody): void
    {
        $fromAddress = $this->config['from_address'] ?? 'no-reply@example.com';
        $fromName = $this->config['from_name'] ?? 'MusicPromoV2 CMS';
        $mode = $this->config['mode'] ?? 'log';

        if ($mode === 'log') {
            $this->logMail($to, $subject, $htmlBody, $fromAddress, $fromName);
            return;
        }

        if ($mode === 'mail') {
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->encodeFrom($fromName, $fromAddress),
            ];
            @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
            return;
        }

        if ($mode === 'smtp') {
            // SMTP transport is intentionally not implemented in the base scaffold.
            throw new RuntimeException('SMTP mail mode is not configured.');
        }

        throw new RuntimeException('Unknown mail mode: ' . $mode);
    }

    private function logMail(string $to, string $subject, string $body, string $fromAddress, string $fromName): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $entry = sprintf(
            "==== %s ====\nTo: %s\nFrom: %s <%s>\nSubject: %s\n\n%s\n\n",
            date('c'),
            $to,
            $fromName,
            $fromAddress,
            $subject,
            $body
        );

        @file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX);
    }

    private function encodeFrom(string $name, string $address): string
    {
        return $name !== '' ? (mb_encode_mimeheader($name) . ' <' . $address . '>') : $address;
    }
}
