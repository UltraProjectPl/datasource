<?php

/*
 * This file is part of the FSi Component package.
 *
 * (c) Szczepan Cieslik <szczepan@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Driver\Doctrine\Extension\Core\Field;

/**
 * Datetime field.
 */
class DateTime extends DateTimeAbstract
{
    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return 'datetime';
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormat()
    {
        return 'Y-m-d H:i:s';
    }
}