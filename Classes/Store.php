<?php
declare(strict_types=1);
namespace RESOAuth\SocaTypo3;

use RESOAuth\SocaHost\Host;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class Store implements Host
{
    public function __construct(public Connection $db, private Registry $registry, private ?FrontendUserAuthentication $frontend = null) {}
    public function get(string $key): mixed { return $this->frontend?->getSessionData($key); }
    public function set(string $key, mixed $value): void
    {
        if ($this->frontend === null) { throw new \RuntimeException('A native frontend session is required.'); }
        $this->frontend->setAndSaveSessionData($key, $value);
    }
    public function remove(string $key): void { $this->set($key, null); }
    public function putTicket(string $stateHash, string $browserHash, string $revision, int $expires): void
    {
        $this->db->executeStatement('DELETE FROM tx_soca_ticket WHERE expires < ?', [time()]);
        $this->db->insert('tx_soca_ticket', ['state_hash' => $stateHash, 'browser_hash' => $browserHash, 'revision' => $revision, 'expires' => $expires]);
    }
    public function consumeTicket(string $stateHash, string $browserHash, string $revision, int $now): bool
    {
        return $this->db->executeStatement('DELETE FROM tx_soca_ticket WHERE state_hash = ? AND browser_hash = ? AND revision = ? AND expires > ?', [$stateHash, $browserHash, $revision, $now]) === 1;
    }
    public function purgeTickets(): void { $this->db->executeStatement('DELETE FROM tx_soca_ticket'); }
    public function evidence(): array { return $this->registry->get('soca', 'evidence', []); }
    public function saveEvidence(array $evidence): void { $this->registry->set('soca', 'evidence', $evidence); }
    public function audit(string $event, string $reference): void { GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class)->notice('SOCA ' . $event, ['reference' => $reference]); }
    public function linkedUid(string $hash): ?string
    {
        $uid = $this->db->select(['user_id'], 'tx_soca_link', ['identity_hash' => $hash])->fetchOne();
        return $uid === false ? null : (string)$uid;
    }
    public function link(string $hash, string $uid): void
    {
        $this->db->insert('tx_soca_link', ['identity_hash' => $hash, 'user_id' => (int)$uid, 'created_at' => time()]);
        $this->audit('identity_linked', $hash);
    }
    public function unlink(string $hash): void
    {
        $this->db->delete('tx_soca_link', ['identity_hash' => $hash]); $this->purgeTickets(); $this->audit('identity_unlinked', $hash);
    }
}
