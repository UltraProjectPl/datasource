<?php

/*
 * This file is part of the FSi Component package.
 *
 * (c) Szczepan Cieslik <szczepan@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Driver;

class DriverFactoryManager implements DriverFactoryManagerInterface
{
    /**
     * @var array
     */
    private $factories;

    /**
     * @param array $factories
     * @throws \InvalidArgumentException
     */
    public function __construct($factories = array())
    {
        $this->factories = array();

        foreach ($factories as $factory) {
            if (!$factory instanceof DriverFactoryInterface) {
                throw new \InvalidArgumentException("Factory must implement \\FSi\\Component\\DataSource\\Driver\\DriverFactoryInterface");
            }

            $this->addFactory($factory);
        }
    }

    /**
     * @param \FSi\Component\DataSource\Driver\DriverFactoryInterface $factory
     */
    public function addFactory(DriverFactoryInterface $factory)
    {
        $this->factories[$factory->getDriverType()] = $factory;
    }

    /**
     * @param $driverType
     * @return null|\FSi\Component\DataSource\Driver\DriverFactoryInterface
     */
    public function getFactory($driverType)
    {
        if (!$this->hasFactory($driverType)) {
            return;
        }

        return $this->factories[$driverType];
    }

    /**
     * @param $driverType
     * @return bool
     */
    public function hasFactory($driverType)
    {
        return array_key_exists($driverType, $this->factories);
    }
}
