<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueRegistrationEmail extends Constraint
{
    public string $messageRequest = 'Cet email possède déjà une demande d\'inscription en attente. Veuillez vérifier votre boîte mail ou réessayer plus tard.';
    public string $messageUser = 'Cet email est déjà associé à un compte. Si vous avez oublié votre mot de passe, utilisez la fonction "Mot de passe oublié".';

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
