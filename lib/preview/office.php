<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace OCA\Onlyoffice\Preview;

use OC\Preview\Provider;

use OCP\ILogger;
use OCP\Image;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Http\Client\IClientService;
use OCP\ISession;
use OCP\Share\IManager;
use OCP\Files\IRootFolder;

use OCA\Files_Versions\Versions\IVersionManager;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\FileUtility;
use OCA\Onlyoffice\FileVersions;

/**
 * Preview provider
 *
 * @package OCA\Onlyoffice
 */
abstract class Office extends Provider {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * Root folder
     *
     * @var IRootFolder
     */
    private $root;

	/** 
     * Client service
     * 
     * @var IClientService
     */

	private $clientService;

	/**
     * Logger
     *  
     * @var ILogger 
     */
    private $logger;

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $config;

	/**
     * Document service
     *
     * @var DocumentService
     */
    private $documentService;

	/**
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

	/**
     * Hash generator
     *
     * @var Crypt
     */
    private $crypt;

    /**
     * File version manager
     *
     * @var IManager
     */
    private $shareManager;

    /**
     * File version manager
     *
     * @var ISession
     */
    private $session;

    /**
     * File version manager
     *
     * @var IVersionManager
    */
    private $versionManager;

    /**
     * File utility
     *
     * @var FileUtility
     */
    private $fileUtility;

    /**
     * Capabilities mimetype
     *
     * @var Array
     */
    private $capabilities = [
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/vnd.openxmlformats-officedocument.presentationml.presentation",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    ];

    /**
     * Converted thumbnail format
     */
    private const thumbExtension = "jpeg";

    /**
     * @param string $appName - application name
     * @param IRootFolder $root - root folder
     * @param IClientService $clientService - client service
     * @param ILogger $logger - logger
     * @param IL10N $trans - l10n service
     * @param AppConfig $config - application configuration
     * @param IURLGenerator $urlGenerator - url generator service
     * @param Crypt $crypt - hash generator
     * @param IManager $shareManager - share manager
     * @param ISession $session - session
     */
    public function __construct(string $appName,
                                    IRootFolder $root, 
                                    IClientService $clientService, 
                                    ILogger $logger,
                                    IL10N $trans,
                                    AppConfig $config,
                                    IURLGenerator $urlGenerator,
                                    Crypt $crypt,
                                    IManager $shareManager,
                                    ISession $session
                                    ) {
        $this->appName = $appName;
        $this->root = $root;
		$this->clientService = $clientService;
        $this->logger = $logger;
        $this->trans = $trans;
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->crypt = $crypt;
        $this->shareManager = $shareManager;
        $this->session = $session;

        if (\OC::$server->getAppManager()->isInstalled("files_versions")) {
            try {
                $this->versionManager = \OC::$server->query(IVersionManager::class);
            } catch (QueryException $e) {
                $this->logger->logException($e, ["message" => "VersionManager init error", "app" => $this->appName]);
            }
        }

        $this->fileUtility = new FileUtility($appName, $trans, $logger, $config, $shareManager, $session);
    }

    /**
     * The method checks if the file can be converted
     *
     * @param FileInfo $file - File
     *
     * @return bool
     */
    public function isAvailable(\OCP\Files\FileInfo $file) {
        $isAvailable = in_array($file->getMimetype(), $this->capabilities, true);
		return $isAvailable;
    }

    /**
     * The method is generated thumbnail for file and returned image object
     *
     * @param string $path - Path of file
     * @param int $maxX - The maximum X size of the thumbnail
     * @param int $maxY - The maximum Y size of the thumbnail
     * @param bool $scalingup - Disable/Enable upscaling of previews
     * @param OC\Files\View $fileview - Fileview object of user folder
     *
     * @return Image|bool false if no preview was generated
     */
	public function getThumbnail($path, $maxX, $maxY, $scalingup, $fileview) {
        list ($fileInfo, $extension, $key, $version) = $this->getFileParam($path, $fileview);
        if($fileInfo === null || $extension === null || $key === null) {
            return false;
        }

        $owner = $fileInfo->getOwner();
        $fileUrl = $this->getUrl($fileInfo, $owner, $version);

        $this->documentService = new DocumentService($this->trans, $this->config);
        $imageUrl = $this->documentService->GetConvertedUri($fileUrl, $extension, self::thumbExtension, $key, false);

        $client = $this->clientService->newClient();
        $response = $client->get($imageUrl);

        $image = new Image();
        $image->loadFromData($response->getBody());
        if ($image->valid()) {
			$image->scaleDownToFit($maxX, $maxY);
			return $image;
        }

		return false;
    }

    /**
     * Generate secure link to download document
     *
     * @param File $file - file
     * @param IUser $user - user with access
     * @param int $version - file version
     *
     * @return string
     */
    private function getUrl($file, $user = null, $version = 0) {

        $data = [
            "action" => "download",
            "fileId" => $file->getId()
        ];

        $userId = null;
        if (!empty($user)) {
            $userId = $user->getUID();
            $data["userId"] = $userId;
        }
        if ($version > 0) {
            $data["version"] = $version;
        }

        $hashUrl = $this->crypt->GetHash($data);

        $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.download", ["doc" => $hashUrl]);

        if (!empty($this->config->GetStorageUrl())
            && !$changes) {
            $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $fileUrl);
        }

        return $fileUrl;
    }

    /**
     * Generate array with file parameters
     *
     * @param string $path - Path of file
     * @param OC\Files\View $fileview - Fileview object of user folder
     *
     * @return array
     */
    private function getFileParam($path, $fileview) {
        list ($filePath, $fileVersion) = FileVersions::splitPathVersion($path);
        $fileVersion = !empty($fileVersion) ? $fileVersion : null;

        $fileInfo = $fileview->getFileInfo($path);

        $key = $this->fileUtility->getKey($fileInfo);
        $key = DocumentService::GenerateRevisionId($key);

        $versionNum = 0;
        if($fileVersion !== null) {
            if ($this->versionManager === null) {
                return [null, null, null, null];
            }

            $owner = $fileInfo->getOwner();
            $versionFolder = $this->root->getUserFolder($owner->getUID())->getParent()->get("files_versions");
            $absolutPath = $fileInfo->getPath();
            $relativePath = $versionFolder->getRelativePath($absolutPath);

            list ($filePath, $fileVersion) = FileVersions::splitPathVersion($relativePath);

            $sourceFile = $this->root->getUserFolder($fileInfo->getOwner()->getUID())->get($filePath);

            $fileInfo = $sourceFile->getFileInfo();

            $versions = array_reverse($this->versionManager->getVersionsForFile($owner, $fileInfo));

            foreach ($versions as $version) {
                $versionNum = $versionNum + 1;

                $versionId = $version->getRevisionId();
                if($versionId === $fileVersion) {
                    break;
                }
            }
        }

        $fileExtension = $fileInfo->getExtension();

        return [$fileInfo, $fileExtension, $key, $versionNum];
    }
}