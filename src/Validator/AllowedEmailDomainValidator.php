<?php

namespace App\Validator;

use App\Repository\AllowedEmailDomainRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class AllowedEmailDomainValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AllowedEmailDomainRepository $allowedEmailDomainRepository
    ) {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof AllowedEmailDomain) {
            throw new UnexpectedTypeException($constraint, AllowedEmailDomain::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        // Extract domain from email
        $emailParts = explode('@', $value);
        if (count($emailParts) !== 2) {
            return; // Let the Email constraint handle invalid email format
        }

        $domain = strtolower(trim($emailParts[1]));

        // Check if domain is allowed
        if (!$this->allowedEmailDomainRepository->isDomainAllowed($domain)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ domain }}', $domain)
                ->addViolation();
        }
    }
}
