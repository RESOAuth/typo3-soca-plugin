<?php
declare(strict_types=1);
if(getenv('SOCA_NATIVE_TESTS')!=='1'){throw new RuntimeException('Disposable demo only.');}
$loader=require '/var/www/typo3/vendor/autoload.php';
\TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run(1,\TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_CLI);
$container=\TYPO3\CMS\Core\Core\Bootstrap::init($loader,false);
use RESOAuth\SocaTypo3\{Service,Store};use RESOAuth\SocaHost\Identity;use RESOAuth\SocaTypo3\Authentication\{VerifiedLogin,SocaAuthenticationService};
$service=$container->get(Service::class);$store=$service->store();$db=$store->db;$count=0;
function nativeCheck(bool $ok,string $name):void{global $count;++$count;if(!$ok){throw new RuntimeException($name);}}
function nativeDeny(callable $fn,string $name):void{try{$fn();}catch(Throwable){nativeCheck(true,$name);return;}nativeCheck(false,$name);}
$configPath=tempnam('/tmp','soca-native-');$original=getenv('SOCA_CONFIG_FILE');$uid=null;$newUid=null;$groupUid=null;$hash=null;
try{
 $db->insert('fe_groups',['pid'=>1,'title'=>'SOCA native fixture '.bin2hex(random_bytes(4)),'hidden'=>0,'deleted'=>0]);$groupUid=(int)$db->lastInsertId('fe_groups');
 $deployment=json_decode(file_get_contents($original),true,32,JSON_THROW_ON_ERROR);$deployment['group_id']=(string)$groupUid;$deployment['storage_pid']=1;file_put_contents($configPath,json_encode($deployment,JSON_THROW_ON_ERROR));putenv('SOCA_CONFIG_FILE='.$configPath);
 $sub='native-'.bin2hex(random_bytes(8));$hash=Identity::hash($service->config()['issuer'],$sub);$username='soca_test_'.bin2hex(random_bytes(6));
 $db->insert('fe_users',['pid'=>1,'username'=>$username,'password'=>'unusable-fixture-hash','usergroup'=>(string)$groupUid,'email'=>'native@example.com','disable'=>0,'deleted'=>0]);$uid=(string)$db->lastInsertId('fe_users');
 nativeDeny(fn()=>$service->account(['sub'=>$sub,'email'=>'native@example.com','email_verified'=>true]),'Email must never select existing account');
 $store->link($hash,$uid);$record=$service->account(['sub'=>$sub]);nativeCheck((string)$record['uid']===$uid,'Explicit native identity link resolves');nativeDeny(fn()=>$store->link($hash,$uid),'Native unique identity constraint');
 $db->update('fe_users',['disable'=>1],['uid'=>(int)$uid]);nativeDeny(fn()=>$service->account(['sub'=>$sub]),'Disabled frontend user denied');$db->update('fe_users',['disable'=>0],['uid'=>(int)$uid]);
 $auth=new SocaAuthenticationService();$auth->authInfo=['loginType'=>'FE'];nativeCheck($auth->getUser()===false&&$auth->authUser($record)===100,'Normal native authentication remains responsible');
 VerifiedLogin::$record=$record;nativeCheck($auth->getUser()===$record&&$auth->authUser($record)===200,'Verified request-local native handoff authenticates');$auth->authInfo=['loginType'=>'BE'];nativeCheck($auth->getUser()===false&&$auth->authUser($record)===100,'Backend authentication is excluded');VerifiedLogin::$record=null;
 $state=hash('sha256',random_bytes(32));$store->putTicket($state,'browser','revision',time()+300);nativeCheck(!$store->consumeTicket($state,'copied','revision',time()),'Copied native callback denied');nativeCheck($store->consumeTicket($state,'browser','revision',time()),'Native callback consumed');nativeCheck(!$store->consumeTicket($state,'browser','revision',time()),'Native callback replay denied');
 $deployment['provision']=true;file_put_contents($configPath,json_encode($deployment,JSON_THROW_ON_ERROR));$newSub='provision-'.bin2hex(random_bytes(8));$newHash=Identity::hash($service->config()['issuer'],$newSub);$new=$service->account(['sub'=>$newSub,'name'=>'Native fixture user']);$newUid=(string)$new['uid'];nativeCheck($new['usergroup']===(string)$groupUid,'Native provisioning assigns only configured frontend group');nativeCheck((string)$service->account(['sub'=>$newSub])['uid']===$newUid,'Returning provisioned native link stable');
 $store->unlink($newHash);$db->delete('fe_users',['uid'=>(int)$newUid]);$newUid=null;$store->unlink($hash);nativeCheck($db->select(['uid'],'fe_users',['uid'=>(int)$uid])->fetchOne()!==false,'Unlink preserves native account');
 echo "TYPO3 native host: $count assertions passed. No live provider login.\n";
}finally{VerifiedLogin::$record=null;putenv('SOCA_CONFIG_FILE='.$original);if($hash!==null){$store->unlink($hash);}if($uid!==null){$db->delete('fe_users',['uid'=>(int)$uid]);}if($newUid!==null){$db->delete('fe_users',['uid'=>(int)$newUid]);}if($groupUid!==null){$db->delete('fe_groups',['uid'=>$groupUid]);}unlink($configPath);}
