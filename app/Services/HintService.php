<?php

namespace App\Services;

use App\Models\CaseModel;
use App\Models\Hint;
use App\Repositories\Contracts\HintRepositoryInterface;

class HintService
{
    public function __construct(
        private readonly HintRepositoryInterface $hints,
    ) {}

    /**
     * New hints are appended to the end of the case's unlock order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(CaseModel $case, array $data): Hint
    {
        $data['case_id'] = $case->id;
        $data['order_index'] = ($case->hints()->max('order_index') ?? -1) + 1;

        return $this->hints->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Hint $hint, array $data): Hint
    {
        return $this->hints->update($hint, $data);
    }

    public function delete(Hint $hint): void
    {
        $this->hints->delete($hint);
    }

    /**
     * Swaps unlock order with the preceding hint, if any.
     */
    public function moveUp(Hint $hint): void
    {
        $previous = Hint::where('case_id', $hint->case_id)
            ->where('order_index', '<', $hint->order_index)
            ->orderByDesc('order_index')
            ->first();

        if ($previous) {
            $this->swapOrder($hint, $previous);
        }
    }

    /**
     * Swaps unlock order with the following hint, if any.
     */
    public function moveDown(Hint $hint): void
    {
        $next = Hint::where('case_id', $hint->case_id)
            ->where('order_index', '>', $hint->order_index)
            ->orderBy('order_index')
            ->first();

        if ($next) {
            $this->swapOrder($hint, $next);
        }
    }

    private function swapOrder(Hint $a, Hint $b): void
    {
        $aOrder = $a->order_index;
        $bOrder = $b->order_index;

        $this->hints->update($a, ['order_index' => $bOrder]);
        $this->hints->update($b, ['order_index' => $aOrder]);
    }
}
