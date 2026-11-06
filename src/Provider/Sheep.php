<?php

namespace DigitalGdn\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

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
     * @param \League\OAuth2\Client\Token\AccessToken $token
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
     * Only what a login needs: basic profile and membership status. Pass
     * 'scope' to getAuthorizationUrl() to ask for more (e.g. 'write').
     *
     * @return array
     */
    protected function getDefaultScopes()
    {
        return ['read', 'read_membership'];
    }

    /**
     * Returns the string that should be used to separate scopes when building
     * the URL for requesting an access token.
     *
     * @return string
     */
    protected function getScopeSeparator()
    {
        return ' ';
    }

    /**
     * Check a provider response for errors.
     *
     * Throws on an OAuth error body or on any HTTP status of 400 or above,
     * so an error body is never mistaken for a token or a user profile.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param array|string $data
     *
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 400 && !(is_array($data) && isset($data['error']))) {
            return;
        }

        $message = (string) $statusCode;

        if (is_array($data)) {
            $error = isset($data['error']) ? $data['error'] : null;
            $description = null;
            foreach (['error_description', 'detail', 'message'] as $key) {
                if (isset($data[$key]) && is_scalar($data[$key])) {
                    $description = (string) $data[$key];
                    break;
                }
            }

            if ($description !== null) {
                $message .= ' - ' . $description;
            } elseif ($response->getReasonPhrase() !== '') {
                $message .= ' - ' . $response->getReasonPhrase();
            }
            if (is_scalar($error)) {
                $message .= ': ' . $error;
            }
            if (isset($data['error_uri']) && is_scalar($data['error_uri'])) {
                $message .= ' (see: ' . $data['error_uri'] . ')';
            }
        } elseif ($response->getReasonPhrase() !== '') {
            $message .= ' - ' . $response->getReasonPhrase();
        }

        throw new IdentityProviderException($message, $statusCode, $data);
    }

    /**
     * Generate a user object from a successful user details request.
     *
     * @param array $response
     * @param \League\OAuth2\Client\Token\AccessToken $token
     *
     * @return \League\OAuth2\Client\Provider\ResourceOwnerInterface
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new SheepResourceOwner($response);
    }
}
