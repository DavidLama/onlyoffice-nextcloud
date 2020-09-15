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
use OCP\Http\Client\IClientService;

/**
 * Preview provider
 *
 * @package OCA\Onlyoffice
 */
abstract class Office extends Provider {

	/** 
     * Client service
     * 
     * @var IClientService
     * */
	private $clientService;

	/**
     * Logger
     *  
     * @var ILogger 
     * */
    private $logger;

    /**
     * @param IClientService $clientService - client service
     * @param ILogger $logger - logger
     */
	public function __construct(IClientService $clientService, ILogger $logger) {
		$this->clientService = $clientService;
		$this->logger = $logger;
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
		return false;
    }
}