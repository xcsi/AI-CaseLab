<?php

namespace App\Services;

use App\Enums\CaseStatus;
use App\Models\CaseModel;
use App\Models\User;
use App\Repositories\Contracts\CaseRepositoryInterface;

class CaseCatalogService
{
    public function __construct(
        private readonly CaseRepositoryInterface $cases,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $author): CaseModel
    {
        $data['created_by'] = $author->id;

        return $this->cases->create($data);
    }

    /**
     * Published cases bump `version` on every edit (Database Design
     * Decision 4) so students can be shown "this case changed since your
     * last attempt"; drafts are edited freely with no version churn.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(CaseModel $case, array $data): CaseModel
    {
        if ($case->status === CaseStatus::Published) {
            $data['version'] = $case->version + 1;
        }

        return $this->cases->update($case, $data);
    }
}
