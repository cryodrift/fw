<?php

namespace cryodrift\fw\interface;

interface Validator
{
    public function validate(mixed $data): iterable;

    public function add(Validator $validator): self;
}
