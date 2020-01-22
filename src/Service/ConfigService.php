<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Service;

use App\Repository\ConfigRepository;
use OutOfBoundsException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service to read GCMS configuration data
 */
class ConfigService
{
    private ConfigRepository $configRepo;
    private array $namespaceCache;
    private array $validNamespaces;

    /**
     * @param ConfigRepository $configRepo
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(ConfigRepository $configRepo, ParameterBagInterface $parameterBag)
    {
        $this->configRepo = $configRepo;
        $this->namespaceCache = [];
        $this->validNamespaces = array_keys($parameterBag->get('app.config_schema'));
    }

    /**
     * Reads a value from our config and throws if value is not found.
     * @param string $key
     * @param string $namespace
     * @throws OutOfBoundsException
     * @return string
     */
    public function require(string $key, string $namespace='meta'): string
    {
        $this->cacheNamespace($namespace);
        if (!isset($this->namespaceCache[$namespace][$key])) {
            throw new OutOfBoundsException("The key \"{$key}\" does not exist");
        }
        return $this->namespaceCache[$namespace][$key];
    }

    /**
     * Reads a value from our config, returns default value if not found.
     * @param string $key
     * @param string $namespace
     * @param string $default
     * @return string
     */
    public function get(string $key, string $namespace='meta', string $default=''): string
    {
        $this->cacheNamespace($namespace);
        return $this->namespaceCache[$namespace][$key] ?? $default;
    }

    /**
     * Reads an array value from our config, returns default value if not found.
     * @param string $key
     * @param string $namespace
     * @param array $default
     * @return array
     */
    public function getArray(string $key, string $namespace='meta', array $default=[]): array
    {
        try {
            return (array)$this->require($key, $namespace);
        } catch (OutOfBoundsException) {
            return $default;
        }
    }

    /**
     * Returns all known keys in a given namespace.
     * @param string $namespace
     * @return array
     */
    public function getKeys(string $namespace): array
    {
        $this->cacheNamespace($namespace);
        return array_keys($this->namespaceCache[$namespace]);
    }

    /**
     * Caches all values found in a namespace. Throws if namespace not found.
     * @throws OutOfBoundsException
     */
    private function cacheNamespace(string $namespace): void
    {
        if (!isset($this->namespaceCache[$namespace])) {
            $config = $this->configRepo->findOneBy(['namespace' => $namespace]);
            if ($config === null) {
                // if the namespace is actually valid, return an empty one
                if (in_array($namespace, $this->validNamespaces)) {
                    $this->namespaceCache[$namespace] = [];
                } else {
                    throw new OutOfBoundsException("The namespace \"{$namespace}\" does not exist");
                }
            } else {
                $this->namespaceCache[$namespace] = $config->getValue();
            }
        }
    }
}