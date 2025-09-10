<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PhpImap\Mailbox;
use DateInterval;
use DateTimeImmutable;
use Exception;

class MailboxAccess {

    public const ENV_EMAIL_USR = 'EMAIL_CREDENTIALS_USR';

    public const ENV_EMAIL_PSW = 'EMAIL_CREDENTIALS_PSW';

    public static function hasCredentials(): bool {
        return getenv(self::ENV_EMAIL_USR) and getenv(self::ENV_EMAIL_PSW);
    }

    private static $imapHosts = [
        'gmail.de' => 'imap.gmail.com',
        'gmail.com' => 'imap.gmail.com',
        'outlook.com' => 'imap-mail.outlook.com',
        'gmx.de' => 'imap.gmx.net',
        'web.de' => 'imap.web.de'
    ];

    private const IMAP_PORT = 993;

    private static function getServer(string $email): string {
        $email = explode('@', $email, 2);
        $domain = $email[1] ?? 'unknown';
        $imapHost = self::$imapHosts[$domain] ?? "imap.$domain";
        return sprintf('{%s:%d/imap/ssl}INBOX', $imapHost, self::IMAP_PORT);
    }

    private Mailbox $mailbox;

    public function __construct(?string $userMail = null, ?string $userPassword = null, ?string $userServer = null) {
        $userMail ??= (string) getenv(self::ENV_EMAIL_USR);
        if ($userMail === '') {
            throw new Exception(self::class . ' requires the environment variable "' . MailboxAccess::ENV_EMAIL_USR . '" to be set.');
        }

        $userPassword ??= (string) getenv(self::ENV_EMAIL_PSW);
        if ($userPassword === '') {
            throw new Exception(self::class . ' requires the environment variable "' . MailboxAccess::ENV_EMAIL_PSW . '" to be set.');
        }

        $userServer ??= self::getServer($userMail);
        if ($userServer === '') {
            throw new Exception(self::class . ' failed to determine the imap path for email address "' . $userMail . '".');
        }

        $this->mailbox = new Mailbox($userServer, $userMail, $userPassword);
    }

    public function retrieveLatestBy(string $from, DateTimeImmutable $since, DateInterval $range, string $pattern): ?string {
        $search = sprintf('FROM "%s" SINCE "%s" BEFORE "%s"', $from, $since->format('d-M-Y'), $since->add(new DateInterval('P2D'))->format('d-M-Y'));

        $mailIds = $this->mailbox->searchMailbox($search);

        $end = $since->add($range);

        foreach (array_reverse($mailIds) as $mailId) {
            $mail = $this->mailbox->getMail($mailId, false);

            $mailDate = new DateTimeImmutable($mail->date);

            if ($mailDate >= $since and $mailDate <= $end) {
                $match = [];
                if (preg_match($pattern, $mail->textPlain, $match)) {
                    return $match[1];
                }
            }
        }

        return null;
    }

    public function waitForLatestBy(string $from, DateTimeImmutable $since, DateInterval $range, string $pattern): ?string {
        $timeout = $since->add($range);

        do {
            sleep(10);

            if ($code = $this->retrieveLatestBy($from, $since, $range, $pattern)) {
                return $code;
            }
        } while (new DateTimeImmutable() < $timeout);

        return null;
    }
}

