<?php
declare(strict_types=1);
namespace RESOAuth\SocaTypo3\Authentication;

use TYPO3\CMS\Core\Authentication\Event\BeforeRequestTokenProcessedEvent;
use TYPO3\CMS\Core\Security\RequestToken;

final class RequestTokenListener
{
    public function __invoke(BeforeRequestTokenProcessedEvent $event): void
    {
        if ($event->getUser()->loginType === 'FE' && VerifiedLogin::$record !== null) {
            // The browser-bound, consumed OIDC transaction supplies CSRF evidence for this native login only.
            $event->setRequestToken(RequestToken::create('core/user-auth/fe'));
        }
    }
}
