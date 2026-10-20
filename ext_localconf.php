<?php
defined('TYPO3') or die();
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService('soca', 'auth', \RESOAuth\SocaTypo3\Authentication\SocaAuthenticationService::class, [
    'title' => 'SOCA verified frontend identity', 'description' => 'Authenticates only a verified, request-local OIDC callback.',
    'subtype' => 'getUserFE,authUserFE', 'available' => true, 'priority' => 90, 'quality' => 90,
    'className' => \RESOAuth\SocaTypo3\Authentication\SocaAuthenticationService::class,
]);
