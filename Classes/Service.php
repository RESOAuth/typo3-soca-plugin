<?php
declare(strict_types=1);
namespace RESOAuth\SocaTypo3;

use RESOAuth\Soca\Client;
use RESOAuth\SocaHost\Configuration;
use RESOAuth\SocaHost\Flow;
use RESOAuth\SocaHost\Identity;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use RuntimeException;

final class Service
{
    public function __construct(private ConnectionPool $pool, private Registry $registry, private PasswordHashFactory $hashFactory) {}
    public function config(): array
    {
        return Configuration::load(['client_name' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ?? 'TYPO3'], [
            'redirect_uri' => '/soca/callback', 'metadata_uri' => '/soca/metadata', 'jwks_uri' => '/soca/jwks',
        ]);
    }
    public function client(): Client { return new Client(Configuration::protocol($this->config())); }
    public function store(?FrontendUserAuthentication $frontend = null): Store { return new Store($this->pool->getConnectionForTable('tx_soca_link'), $this->registry, $frontend); }
    public function flow(?FrontendUserAuthentication $frontend = null): Flow { $c = $this->config(); return new Flow(new Client(Configuration::protocol($c)), $this->store($frontend), $c); }
    public function operator(): string
    {
        $be = $GLOBALS['BE_USER'] ?? null;
        return $be instanceof \TYPO3\CMS\Core\Authentication\BackendUserAuthentication && $be->isAdmin() ? (string)($be->user['uid'] ?? '') : '';
    }
    public function eligible(string $uid): array
    {
        $config = $this->config();
        if (!ctype_digit($uid)) { throw new RuntimeException('Invalid frontend account identifier.'); }
        $row = $this->pool->getConnectionForTable('fe_users')->select(['*'], 'fe_users', ['uid' => (int)$uid])->fetchAssociative();
        if (!$row || $row['disable'] || $row['deleted'] || (int)$row['pid'] !== (int)($config['storage_pid'] ?? -1) || (int)$row['starttime'] > time() || ((int)$row['endtime'] !== 0 && (int)$row['endtime'] <= time()) || !in_array((string)$config['group_id'], explode(',', (string)$row['usergroup']), true)) {
            throw new RuntimeException('The linked frontend account is unavailable or ineligible.');
        }
        return $row;
    }
    public function account(array $claims): array
    {
        $config = $this->config(); $store = $this->store();
        $hash = Identity::hash($config['issuer'], $claims['sub']);
        $uid = $store->linkedUid($hash);
        if ($uid !== null) { return $this->eligible($uid); }
        if (!$config['provision']) { throw new RuntimeException('No approved local account link exists.'); }
        if (!ctype_digit((string)$config['group_id']) || (int)$config['group_id'] < 1 || !is_int($config['storage_pid'] ?? null) || $config['storage_pid'] < 1) { throw new RuntimeException('Set a least-privileged frontend group and storage_pid.'); }
        if ($this->pool->getConnectionForTable('fe_users') !== $store->db || $this->pool->getConnectionForTable('tx_soca_ticket') !== $store->db) { throw new RuntimeException('SOCA and frontend user tables must share one database connection.'); }
        $group = $store->db->select(['uid'], 'fe_groups', ['uid' => (int)$config['group_id'], 'deleted' => 0, 'hidden' => 0])->fetchOne();
        if ($group === false) { throw new RuntimeException('The configured frontend group is unavailable.'); }
        $profile = Identity::profile($claims); $username = Identity::generatedUid($hash);
        $store->db->beginTransaction();
        try {
            if ($store->db->select(['uid'], 'fe_users', ['username' => $username])->fetchOne() !== false) { throw new RuntimeException('Account collision needs administrator recovery.'); }
            $store->db->insert('fe_users', [
                'username' => $username, 'password' => $this->hashFactory->getDefaultHashInstance('FE')->getHashedPassword(base64_encode(random_bytes(64))),
                'pid' => $config['storage_pid'], 'usergroup' => (string)$config['group_id'], 'disable' => 0, 'deleted' => 0,
                'crdate' => time(), 'tstamp' => time(), 'name' => $profile['name'] ?? '', 'first_name' => $profile['given_name'] ?? '', 'last_name' => $profile['family_name'] ?? '', 'email' => $profile['email'] ?? '',
            ]);
            $uid = (string)$store->db->lastInsertId('fe_users');
            $store->link($hash, $uid); $store->db->commit();
            $store->audit('account_provisioned', $hash);
            return $this->eligible($uid);
        } catch (\Throwable $e) { $store->db->rollBack(); throw $e; }
    }
}
