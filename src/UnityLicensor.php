<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Exception;

class UnityLicensor {

    const UNITY_INIT_LOGIN = 'https://license.unity3d.com/genesis/oauth/logout_callback';

    const UNITY_INIT_ACTIVATION = 'https://license.unity3d.com/manual';

    const UNITY_LICENSE = 'https://license.unity3d.com';

    const UNITY_UPLOAD_ACTIVATION = 'https://license.unity3d.com/genesis/activation/create-transaction';

    const UNITY_UPDATE_ACTIVATION = 'https://license.unity3d.com/genesis/activation/update-transaction';

    const UNITY_DOWNLOAD_ACTIVATION = 'https://license.unity3d.com/genesis/activation/download-license';

    const UNITY_NEW_SERIAL = 'https://license.unity3d.com/manual/serial/new';

    const UNITY_FINALIZE = 'https://license.unity3d.com/manual/finalize';

    const UNITY_LOGIN = 'https://id.unity.com';

    const UNITY_ACCOUNT = 'https://id.unity.com/en/account/edit';

    private static string $userMail = 'info.slothsoft@gmail.com';

    private static string $userPassword = 'CI4life!';

    private HttpBrowser $browser;

    private CookieJar $cookies;

    private HttpClientInterface $client;

    private string $activationCookie = '';

    private string $alfFile = '';

    private string $ulfFile = '';

    public function __construct() {
        $this->browser = new HttpBrowser();
        $this->cookies = $this->browser->getCookieJar();
        $this->client = HttpClient::create();
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

    private function login(): void {
        $this->browser->request('GET', self::UNITY_INIT_ACTIVATION);

        // $this->log();

        $url = self::UNITY_INIT_LOGIN . '?' . http_build_query([
            'lastPage' => '/manual'
        ]);

        $crawler = $this->browser->request('GET', $url);

        // $this->log();

        $form = $crawler->selectButton('commit')->form();
        $form->disableValidation();
        $crawler = $this->browser->submit($form, [
            'conversations_create_session_form[email]' => self::$userMail,
            'conversations_create_session_form[password]' => self::$userPassword,
            'conversations_create_session_form[remember_me]' => true
        ]);

        // $this->log();

        $redirect = $crawler->filterXPath('.//a')
            ->first()
            ->link();

        $crawler = $this->browser->click($redirect);

        // $this->log();

        $this->activationCookie = $this->getUploadCookies();

        if ($crawler->getUri() !== self::UNITY_INIT_ACTIVATION) {
            throw new Exception(sprintf('Failed to login using email "%s"', self::$userMail));
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

