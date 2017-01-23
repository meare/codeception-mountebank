<?php

namespace Codeception\Module;

use Codeception\Configuration;
use Codeception\Exception\ConfigurationException;
use Codeception\Exception\ModuleConfigException;
use Codeception\Extension;
use Codeception\Module;
use Codeception\TestCase;
use Codeception\TestInterface;
use Exception;
use Meare\Juggler\Imposter\Imposter;
use Meare\Juggler\Juggler;

class Mountebank extends Module
{
    /**
     * @var array
     */
    protected $requiredFields = [
        'host',
    ];

    /**
     * @var array
     */
    protected $config = [
        'port'      => Juggler::DEFAULT_PORT,
        'imposters' => [],
    ];

    /**
     * Mountebank client
     *
     * @var Juggler
     */
    private $juggler;

    /**
     * Per-test imposters cache
     *
     * @var Imposter[]
     */
    private $cachedImposters;

    /**
     * Maps imposter alias (key) to imposter port (value)
     *
     * @var array
     */
    private $imposterAliasToPort = [];

    /**
     * List of imposters that were replaced during test
     * Contains imposter aliases as keys and dummy values
     *
     * @var array
     */
    private $replacedImposters = [];

    /**
     * @throws ConfigurationException
     * @throws ModuleConfigException
     */
    public function _initialize()
    {
        $this->juggler = $this->createJuggler();

        $this->initializeImposters();
    }

    protected function validateConfig()
    {
        parent::validateConfig();
        $this->validateImpostersConfig();
    }

    protected function validateImpostersConfig()
    {
        foreach ($this->config['imposters'] as $alias => $imposter_config) {
            if (!isset($imposter_config['contract'])) {
                throw new ModuleConfigException($this, "Missing 'contract' field in imposter configuration ('$alias')");
            }
        }
    }

    /**
     * @return Juggler
     */
    protected function createJuggler()
    {
        return new Juggler($this->config['host'], $this->config['port']);
    }

    /**
     * @throws ConfigurationException
     * @throws Exception
     */
    private function initializeImposters()
    {
        $this->juggler->deleteImposters();
        $this->postConfiguredImposters();
    }

    private function postConfiguredImposters()
    {
        foreach ($this->config['imposters'] as $alias => $imposter_config) {
            $port = $this->juggler->postImposterFromFile($imposter_config['contract']);
            $this->imposterAliasToPort[$alias] = $port;
        }
    }

    /**
     * @return Juggler
     */
    public function _getJuggler()
    {
        return $this->juggler;
    }

    /**
     * @param TestInterface $test
     */
    public function _before(TestInterface $test)
    {
        foreach ($this->config['imposters'] as $alias => $imposter_config) {
            if ($this->imposterShouldBeRestored($alias)) {
                $this->restoreImposter($alias);
            }
        }
    }

    /**
     * Imposter should be restored if it was replaced during test or it was specified as 'mock' in module configuration
     *
     * @param string $alias
     * @return bool
     */
    private function imposterShouldBeRestored($alias)
    {
        return isset($this->replacedImposters[$alias])
        || (isset($this->config['imposters'][$alias]['mock']) && true === $this->config['imposters'][$alias]['mock']);
    }

    /**
     * Restores imposter to initial state by posting contract from configuration
     *
     * @param string $alias
     */
    public function restoreImposter($alias)
    {
        $this->debug("Restoring imposter '$alias'");
        $this->silentlyReplaceImposter(
            $alias,
            $this->config['imposters'][$alias]['contract']
        );
    }

    /**
     * @param string $alias
     * @return int Port
     */
    private function resolveImposterPort($alias)
    {
        if (!isset($this->imposterAliasToPort[$alias])) {
            $this->fail("Imposter '$alias' is not presented in configuration");
        }

        return $this->imposterAliasToPort[$alias];
    }

    /**
     * @throws Exception
     */
    public function _afterSuite()
    {
        $this->saveImposters();
    }

    /**
     * Saves imposter contracts to files if 'save' param was set in imposter config
     *
     * @throws Exception
     */
    private function saveImposters()
    {
        foreach ($this->config['imposters'] as $alias => $imposter_config) {
            if (isset($imposter_config['save'])) {
                $this->juggler->retrieveAndSaveContract($this->resolveImposterPort($alias), $imposter_config['save']);
            }
        }
    }

