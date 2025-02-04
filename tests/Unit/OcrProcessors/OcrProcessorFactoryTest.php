<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Windey <ro.windey@gmail.com>
 *
 *  @license GNU AGPL version 3 or any later version
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
 */

namespace OCA\WorkflowOcr\Tests\Unit\OcrProcessors;

use OCA\WorkflowOcr\AppInfo\Application;
use OCA\WorkflowOcr\Exception\OcrProcessorNotFoundException;
use OCA\WorkflowOcr\OcrProcessors\IOcrProcessor;
use OCA\WorkflowOcr\OcrProcessors\OcrProcessorFactory;
use OCA\WorkflowOcr\OcrProcessors\PdfOcrProcessor;
use Psr\Container\ContainerInterface;
use Test\TestCase;

class OcrProcessorFactoryTest extends TestCase {
	/** @var ContainerInterface */
	private $appContainer;

	protected function setUp() : void {
		parent::setUp();
		$app = new Application();
		$this->appContainer = $app->getContainer();
	}

	public function testReturnsPdfProcessor() {
		$factory = new OcrProcessorFactory($this->appContainer);
		$processor = $factory->create('application/pdf');
		$this->assertInstanceOf(PdfOcrProcessor::class, $processor);
	}

	public function testThrowsNotFoundExceptionOnInvalidMimeType() {
		$this->expectException(OcrProcessorNotFoundException::class);
		$factory = new OcrProcessorFactory($this->appContainer);
		$factory->create('no/mimetype');
	}

	/**
	 * @dataProvider dataProvider_mimeTypes
	 */
	public function testOcrProcessorsAreNotCached($mimetype) {
		// Related to BUG #43

		$factory = new OcrProcessorFactory($this->appContainer);
		$processor1 = $factory->create($mimetype);
		$processor2 = $factory->create($mimetype);
		$this->assertFalse($processor1 === $processor2);
	}

	/**
	 * @dataProvider dataProvider_mimeTypes
	 */
	public function testPdfCommandNotCached($mimetype) {
		// Related to BUG #43

		$factory = new OcrProcessorFactory($this->appContainer);
		$processor1 = $factory->create($mimetype);
		$processor2 = $factory->create($mimetype);
		$cmd1 = $this->getCommandObject($processor1);
		$cmd2 = $this->getCommandObject($processor2);

		$this->assertFalse($cmd1 === false);
		$this->assertFalse($cmd2 === false);
		$this->assertFalse($cmd1 === $cmd2);
	}

	public function dataProvider_mimeTypes() {
		$mimetypes = [];
		$mapping = $this->invokePrivate(OcrProcessorFactory::class, 'mapping');
		foreach ($mapping as $mimetype => $className) {
			$mimetypes[] = [$mimetype];
		}
		return $mimetypes;
	}

	private function getCommandObject(IOcrProcessor $ocrProcessor) {
		$reflection = new \ReflectionClass($ocrProcessor);
		if ($reflection->hasProperty('command')) {
			return $this->getCommandObjectFromReflection($reflection, $ocrProcessor);
		}
		$reflection = $reflection->getParentClass();
		return $this->getCommandObjectFromReflection($reflection, $ocrProcessor);
	}

	private function getCommandObjectFromReflection(\ReflectionClass $reflection, IOcrProcessor $object) {
		if ($reflection->hasProperty('command')) {
			$property = $reflection->getProperty('command');
			$property->setAccessible(true);
			return $property->getValue($object);
		}
		return false;
	}
}
