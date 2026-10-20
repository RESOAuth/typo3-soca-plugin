<?php
declare(strict_types=1);
namespace RESOAuth\SocaTypo3\Authentication;

final class SocaAuthenticationService extends \TYPO3\CMS\Core\Authentication\AbstractAuthenticationService
{
    public function getUser(): array|false
    {
        return ($this->authInfo['loginType'] ?? '') === 'FE' ? (VerifiedLogin::$record ?? false) : false;
    }
    public function authUser(array $user): int
    {
        if (($this->authInfo['loginType'] ?? '') !== 'FE' || VerifiedLogin::$record === null) { return 100; }
        return (int)$user['uid'] === (int)VerifiedLogin::$record['uid'] ? 200 : 0;
    }
}
