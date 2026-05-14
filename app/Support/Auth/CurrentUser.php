<?php

namespace App\Support\Auth;

final readonly class CurrentUser
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email = null,
    ) {}

    /**
     * @return array{id: string, name: string, email: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
