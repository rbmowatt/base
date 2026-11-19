<?php namespace RBMowatt\Base\Models\Interfaces;

interface BaseModelInterface
{
    public function columns();

    public function validate( array $args);

    public function filter(array $args);
}
