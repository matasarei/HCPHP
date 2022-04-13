<?php

namespace UserBundle\Entity;

use core\Entity;

class User extends Entity
{
    /**
     * @var string|null
     */
    protected $authKey = null;

    /**
     * @var int
     */
    protected $authTime = 0;

    /**
     * @var int
     */
    protected $timeModified;

    /**
     * @var int
     */
    protected $timeCreated;

    /**
     * @var string
     */
    protected $firstName;

    /**
     * @var string|null
     */
    protected $lastName = null;

    /**
     * @var Role
     */
    protected $role;

    /**
     * @var string|null
     */
    protected $password = null;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $suspended = false;

    public function __construct(
        string $email,
        string $firstName,
        Role $role
    ) {
        $this->timeCreated = $this->timeModified = time();
        $this->email = $email;
        $this->firstName = $firstName;
        $this->role = $role;
    }

    public function setTimeModified(int $time): self
    {
        $this->timeModified = $time;

        return $this;
    }

    public function getTimeModified(): int
    {
        return $this->timeModified;
    }
    
    public function setTimeCreated(int $time): self
    {
        $this->timeCreated = $time;

        return $this;
    }

    public function getTimeCreated(): int
    {
        return $this->timeCreated;
    }

    public function getFullName(string $format = '%f %l'): string
    {
        return trim(
            preg_replace(
                ['/%f/', '/%l/', '/\s{2,}/'],
                [$this->firstName, $this->lastName, ' '],
                $format
            )
        );
    }

    public function __toString()
    {
        return $this->getFullName();
    }

    public function setFirstName($firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * @param string|null $lastname
     *
     * @return self
     */
    public function setLastName($lastname): self
    {
        $this->lastName = $lastname;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    public function setRole(Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    /**
     * @param string|null $password
     *
     * @return self
     */
    public function setPassword($password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPassword()
    {
        return $this->password;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }

    public function setSuspended(bool $val): self
    {
        $this->suspended = $val;

        return $this;
    }

    public function isSuspended(): bool
    {
        return $this->suspended;
    }

    /**
     * @param string|null $value
     *
     * @return $this
     */
    public function setAuthKey($value): self
    {
        $this->authKey = $value;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAuthKey()
    {
        return $this->authKey;
    }

    public function setAuthTime(int $time): self
    {
        $this->authTime = $time;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getAuthTime()
    {
        return $this->authTime;
    }
}
