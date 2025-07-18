# sheepCRM Provider for OAuth 2.0 Client

This package provides sheepCRM OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

```
composer require DigitalGdn/oauth2-sheepcrm
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
    // Options are optional, defaults to 'profile' only
    $options = ['scope' => 'profile postal_code payments:widget payments:shipping_address payments:billing_address'];
    // Get authorization URL
    $authorizationUrl = $sheepProvider->getAuthorizationUrl($options);

    // Get state and store it to the session
    $_SESSION['oauth2state'] = $sheepProvider->getState();

    // Redirect user to authorization URL
    header('Location: ' . $authorizationUrl);
    exit;
// Check for errors
} elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {
    if (isset($_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
    }
    exit('Invalid state');
} else {
    // Get access token
    try {
        $accessToken = $sheepProvider->getAccessToken(
            'authorization_code',
            [
                'code' => $_GET['code']
            ]
        );
    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        exit($e->getMessage());
    }

    // Get resource owner
    try {
        $resourceOwner = $sheepProvider->getResourceOwner($accessToken);
    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        exit($e->getMessage());
    }

    // Now you can store the results to session etc.
    $_SESSION['accessToken'] = $accessToken;
    $_SESSION['resourceOwner'] = $resourceOwner;

    var_dump(
        $resourceOwner->getId(),
        $resourceOwner->getFirstName(),
        $resourceOwner->getLastName(),
        $resourceOwner->getName(),
        $resourceOwner->getEmail(),
        $resourceOwner->toArray()
    );
}
```

For more information see the PHP League's general usage examples.

## Testing

``` bash
$ ./vendor/bin/phpunit
```

## License

The MIT License (MIT). Please see [License File](https://github.com/DigitalGdn/oauth2-sheepcrm/blob/master/LICENSE) for more information.
