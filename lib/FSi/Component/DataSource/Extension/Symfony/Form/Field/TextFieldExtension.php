<?php

/*
 * This file is part of the FSi Component package.
 *
 * (c) Szczepan Cieslik <szczepan@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Extension\Symfony\Form\Field;

use FSi\Component\DataSource\Field\FieldViewInterface;
use FSi\Component\DataSource\Field\FieldTypeInterface;
use FSi\Component\DataSource\DataSourceInterface;
use Symfony\Component\Form\FormBuilder;

/**
 * Builds form for text field.
 */
class TextFieldExtension extends FormFieldAbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getExtendedFieldTypes()
    {
        return array('text');
    }

    /**
     * {@inheritdoc}
     */
    protected function buildForm(FieldTypeInterface $field, FormBuilder $builder, $options)
    {
        switch ($field->getComparison()) {
            default:
                $builder->add($field->getName(), 'text', $options);
        }
    }
}