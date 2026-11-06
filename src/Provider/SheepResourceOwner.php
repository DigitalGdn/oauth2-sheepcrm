<?php

namespace DigitalGdn\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Tool\ArrayAccessorTrait;

class SheepResourceOwner implements ResourceOwnerInterface
{
    use ArrayAccessorTrait;

    /**
     * Raw response
     *
     * @var array
     */
    protected $response;

    /**
     * Creates new resource owner.
     *
     * @param array $response
     */
    public function __construct(array $response = [])
    {
        $this->response = $response;
    }

    /**
     * Get resource owner id
     *
     * SheepCRM's userinfo returns the user's URI path as "id". "user_id" is
     * kept as a fallback for older responses.
     *
     * @return string|null
     */
    public function getId()
    {
        return $this->getValueByKey($this->response, "id", $this->getValueByKey($this->response, "user_id"));
    }

    /**
     * Get resource owner username
     *
     * @return string|null
     */
    public function getUsername()
    {
        return $this->getValueByKey($this->response, "app_username");
    }

    /**
     * Get resource owner name
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->getValueByKey($this->response, "name");
    }

    /**
     * Get resource owner first name
     *
     * @return string|null
     */
    public function getFirstName()
    {
        return $this->getValueByKey($this->response, "first_name");
    }

    /**
     * Get resource owner last name
     *
     * @return string|null
     */
    public function getLastName()
    {
        return $this->getValueByKey($this->response, "last_name");
    }

    /**
     * Get resource owner email
     *
     * @return string|null
     */
    public function getEmail()
    {
        return $this->getValueByKey($this->response, "email");
    }

    /**
     * Whether the resource owner is an active member
     *
     * @return bool
     */
    public function getActiveMember()
    {
        return (bool) $this->getValueByKey($this->response, "active_member", false);
    }

    /**
     * Return all of the owner details available as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->response;
    }
}
