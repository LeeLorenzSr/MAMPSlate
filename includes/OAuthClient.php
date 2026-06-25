<?php
declare(strict_types=1);

/**
 * Minimal, dependency-free OAuth 2.0 Authorization Code client.
 *
 * Supports Google and GitHub using PHP's cURL extension (bundled with MAMP).
 * The `state` parameter is the CSRF guard between the redirect to the provider
 * and the callback; callers must store and verify it in the session.
 */
final class OAuthClient
{
    /** Provider endpoint configuration. */
    private const PROVIDERS = [
        'google' => [
            'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token'     => 'https://oauth2.googleapis.com/token',
            'userinfo'  => 'https://www.googleapis.com/oauth2/v3/userinfo',
        ],
        'github' => [
            'authorize' => 'https://github.com/login/oauth/authorize',
            'token'     => 'https://github.com/login/oauth/access_token',
            'userinfo'  => 'https://api.github.com/user',
            'emails'    => 'https://api.github.com/user/emails',
        ],
    ];

    public function __construct(private array $config)
    {
    }

    public function isEnabled(string $provider): bool
    {
        return isset($this->config[$provider]['enabled'])
            && (bool)$this->config[$provider]['enabled']
            && !empty($this->config[$provider]['client_id'])
            && !empty($this->config[$provider]['client_secret']);
    }

    /**
     * Build the provider authorization URL the browser is redirected to.
     */
    public function authorizationUrl(string $provider, string $state): string
    {
        $cfg = $this->providerConfig($provider);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $cfg['redirect_uri'],
            'scope' => $cfg['scope'],
            'state' => $state,
        ]);

        return self::PROVIDERS[$provider]['authorize'] . '?' . $params;
    }

    /**
     * Exchange an authorization code for an access token.
     *
     * @return array{access_token: string}
     */
    public function exchangeCode(string $provider, string $code): array
    {
        $cfg = $this->providerConfig($provider);

        $body = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $cfg['redirect_uri'],
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
        ]);

        $response = $this->post(self::PROVIDERS[$provider]['token'], $body, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('OAuth token exchange failed.');
        }

        return ['access_token' => (string)$data['access_token']];
    }

    /**
     * Fetch a normalized identity from the provider.
     *
     * @return array{provider_user_id: string, email: string, email_verified: bool, display_name: string}
     */
    public function fetchIdentity(string $provider, string $accessToken): array
    {
        if ($provider === 'google') {
            return $this->fetchGoogleIdentity($accessToken);
        }
        if ($provider === 'github') {
            return $this->fetchGithubIdentity($accessToken);
        }

        throw new RuntimeException('Unknown OAuth provider: ' . $provider);
    }

    private function fetchGoogleIdentity(string $accessToken): array
    {
        $data = $this->getJson(self::PROVIDERS['google']['userinfo'], $accessToken);

        if (empty($data['sub'])) {
            throw new RuntimeException('Google did not return a subject identifier.');
        }

        return [
            'provider_user_id' => (string)$data['sub'],
            'email' => isset($data['email']) ? strtolower(trim((string)$data['email'])) : '',
            'email_verified' => isset($data['email_verified']) && ($data['email_verified'] === true || $data['email_verified'] === 'true'),
            'display_name' => trim((string)($data['name'] ?? $data['email'] ?? '')),
        ];
    }

    private function fetchGithubIdentity(string $accessToken): array
    {
        $data = $this->getJson(self::PROVIDERS['github']['userinfo'], $accessToken, true);

        if (empty($data['id'])) {
            throw new RuntimeException('GitHub did not return a user identifier.');
        }

        $email = isset($data['email']) ? strtolower(trim((string)$data['email'])) : '';
        $verified = false;

        // GitHub may return a null email when the address is private; fetch the
        // emails endpoint and pick the primary verified address.
        if ($email === '') {
            $emails = $this->getJson(self::PROVIDERS['github']['emails'], $accessToken, true);
            if (is_array($emails)) {
                foreach ($emails as $candidate) {
                    if (!empty($candidate['primary']) && !empty($candidate['verified'])) {
                        $email = strtolower(trim((string)$candidate['email']));
                        $verified = true;
                        break;
                    }
                }
            }
        } else {
            // The /user endpoint does not report verification; check /user/emails.
            $verified = $this->githubEmailIsVerified($accessToken, $email);
        }

        $displayName = trim((string)($data['name'] ?? $data['login'] ?? ''));

        return [
            'provider_user_id' => (string)$data['id'],
            'email' => $email,
            'email_verified' => $verified,
            'display_name' => $displayName !== '' ? $displayName : trim((string)($data['login'] ?? '')),
        ];
    }

    private function githubEmailIsVerified(string $accessToken, string $email): bool
    {
        $emails = $this->getJson(self::PROVIDERS['github']['emails'], $accessToken, true);
        if (!is_array($emails)) {
            return false;
        }
        foreach ($emails as $candidate) {
            if (strtolower(trim((string)($candidate['email'] ?? ''))) === $email) {
                return !empty($candidate['verified']);
            }
        }
        return false;
    }

    private function providerConfig(string $provider): array
    {
        if (!isset(self::PROVIDERS[$provider])) {
            throw new RuntimeException('Unknown OAuth provider: ' . $provider);
        }
        if (!$this->isEnabled($provider)) {
            throw new RuntimeException('OAuth provider not enabled: ' . $provider);
        }
        return $this->config[$provider];
    }

    private function getJson(string $url, string $accessToken, bool $github = false): array
    {
        $headers = ['Authorization: Bearer ' . $accessToken, 'Accept: application/json'];
        if ($github) {
            $headers[] = 'User-Agent: MusicPromoV2-OAuth';
        }
        $raw = $this->request('GET', $url, $headers);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Unexpected response from OAuth provider.');
        }
        return $data;
    }

    private function post(string $url, string $body, array $headers): string
    {
        return $this->request('POST', $url, $headers, $body);
    }

    private function request(string $method, string $url, array $headers, ?string $body = null): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false || $error !== '') {
            throw new RuntimeException('OAuth HTTP request failed: ' . $error);
        }
        if ($status >= 400) {
            throw new RuntimeException('OAuth provider returned HTTP ' . $status);
        }

        return (string)$response;
    }
}
