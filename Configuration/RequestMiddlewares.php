<?php
return ['frontend' => ['resoauth/soca' => [
    'target' => \RESOAuth\SocaTypo3\Middleware\Soca::class,
    'after' => ['typo3/cms-frontend/authentication', 'typo3/cms-frontend/backend-user-authentication', 'typo3/cms-frontend/site'],
    'before' => ['typo3/cms-frontend/page-resolver'],
]]];
