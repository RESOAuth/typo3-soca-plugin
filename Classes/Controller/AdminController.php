<?php
declare(strict_types=1);
namespace RESOAuth\SocaTypo3\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RESOAuth\SocaTypo3\Service;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;

#[AsController]
final class AdminController
{
    public function __construct(private Service $service, private ModuleTemplateFactory $templates, private FormProtectionFactory $forms) {}
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->service->operator() === '') { throw new \RuntimeException('Local administrator required.'); }
        $form = $this->forms->createForType('backend');
        $message = '';
        try {
            $body = $request->getParsedBody();
            if ($request->getMethod() === 'POST') {
                if (!$form->validateToken((string)($body['soca_token'] ?? ''), 'soca', 'check')) { throw new \RuntimeException('The form expired. Reload the settings page.'); }
                $this->service->flow()->checkDocuments(); $message = 'Public documents checked successfully. A real candidate test is still required.';
            }
            $status = json_encode($this->service->flow()->status(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $testUrl = $this->service->config()['origin'] . '/soca/test';
        } catch (\Throwable) { $status = 'Configuration or document check failed. Use soca:manage status/check for deployment diagnostics.'; $testUrl = ''; }
        $view = $this->templates->create($request);
        $view->assignMultiple(['status' => $status, 'message' => $message, 'checkToken' => $form->generateToken('soca','check'), 'testToken' => $form->generateToken('soca','test'), 'testUrl' => $testUrl]);
        return $view->renderResponse('Status');
    }
}
