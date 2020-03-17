<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\TermsOfService\AppInfo;

use Exception;
use OC;
use OC\Files\Filesystem;
use OC\Files\Storage\Wrapper\Wrapper;
use OCA\TermsOfService\Checker;
use OCA\TermsOfService\Db\Mapper\SignatoryMapper;
use OCA\TermsOfService\Filesystem\StorageWrapper;
use OCA\TermsOfService\Notifications\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\QueryException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use OCP\Util;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App {


	const APPNAME = 'terms_of_service';


	public function __construct() {
		parent::__construct('terms_of_service');
	}

	public function register() {
		$this->registerNotifier();
		$this->createNotificationOnFirstLogin();

		Util::connectHook('OC_Filesystem', 'preSetup', $this, 'addStorageWrapper');

		// Only display the app on index.php except for public shares
		/** @var OC\Server $server */
		$server = $this->getContainer()
					   ->getServer();
		$request = $server->getRequest();

		try {
			$server->query(IEventDispatcher::class)->addListener(IUser::class . '::postDelete', function (GenericEvent $event) use ($server) {
				/** @var SignatoryMapper $signatoryMapper */
				$signatoryMapper = $server->query(SignatoryMapper::class);
				$signatoryMapper->deleteSignatoriesByUser($event->getSubject());
			});
		} catch (QueryException $e) {
		}


		if (!\OC::$CLI) {
			Util::addStyle('terms_of_service', 'overlay');

			if ($server->getUserSession()
					   ->getUser() !== null
				&& strpos($request->getPathInfo(), '/s/') !== 0
				&& strpos($request->getPathInfo(), '/login/') !== 0
				&& substr($request->getScriptName(), 0 - strlen('/index.php')) === '/index.php') {
				Util::addScript('terms_of_service', 'terms_of_service_user');
			} else if ($server->getConfig()
							  ->getAppValue(self::APPNAME, 'tos_on_public_shares', '0') === '1') {
				Util::addScript('terms_of_service', 'terms_of_service_public');
			}
		}
	}

	public function addStorageWrapper() {
		Filesystem::addStorageWrapper(
			'terms_of_service', [$this, 'addStorageWrapperCallback'], -10
		);
	}

	/**
	 * @param string $mountPoint
	 * @param IStorage|Wrapper $storage
	 *
	 * @return StorageWrapper|IStorage
	 * @throws Exception
	 */
	public function addStorageWrapperCallback(string $mountPoint, IStorage $storage) {
		if (!\OC::$CLI) {
			try {
				return new StorageWrapper(
					[
						'storage'    => $storage,
						'mountPoint' => $mountPoint,
						'request'    => $this->getContainer()
											 ->getServer()
											 ->getRequest(),
						'checker'    => $this->getContainer()
											 ->query(Checker::class),
					]
				);
			} catch (QueryException $e) {
				$this->getContainer()
					 ->getServer()
					 ->getLogger()
					 ->logException($e);
			}
		}

		return $storage;
	}

	protected function registerNotifier() {
		$this->getContainer()
			 ->getServer()
			 ->getNotificationManager()
			 ->registerNotifierService(Notifier::class);
	}

	protected function createNotificationOnFirstLogin() {
		$this->getContainer()
			 ->getServer()
			 ->getEventDispatcher()
			 ->addListener(
				 IUser::class . '::firstLogin', function(GenericEvent $event) {
				 $user = $event->getSubject();
				 if (!$user instanceof IUser) {
					 return;
				 }

				 $notificationsManager = $this->getContainer()
											  ->getServer()
											  ->getNotificationManager();
				 $notification = $notificationsManager->createNotification();
				 $notification->setApp('terms_of_service')
							  ->setDateTime(new \DateTime())
							  ->setSubject('accept_terms')
							  ->setObject('terms', '1')
							  ->setUser($user->getUID());
				 $notificationsManager->notify($notification);
			 }
			 );
	}
}
