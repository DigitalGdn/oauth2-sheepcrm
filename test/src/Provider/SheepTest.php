<?php

namespace DigitalGdn\OAuth2\Client\Test\Provider;

use DigitalGdn\OAuth2\Client\Provider\Sheep;
use DigitalGdn\OAuth2\Client\Provider\SheepResourceOwner;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;

class SheepTest extends TestCase
{
    /** @var Sheep */
    protected $provider;

    /** @var array */
    protected $history = [];

    protected function setUp(): void
    {
        $this->provider = new Sheep([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'https://example.com/auth/',
        ]);
    }

    /**
     * Queue canned responses and record the requests the provider sends.
     */
    private function mockResponses(Response ...$responses): void
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $this->provider->setHttpClient(new Client(['handler' => $stack]));
    }

    private function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    private function tokenResponse(): Response
    {
        return $this->json(200, [
            'access_token' => 'mock_access_token',
            'refresh_token' => 'mock_refresh_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => 'read read_membership',
        ]);
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertSame('auth.sheepcrm.com', $uri['host']);
        $this->assertSame('/o/authorize/', $uri['path']);
        $this->assertSame('mock_client_id', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('https://example.com/auth/', $query['redirect_uri']);
        $this->assertSame('auto', $query['approval_prompt']);
        $this->assertNotEmpty($query['state']);
        $this->assertSame($this->provider->getState(), $query['state']);
    }

    public function testDefaultScopesAreReadOnly(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('read read_membership', $query['scope']);
    }

    public function testCustomScopesAreSpaceSeparated(): void
    {
        $url = $this->provider->getAuthorizationUrl(['scope' => ['read', 'write']]);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('read write', $query['scope']);
    }

    public function testGetBaseAccessTokenUrl(): void
    {
        $this->assertSame('https://auth.sheepcrm.com/o/token/', $this->provider->getBaseAccessTokenUrl([]));
    }

    public function testGetResourceOwnerDetailsUrl(): void
    {
        $token = new AccessToken(['access_token' => 'mock_access_token']);

        $this->assertSame('https://iam.sheepcrm.com/userinfo/', $this->provider->getResourceOwnerDetailsUrl($token));
    }

    public function testGetAccessToken(): void
    {
        $this->mockResponses($this->tokenResponse());

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertSame('mock_access_token', $token->getToken());
        $this->assertSame('mock_refresh_token', $token->getRefreshToken());
        $this->assertGreaterThanOrEqual(time(), $token->getExpires());
        $this->assertLessThanOrEqual(time() + 3600, $token->getExpires());
    }

    public function testClientCredentialsAreSentOnceInTheBody(): void
    {
        $this->mockResponses($this->tokenResponse());

        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $request = $this->history[0]['request'];
        parse_str((string) $request->getBody(), $body);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('', $request->getUri()->getUserInfo());
        $this->assertFalse($request->hasHeader('Authorization'));
        $this->assertSame('mock_client_id', $body['client_id']);
        $this->assertSame('mock_secret', $body['client_secret']);
        $this->assertSame('authorization_code', $body['grant_type']);
        $this->assertSame('mock_authorization_code', $body['code']);
    }

    public function testOAuthErrorBodyThrows(): void
    {
        $this->mockResponses($this->json(400, [
            'error' => 'invalid_grant',
            'error_description' => 'Invalid code',
        ]));

        $this->expectException(IdentityProviderException::class);
        $this->expectExceptionMessage('400 - Invalid code: invalid_grant');

        $this->provider->getAccessToken('authorization_code', ['code' => 'bad_code']);
    }

    public function testErrorWithoutDescriptionThrowsCleanly(): void
    {
        $this->mockResponses($this->json(400, ['error' => 'invalid_client']));

        $this->expectException(IdentityProviderException::class);
        $this->expectExceptionMessage('invalid_client');

        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    public function testHttpErrorWithoutErrorKeyThrows(): void
    {
        $this->mockResponses($this->json(401, ['detail' => 'Authentication credentials were not provided.']));

        $token = new AccessToken(['access_token' => 'expired_token']);

        try {
            $this->provider->getResourceOwner($token);
            $this->fail('Expected IdentityProviderException');
        } catch (IdentityProviderException $e) {
            $this->assertSame(401, $e->getCode());
            $this->assertStringContainsString('Authentication credentials were not provided.', $e->getMessage());
        }
    }

    public function testServerErrorWithHtmlBodyThrows(): void
    {
        // The League client rejects a non-JSON body before checkResponse()
        // runs, so callers must catch UnexpectedValueException too.
        $this->mockResponses(new Response(500, ['Content-Type' => 'text/html'], '<h1>Server Error</h1>'));

        $this->expectException(\UnexpectedValueException::class);

        $this->provider->getResourceOwner(new AccessToken(['access_token' => 'mock_access_token']));
    }

    public function testGetResourceOwner(): void
    {
        $this->mockResponses($this->json(200, [
            'id' => '/flock/user/abc123/',
            'app_username' => 'jsmith',
            'name' => 'Jo Smith',
            'first_name' => 'Jo',
            'last_name' => 'Smith',
            'email' => 'jo@example.com',
            'active_member' => true,
        ]));

        $token = new AccessToken(['access_token' => 'mock_access_token']);
        $owner = $this->provider->getResourceOwner($token);

        $request = $this->history[0]['request'];
        $this->assertSame('Bearer mock_access_token', $request->getHeaderLine('Authorization'));

        $this->assertInstanceOf(SheepResourceOwner::class, $owner);
        $this->assertSame('/flock/user/abc123/', $owner->getId());
        $this->assertSame('jsmith', $owner->getUsername());
        $this->assertSame('Jo Smith', $owner->getName());
        $this->assertSame('Jo', $owner->getFirstName());
        $this->assertSame('Smith', $owner->getLastName());
        $this->assertSame('jo@example.com', $owner->getEmail());
        $this->assertTrue($owner->getActiveMember());
        $this->assertSame('jsmith', $owner->toArray()['app_username']);
    }

    public function testResourceOwnerDefaults(): void
    {
        $owner = new SheepResourceOwner(['user_id' => 'legacy_id']);

        $this->assertSame('legacy_id', $owner->getId());
        $this->assertNull($owner->getEmail());
        $this->assertFalse($owner->getActiveMember());
    }
}
