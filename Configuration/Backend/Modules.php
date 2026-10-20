<?php
return ['soca_status' => [
    'parent' => 'system', 'access' => 'admin', 'path' => '/module/system/soca',
    'iconIdentifier' => 'actions-lock', 'labels' => 'LLL:EXT:soca/Resources/Private/Language/locallang.xlf',
    'routes' => ['_default' => ['target' => \RESOAuth\SocaTypo3\Controller\AdminController::class . '::handleRequest']],
]];
