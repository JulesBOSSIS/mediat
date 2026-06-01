<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class AllowedEmailDomain extends Constraint
{
    public string $message = 'Vous n\'êtes pas autorisé à créer un compte avec cette adresse email.';

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
