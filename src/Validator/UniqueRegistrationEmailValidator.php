<?php

namespace App\Validator;

use App\Repository\RegistrationRequestRepository;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueRegistrationEmailValidator extends ConstraintValidator
{
    public function __construct(
        private RegistrationRequestRepository $registrationRequestRepository,
        private UserRepository $userRepository
    ) {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueRegistrationEmail) {
            throw new UnexpectedTypeException($constraint, UniqueRegistrationEmail::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        // Check if email exists in RegistrationRequest
        $existingRequest = $this->registrationRequestRepository->findByEmail($value);
        if ($existingRequest) {
            $this->context->buildViolation($constraint->messageRequest)
                ->addViolation();
            return;
        }

        // Check if email exists in User
        $existingUser = $this->userRepository->findOneBy(['email' => $value]);
        if ($existingUser) {
            $this->context->buildViolation($constraint->messageUser)
                ->addViolation();
            return;
        }
    }
}
