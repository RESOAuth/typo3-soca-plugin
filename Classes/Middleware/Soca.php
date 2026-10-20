<?php
declare(strict_types=1);
namespace RESOAuth\SocaTypo3\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RESOAuth\SocaHost\Configuration;
use RESOAuth\SocaTypo3\Authentication\VerifiedLogin;
use RESOAuth\SocaTypo3\Service;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use RuntimeException;

final class Soca implements MiddlewareInterface
{
    public function __construct(private Service $service, private FormProtectionFactory $forms) {}
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $frontend = $request->getAttribute('frontend.user');
        if (!$frontend instanceof FrontendUserAuthentication) { return $handler->handle($request); }
        $store = $this->service->store($frontend);
        $path = $request->getUri()->getPath();
        $isSocaPath = preg_match('~/soca/(metadata|jwks|login|callback|test)$~D', $path, $match) === 1;
        try {
            $marker = $store->get('soca.auth');
            if (is_array($marker)) {
                $config = $this->service->config();
                $valid = $config['enabled'] && !Configuration::emergencyDisabled() && hash_equals($this->service->flow($frontend)->revision(), (string)$marker['revision']) && time() < $marker['at'] + $config['session_max_age'];
                if ($valid) { $this->service->eligible((string)$marker['uid']); }
                else { $frontend->logoff(); $store->purgeTickets(); }
            }
            if (!$isSocaPath) { return $handler->handle($request); }
            $config = $this->service->config();
            $site = $request->getAttribute('site');
            if ($site === null || $site->getIdentifier() !== $config['site_id']) { throw new RuntimeException('The SOCA endpoint belongs to a different site.'); }
            $endpoint = $match[1];
            $expected = rtrim((string)parse_url($config['origin'], PHP_URL_PATH), '/') . '/soca/' . $endpoint;
            if ($path !== $expected || $request->getUri()->getScheme() !== 'https') { throw new RuntimeException('Use the canonical HTTPS endpoint.'); }
            if (in_array($endpoint, ['metadata','jwks'], true)) {
                return new JsonResponse($endpoint === 'metadata' ? $this->service->client()->metadata() : $this->service->client()->jwks(), 200, ['Cache-Control' => 'public, max-age=300']);
            }
            $flow = $this->service->flow($frontend);
            if ($endpoint === 'test') {
                $body = $request->getParsedBody();
                $protection = $this->forms->createForType('backend');
                if ($request->getMethod() !== 'POST' || $this->service->operator() === '' || !$protection->validateToken((string)($body['soca_token'] ?? ''), 'soca', 'test')) { throw new RuntimeException('A CSRF-protected local administrator test is required.'); }
                return new RedirectResponse($flow->begin(true, $this->service->operator()), 303);
            }
            if ($endpoint === 'login') {
                if (!empty($frontend->user['uid'])) { throw new RuntimeException('A local session must not be replaced.'); }
                return new RedirectResponse($flow->begin(), 303);
            }
            $result = $flow->complete($request->getQueryParams(), $this->service->operator());
            if ($result['test']) { return new JsonResponse(['test_login_succeeded' => true, 'operator_session_preserved' => true], 200, ['Cache-Control' => 'no-store']); }
            if (!empty($frontend->user['uid'])) { throw new RuntimeException('A local session must not be replaced.'); }
            $record = $this->service->account($result['claims']);
            VerifiedLogin::$record = $record;
            try {
                $frontend->checkAuthentication($request->withParsedBody(['logintype' => 'login', 'user' => $record['username'], 'pass' => '']));
                if ((int)($frontend->user['uid'] ?? 0) !== (int)$record['uid']) { throw new RuntimeException('Native frontend authentication failed.'); }
            } finally { VerifiedLogin::$record = null; }
            $store->set('soca.auth', ['uid' => (string)$record['uid'], 'at' => time(), 'revision' => $flow->revision()]);
            return new RedirectResponse($config['origin'] . '/', 303);
        } catch (\Throwable) {
            if (!$isSocaPath) { if (is_array($store->get('soca.auth'))) { $frontend->logoff(); } return $handler->handle($request); }
            $reference = bin2hex(random_bytes(8)); $store->audit('login_rejected', $reference);
            return new JsonResponse(['error' => 'SOCA could not complete this action. Check deployment configuration, site, candidate evidence, admission and account links.', 'reference' => $reference], 403, ['Cache-Control' => 'no-store']);
        }
    }
}
