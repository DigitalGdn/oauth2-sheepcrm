<?php

namespace DigitalGdn\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use Psr\Http\Message\ResponseInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;

class Sheep extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /**
     * Get authorization url to begin OAuth flow
     *
     * @return string
     */
    public function getBaseAuthorizationUrl()
    {
        return 'https://auth.sheepcrm.com/o/authorize/';
    }

    /**
     * Get access token url to retrieve token
     *
     * @param array $params
     *
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return 'https://auth.sheepcrm.com/o/token/';
    }

    /**
     * Get provider url to fetch user details
     *
     * @param AccessToken $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return 'https://iam.sheepcrm.com/userinfo/';
    }

    /**
     * Get the default scopes used by this provider.
     *
     * @return array
     */
    protected function getDefaultScopes()
    {
        return ['read', 'write', 'read_membership', 'introspection'];
    }

    /**
     * Returns the string that should be used to separate scopes when building
     * the URL for requesting an access token.
     *
     * @return string Scope separator, defaults to ','
     */
    protected function getScopeSeparator()
    {
        return ' ';
    }

    /**
     * Check a provider response for errors.
     *
     * @param  ResponseInterface $response
     * @param  array|string $data
     *
     * @throws IdentityProviderException
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (isset($data['error'])) {
            $statusCode = $response->getStatusCode();
            $error = $data['error'];
            $errorDescription = $data['error_description'];
            $errorLink = (isset($data['error_uri']) ? $data['error_uri'] : false);
            throw new IdentityProviderException(
                $statusCode . ' - ' . $errorDescription . ': ' . $error . ($errorLink ? ' (see: ' . $errorLink . ')' : ''),
                $response->getStatusCode(),
                $response
            );
        }
    }

    /**
     * Generate a user object from a successful user details request.
     *
     * @param array $response
     * @param AccessToken $token
     *
     * @return League\OAuth2\Client\Provider\ResourceOwnerInterface
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new SheepResourceOwner($response);
    }

    /**
     * Returns a prepared request for requesting an access token.
     *
     * @param array $params
     *
     * @return Psr\Http\Message\RequestInterface
     */
    protected function getAccessTokenRequest(array $params)
    {
        $request = parent::getAccessTokenRequest($params);
        $uri = $request->getUri()
            ->withUserInfo($this->clientId, $this->clientSecret);
        return $request->withUri($uri);
    }
}
