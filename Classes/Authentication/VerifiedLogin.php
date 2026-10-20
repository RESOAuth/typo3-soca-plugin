<?php
declare(strict_types=1);
namespace RESOAuth\SocaTypo3\Authentication;

/** Request-local hand-off, written only after OIDC, admission and account validation. */
final class VerifiedLogin { public static ?array $record = null; }
