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

    /**
     * Publishes the case if it satisfies the invariants agreed in the
     * architecture doc; otherwise leaves it untouched.
     *
     * @return array<int, string> Invariant failure messages — empty means published.
     */
    public function publish(CaseModel $case): array
    {
        $errors = $this->publishInvariantErrors($case);

        if ($errors !== []) {
            return $errors;
        }

        $this->cases->update($case, ['status' => CaseStatus::Published]);

        return [];
    }

    /**
     * Read-only invariant check, reusable by the Publish button's
     * disabled-state and (later) the admin dashboard's "Needs attention"
     * widget without actually attempting to publish.
     *
     * TODO(Phase 7): the "at least one evidence item" invariant from the
     * architecture doc is intentionally not checked yet — evidence
     * authoring doesn't exist until Phase 7, so every case currently has
     * zero evidence items, which would make every case permanently
     * unpublishable. Add back:
     *     if ($case->evidenceItems()->doesntExist()) {
     *         $errors[] = 'The case needs at least one evidence item.';
     *     }
     *
     * @return array<int, string>
     */
    public function publishInvariantErrors(CaseModel $case): array
    {
        $errors = [];

        if ($case->rubricCriteria()->doesntExist()) {
            $errors[] = 'The case needs at least one rubric criterion.';
        }

        if ((float) $case->rubricCriteria()->sum('weight') <= 0) {
            $errors[] = 'The rubric criteria weights must sum to more than zero.';
        }

        return $errors;
    }
}
