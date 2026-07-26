<?php

namespace App\Repositories\Contracts;

use App\Models\Hint;

interface HintRepositoryInterface
{
    public function find(int $id): ?Hint;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Hint;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Hint $hint, array $data): Hint;

    public function delete(Hint $hint): bool;
}
