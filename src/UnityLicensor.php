<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use DateInterval;
use DateTimeImmutable;
use Exception;

class UnityLicensor {

    private const UNITY_INIT_LOGIN = 'https://license.unity3d.com/genesis/oauth/logout_callback';

    private const UNITY_INIT_ACTIVATION = 'https://license.unity3d.com/manual';

    private const UNITY_LICENSE = 'https://license.unity3d.com';

    private const UNITY_UPLOAD_ACTIVATION = 'https://license.unity3d.com/genesis/activation/create-transaction';

    private const UNITY_UPDATE_ACTIVATION = 'https://license.unity3d.com/genesis/activation/update-transaction';

    private const UNITY_DOWNLOAD_ACTIVATION = 'https://license.unity3d.com/genesis/activation/download-license';

    private const UNITY_NEW_SERIAL = 'https://license.unity3d.com/manual/serial/new';

    private const UNITY_FINALIZE = 'https://license.unity3d.com/manual/finalize';

    private const UNITY_LOGIN = 'https://id.unity.com';

    private const UNITY_ACCOUNT = 'https://id.unity.com/en/account/edit';

    public const ENV_UNITY_LICENSE_EMAIL = 'UNITY_CREDENTIALS_USR';

    public const ENV_UNITY_LICENSE_PASSWORD = 'UNITY_CREDENTIALS_PSW';

    public static function hasCredentials(): bool {
        return getenv(self::ENV_UNITY_LICENSE_EMAIL) and getenv(self::ENV_UNITY_LICENSE_PASSWORD);
    }

    private string $userMail;

    private string $userPassword;

    private HttpBrowser $browser;

    private CookieJar $cookies;

    private HttpClientInterface $client;

    private string $activationCookie = '';

    private string $alfFile = '';

    private string $ulfFile = '';

    public function __construct(?string $userMail = null, ?string $userPassword = null) {
        $this->browser = new HttpBrowser();
        $this->cookies = $this->browser->getCookieJar();
        $this->client = HttpClient::create();

        $this->userMail = $userMail ?? (string) getenv(self::ENV_UNITY_LICENSE_EMAIL);
        if ($this->userMail === '') {
            throw new Exception(self::class . ' requires the environment variable "' . UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '" to be set.');
        }

        $this->userPassword = $userPassword ?? (string) getenv(self::ENV_UNITY_LICENSE_PASSWORD);
        if ($this->userPassword === '') {
            throw new Exception(self::class . ' requires the environment variable "' . UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '" to be set.');
        }
    }

    public function sign(string $alfFile): string {
        assert(is_readable($alfFile));

        $this->alfFile = $alfFile;

        $this->login();

        $this->uploadActivation();

        $this->updateActivation();

        $this->downloadActivation();

        return $this->ulfFile;
    }

    const UNITY_EMAIL = 'no-reply@unity3d.com';

    const UNITY_2FA_PATTERN = '/\b(\d{6})\b/';

    private function login(): void {
        $this->browser->request('GET', self::UNITY_INIT_ACTIVATION);

        // $this->log();

        $url = self::UNITY_INIT_LOGIN . '?' . http_build_query([
            'lastPage' => '/manual'
        ]);

        $crawler = $this->browser->request('GET', $url);

        // $this->log();

        $startTime = new DateTimeImmutable();

        $form = $crawler->selectButton('commit')->form();
        $form->disableValidation();
        $crawler = $this->browser->submit($form, [
            'conversations_create_session_form[email]' => $this->userMail,
            'conversations_create_session_form[password]' => $this->userPassword,
            'conversations_create_session_form[remember_me]' => true
        ]);

        // $this->log();

        $input = $crawler->filterXPath('.//input[@name="conversations_email_tfa_required_form[code]"]');

        if ($input->count() > 0) {
            if (MailboxAccess::hasCredentials()) {
                $code = null;

                $mailbox = new MailboxAccess();
                for ($i = 0; $i < 30; $i ++) {
                    if ($code = $mailbox->retrieveLatestBy(self::UNITY_EMAIL, $startTime, new DateInterval('PT10M'), self::UNITY_2FA_PATTERN)) {
                        break;
                    }

                    sleep($i);
                }

                if ($code) {
                    $form = $input->form();
                    $form->disableValidation();
                    $crawler = $this->browser->submit($form, [
                        'conversations_email_tfa_required_form[code]' => $code
                    ]);

                    // $this->log();
                } else {
                    trigger_error(sprintf('Unity sent a 2FA code to "%s", but we did not find it there using the environment variables "%s" and "%s".', $this->userMail, MailboxAccess::ENV_EMAIL_PSW, MailboxAccess::ENV_EMAIL_PSW), E_USER_WARNING);
                }
            } else {
                trigger_error(sprintf('Unity sent a 2FA code to "%s", but mail access has not been granted via the environment variables "%s" and "%s".', $this->userMail, MailboxAccess::ENV_EMAIL_PSW, MailboxAccess::ENV_EMAIL_PSW), E_USER_WARNING);
                $this->log();
            }
        }

        $redirect = $crawler->filterXPath('.//a')
            ->first()
            ->link();

        $crawler = $this->browser->click($redirect);

        // $this->log();

        $this->activationCookie = $this->getUploadCookies();

        if ($crawler->getUri() !== self::UNITY_INIT_ACTIVATION) {
            trigger_error(sprintf('Failed to login using email "%s" (ended up in "%s" with cookie "%s")', $this->userMail, $crawler->getUri(), $this->activationCookie), E_USER_WARNING);
            $this->log();
        }
    }

