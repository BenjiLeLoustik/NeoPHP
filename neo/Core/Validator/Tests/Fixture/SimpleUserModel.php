<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Tests\Fixture;

use Neo\Core\Validator\Assert\EqualToField;
use Neo\Core\Validator\Assert\Email;
use Neo\Core\Validator\Assert\Length;
use Neo\Core\Validator\Assert\NotBlank;

final class SimpleUserModel
{
    public function __construct(
        #[NotBlank(message: 'Name is required')]
        #[Length(min: 3, message: 'Name is too short')]
        public string $name = '',

        #[Email(message: 'Invalid email address')]
        public string $email = '',

        public string $password = '',

        #[EqualToField(field: 'password', message: 'Passwords do not match')]
        public string $confirmPassword = '',

        public string $notes = '',
    ) {
    }
}