    /**
     * Fetches imposter from mountebank or returns cached Imposter instance.
     * Imposter instance gets cached for current test after fetching
     *
     * @param string $alias
     * @return Imposter
     * @see Mountebank::fetchImposter() To get Imposter without hitting cache
     */
    public function getImposter($alias)
    {
        if (isset($this->cachedImposters[$alias])) {
            return $this->cachedImposters[$alias];
        } else {
            return $this->fetchImposter($alias);
        }
    }

    /**
     * Retrieves imposter from mountebank
     * Does not looks in cache but caches fetched Imposter instance
     *
     * @param string $alias
     * @return Imposter
     */
    public function fetchImposter($alias)
    {
        $port = $this->resolveImposterPort($alias);

        return $this->cachedImposters[$alias] = $this->juggler->getImposter($port);
    }

    /**
     * Asserts there is one or $exact_quantity of requests recorded in imposter that matches criteria
     *
     * @param string $alias
     * @param array  $criteria
     * @param int    $exact_quantity
     */
    public function seeImposterHasRequestsByCriteria($alias, array $criteria, $exact_quantity = 1)
    {
        $port = $this->resolveImposterPort($alias);
        $imposter = $this->juggler->getImposter($port);
        $requests = $imposter->findRequests($criteria);

        if (sizeof($requests) > 0) {
            $this->debugSection('Matched requests', json_encode($requests, JSON_PRETTY_PRINT));
        }

        $this->assertNotEmpty(
            $requests,
            'Imposter has requests by criteria'
        );
        $this->assertSame(
            $exact_quantity,
            sizeof($requests),
            "Imposter has exactly $exact_quantity requests by criteria"
        );
    }

    /**
     * Asserts there is at least one request recorded in imposter
     *
     * @param string $alias
     */
    public function seeImposterHasRequests($alias)
    {
        $port = $this->resolveImposterPort($alias);
        $imposter = $this->juggler->getImposter($port);

        $this->assertTrue($imposter->hasRequests(), 'Imposter has requests recorded');
    }

    /**
     * Asserts that there are no requests recorded in imposter
     *
     * @param string $alias
     */
    public function seeImposterHasNoRequests($alias)
    {
        $port = $this->resolveImposterPort($alias);
        $imposter = $this->juggler->getImposter($port);

        $this->assertFalse($imposter->hasRequests(), 'Imposter has no request recorded');
    }

    /**
     * Replaces imposter with cached Imposter instance for current test
     * Imposter instance gets cached when retrieved with Mountebank::getImposter() or Mountebank::fetchImposter() methods
     *
     * @param string $alias
     * @see Mountebank::getImposter()
     * @see Mountebank::fetchImposter()
     */
    public function replaceImposterWithCached($alias)
    {
        if (!isset($this->cachedImposters[$alias])) {
            $this->fail("Unable to replace imposter '$alias' with cached instance - no cached instance found");
        }
        $imposter = $this->cachedImposters[$alias];
        $this->replaceImposter($alias, $imposter);
    }

    /**
     * Replaces imposter, which will be restored before next test
     *
     * @param string          $alias
     * @param string|Imposter $imposter_or_path Path to imposter contract or Imposter instance
     */
    public function replaceImposter($alias, $imposter_or_path)
    {
        $this->replacedImposters[$alias] = true;
        $this->silentlyReplaceImposter($alias, $imposter_or_path);
    }

    /**
     * Replaces imposter without queueing it for restore;
     * Expects new imposter to have the same port replaced imposter had
     *
     * @param string          $alias
     * @param string|Imposter $imposter_or_path
     */
    private function silentlyReplaceImposter($alias, $imposter_or_path)
    {
        $port = $this->resolveImposterPort($alias);

        if (is_string($imposter_or_path)) { // Path to imposter contract given
            $this->juggler->deleteImposterIfExists($port);
            $new_port = $this->juggler->postImposterFromFile($imposter_or_path);
        } else { // Assume Imposter instance given
            $new_port = $this->juggler->replaceImposter($imposter_or_path);
        }

        if ($new_port !== $port) {
            $this->fail("Failed to replace imposter '$alias' at port $port - new imposter port does not match ($new_port)");
        }
    }

    /**
     * @param string $relative_path Path relative to project directory
     * @return string
     */
    protected function getFullPath($relative_path)
    {
        return Configuration::projectDir() . DIRECTORY_SEPARATOR . $relative_path;
    }
}