    private function getUploadCookies(): string {
        $cookie = '';
        foreach ($this->cookies->allValues(self::UNITY_UPLOAD_ACTIVATION) as $key => $value) {
            $cookie .= "$key=$value; ";
        }
        return $cookie;
    }

    private function uploadActivation(): void {
        $headers = [
            'Content-Type' => 'text/xml',
            'Referer' => self::UNITY_UPLOAD_ACTIVATION,
            'Origin' => self::UNITY_LICENSE,
            'Cookie' => $this->activationCookie
        ];

        $body = file_get_contents($this->alfFile);

        $response = $this->client->request('POST', self::UNITY_UPLOAD_ACTIVATION, [
            'headers' => $headers,
            'body' => $body
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception(sprintf('Failed to call "%s"', self::UNITY_UPLOAD_ACTIVATION));
        }

        $newCookies = $response->getHeaders(false)['set-cookie'] ?? [];

        foreach ($newCookies as $cookieLine) {
            $match = [];
            if (preg_match('/^([^=]+=[^;]+)/', $cookieLine, $match)) {
                $this->activationCookie .= $match[1] . '; ';
            }
        }
    }

    private function updateActivation(): void {
        $headers = [
            'Content-Type' => 'application/json',
            'Referer' => self::UNITY_NEW_SERIAL,
            'Origin' => self::UNITY_LICENSE,
            'Cookie' => $this->activationCookie
        ];

        $body = '{"transaction":{"serial":{"type":"personal"}}}';

        $response = $this->client->request('PUT', self::UNITY_UPDATE_ACTIVATION, [
            'headers' => $headers,
            'body' => $body
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception(sprintf('Failed to call "%s"', self::UNITY_UPDATE_ACTIVATION));
        }
    }

    private function downloadActivation(): void {
        $headers = [
            'Content-Type' => 'application/json',
            'Referer' => self::UNITY_FINALIZE,
            'Origin' => self::UNITY_LICENSE,
            'Cookie' => $this->activationCookie
        ];

        $body = '{}';

        $response = $this->client->request('POST', self::UNITY_DOWNLOAD_ACTIVATION, [
            'headers' => $headers,
            'body' => $body
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception(sprintf('Failed to call "%s"', self::UNITY_DOWNLOAD_ACTIVATION));
        }

        $json = $response->getContent();
        $json = json_decode($json, true);

        $this->ulfFile = temp_dir(__CLASS__) . DIRECTORY_SEPARATOR . $json['name'];
        file_put_contents($this->ulfFile, $json['xml']);
    }

    private function log(): void {
        $crawler = $this->browser->getCrawler();
        $response = $this->browser->getResponse();
        $url = $crawler->getUri();

        echo PHP_EOL;
        echo 'URL: ' . $url . PHP_EOL;
        echo 'Response Code: ' . $response->getStatusCode() . PHP_EOL;
        echo '-- Cookies --' . PHP_EOL;
        foreach ($this->cookies->allValues($url) as $key => $value) {
            echo $key . ': ' . $value . PHP_EOL;
        }
        echo '-- Response Headers --' . PHP_EOL;
        foreach ($response->getHeaders() as $key => $value) {
            echo $key . ': ' . implode(', ', $value) . PHP_EOL;
        }
        echo '-- Response Body --' . PHP_EOL;
        echo $response->getContent() . PHP_EOL . PHP_EOL;
    }
}

