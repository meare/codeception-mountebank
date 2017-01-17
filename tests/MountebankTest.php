<?php

namespace Codeception\Module\Mountebank\Test;

use Codeception\Exception\ModuleConfigException;
use Codeception\Lib\ModuleContainer;
use Codeception\Module\Mountebank;
use Meare\Juggler\Juggler;

class MountebankTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|Juggler
     */
    private $juggler;

    /**
     * @var array
     */
    private $validConfiguration = [
        'host' => 'localhost',
    ];

    public function setUp()
    {
        $this->juggler = $this->getMockBuilder(Juggler::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testValidConfiguration()
    {
        $this->createMountebankModule($this->validConfiguration);
    }

    public function testConfigurationWithoutHost()
    {
        $this->setExpectedException(ModuleConfigException::class);

        $this->createMountebankModule([]);
    }

    public function testValidImpostersConfiguration()
    {
        $this->createMountebankModule(array_merge($this->validConfiguration, [
            'imposters' => [
                'service' => [
                    'contract' => '/tmp/contract.json'
                ]
            ]
        ]));
    }

    public function testInvalidImpostersConfiguration()
    {
        $this->setExpectedException(ModuleConfigException::class);

        $this->createMountebankModule(array_merge($this->validConfiguration, [
            'imposters' => [
                'service' => []
            ]
        ]));
    }

    public function testImpostersArePostedBeforeSuite()
    {
        $this->juggler->expects($this->exactly(2))
            ->method('postImposterFromFile')
            ->withConsecutive(
                ['_data/mb/service_1_contract.json'],
                ['_data/mb/service_2_contract.json']
            );

        $module = $this->createMountebankModule(array_merge($this->validConfiguration, [
            'imposters' => [
                'service_1' => [
                    'contract' => '_data/mb/service_1_contract.json'
                ],
                'service_2' => [
                    'contract' => '_data/mb/service_2_contract.json'
                ]
            ]
        ]));

        $module->_initialize();
    }

    public function testImpostersAreDeletedBeforeSuite()
    {
        $this->juggler->expects($this->once())
            ->method('deleteImposters');

        $this->createMountebankModule($this->validConfiguration)->_initialize();
    }

    public function testImpostersAreSavedAfterSuite()
    {
        $this->juggler->method('postImposterFromFile')
            ->willReturnOnConsecutiveCalls(4545, 4646);
        $this->juggler->expects($this->once())
            ->method('retrieveAndSaveContract')
            ->with(4545, '_output/mountebank/output_contract.json');

        $module = $this->createMountebankModule(array_merge($this->validConfiguration, [
            'imposters' => [
                'service_1' => [
                    'contract' => 'service_contract.json',
                    'save' => '_output/mountebank/output_contract.json'
                ],
                'service_2' => [
                    'contract' => 'service_contract.json',
                ]
            ]
        ]));

        $module->_initialize();
        $module->_afterSuite();
    }

    /**
     * @param array $config
     * @return Mountebank|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createMountebankModule(array $config)
    {
        $moduleContainer = $this->getMockBuilder(ModuleContainer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $module = $this->getMockBuilder(Mountebank::class)
            ->setConstructorArgs([$moduleContainer, $config])
            ->setMethods(['createJuggler'])
            ->getMock();

        $module->method('createJuggler')
            ->willReturn($this->juggler);

        return $module;
    }
}
