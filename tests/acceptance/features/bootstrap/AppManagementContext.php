<?php
/**
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

use Behat\Behat\Context\Context;
use TestHelpers\SetupHelper;

require __DIR__ . '/../../../../lib/composer/autoload.php';

/**
 * App Management context.
 */
class AppManagementContext implements Context {
	private $oldAppsPaths;

	/**
	 * @var string stdout of last command
	 */
	private $cmdOutput;

	/**
	 * @BeforeScenario @skipWhenTestingRemoteSystems
	 *
	 * Remember the config values before each scenario
	 *
	 * @return void
	 * @throws Exception
	 */
	public function prepareParameters() {
		include_once __DIR__ . '/../../../../lib/base.php';
		$value = SetupHelper::runOcc(
			['config:system:get', 'apps_paths', '--output', 'json']
		)['stdOut'];

		if ($value === '') {
			$this->oldAppsPaths = null;
		} else {
			$this->oldAppsPaths = \json_decode($value, true);
		}
	}

	/**
	 * @AfterScenario @skipWhenTestingRemoteSystems
	 *
	 * Reset the config values after each scenario
	 *
	 * @return void
	 * @throws Exception
	 */
	public function undoChangingParameters() {
		if ($this->oldAppsPaths === null) {
			SetupHelper::runOcc(['config:system:delete', 'apps_paths']);
		} else {
			$this->setAppsPaths($this->oldAppsPaths);
		}
	}

	/**
	 *
	 * @param array $appsPaths of apps_paths entries
	 *
	 * @return string[] associated array with "code", "stdOut", "stdErr"
	 * @throws Exception
	 */
	public function setAppsPaths($appsPaths) {
		return SetupHelper::runOcc(
			[
				'config:system:set',
				'apps_paths',
				'--type',
				'json',
				'--value',
				\json_encode($appsPaths)
			]
		);
	}

	/**
	 * @Given apps have been put in two directories :dir1 and :dir2
	 *
	 * @param string $dir1
	 * @param string $dir2
	 *
	 * @return void
	 * @throws Exception
	 */
	public function setAppDirectories($dir1, $dir2) {
		$fullpath1 = \OC::$SERVERROOT . "/$dir1";
		$fullpath2 = \OC::$SERVERROOT . "/$dir2";

		$this->setAppsPaths(
			[
				['path' => $fullpath1, 'url' => $dir1, 'writable' => true],
				['path' => $fullpath2, 'url' => $dir2, 'writable' => true]
			]
		);
	}

	/**
	 * @Given app :appId with version :version has been put in dir :dir
	 *
	 * @param string $appId app id
	 * @param string $version app version
	 * @param string $dir app directory
	 *
	 * @return void
	 */
	public function appHasBeenPutInDir($appId, $version, $dir) {
		$ocVersion = SetupHelper::runOcc(
			['config:system:get', 'version']
		)['stdOut'];
		$appInfo = \sprintf(
			'<?xml version="1.0"?>
			<info>
				<id>%s</id>
				<name>%s</name>
				<description>description</description>
				<licence>AGPL</licence>
				<author>Author</author>
				<version>%s</version>
				<category>collaboration</category>
				<website>https://github.com/owncloud/</website>
				<bugs>https://github.com/owncloud/</bugs>
				<repository type="git">https://github.com/owncloud/</repository>
				<screenshot>https://raw.githubusercontent.com/owncloud/screenshots/</screenshot>
				<dependencies>
					<owncloud min-version="%s" max-version="%s" />
				</dependencies>
			</info>',
			$appId,
			$appId,
			$version,
			$ocVersion,
			$ocVersion
		);
		$appsDir = \OC::$SERVERROOT . "/$dir";
		if (!\file_exists($appsDir)) {
			\mkdir($appsDir);
		}
		if (!\file_exists("$appsDir/$appId")) {
			\mkdir("$appsDir/$appId");
		}

		$fullpath = "$appsDir/$appId";

		if (!\file_exists("$fullpath/appinfo")) {
			\mkdir("$fullpath/appinfo");
		}

		\file_put_contents("$fullpath/appinfo/info.xml", $appInfo);
	}

	/**
	 * @When the administrator gets the path for app :appId using the console
	 * @Given the administrator has got the path for app :appId using the console
	 *
	 * @param string $appId app id
	 *
	 * @return void
	 */
	public function adminGetsPathForApp($appId) {
		$this->cmdOutput = SetupHelper::runOcc(
			['app:getpath', $appId, '--no-ansi']
		)['stdOut'];
	}

	/**
	 * @Then the path to :appId should be :dir
	 *
	 * @param string $appId
	 * @param string $dir
	 *
	 * @return void
	 */
	public function appPathIs($appId, $dir) {
		PHPUnit_Framework_Assert::assertEquals(
			\OC::$SERVERROOT . "/$dir/$appId",
			\trim($this->cmdOutput)
		);
	}
}
