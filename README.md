# sheepCRM Provider for OAuth 2.0 Client

This package provides sheepCRM OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

Requires PHP 8.1 or later.

```
composer require digitalgdn/oauth2-sheepcrm
```

## Usage

```php
$sheepProvider = new \DigitalGdn\OAuth2\Client\Provider\Sheep([
    'clientId'     => 'yourId',          // The client ID assigned to you by sheepCRM
    'clientSecret' => 'yourSecret',      // The client password assigned to you by sheepCRM
    'redirectUri'  => 'yourRedirectUri'  // The return URL you specified for your app on sheepCRM
]);

// Get authorization code
if (!isset($_GET['code'])) {
    // Options are optional, defaults shown below.
    // Add 'write' or 'introspection' only if your app needs them.
    $options = ['scope' => ['read', 'read_membership']];
    // Get authorization URL
    $authorizationUrl = $sheepProvider->getAuthorizationUrl($options);

    // Get state and store it to the session
    $_SESSION['oauth2state'] = $sheepProvider->getState();

    // Redirect user to authorization URL
    header('Location: ' . $authorizationUrl);
    exit;
// Check for errors
} elseif (empty($_GET['state']) || empty($_SESSION['oauth2state']) || !hash_equals($_SESSION['oauth2state'], $_GET['state'])) {
    if (isset($_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
    }
    exit('Invalid state');
} else {
    // The state is single use
    unset($_SESSION['oauth2state']);

    // Get access token
    try {
        $accessToken = $sheepProvider->getAccessToken(
            'authorization_code',
            [
                'code' => $_GET['code']
            ]
        );
    } catch (\Exception $e) {
        exit(htmlspecialchars($e->getMessage()));
    }

    // Get resource owner
    try {
        $resourceOwner = $sheepProvider->getResourceOwner($accessToken);
    } catch (\Exception $e) {
        exit(htmlspecialchars($e->getMessage()));
    }

    // Now you can store the results to session etc.
    $_SESSION['accessToken'] = $accessToken;
    $_SESSION['resourceOwner'] = $resourceOwner;

    var_dump(
        $resourceOwner->getId(),
        $resourceOwner->getUsername(),
        $resourceOwner->getFirstName(),
        $resourceOwner->getLastName(),
        $resourceOwner->getName(),
        $resourceOwner->getEmail(),
        $resourceOwner->getActiveMember(),
        $resourceOwner->toArray()
    );
}
```

For more information see the PHP League's general usage examples.

### Errors

The provider throws `IdentityProviderException` for any OAuth error body and for any HTTP status of 400 or above. The League client throws `UnexpectedValueException` when an error response isn't JSON, so catch both (or `\Exception`).

### Consent screen

SheepCRM's authorisation server skips its consent screen (`approval_prompt=auto`, the default) only while the user still holds an unexpired token for your client covering the requested scopes. To switch the consent screen off entirely, ask SheepCRM to enable "skip authorization" for your application.

## Testing

```bash
composer install
composer test
```

## License

The MIT License (MIT). Please see [License File](https://github.com/DigitalGdn/oauth2-sheepcrm/blob/main/LICENSE) for more information.
