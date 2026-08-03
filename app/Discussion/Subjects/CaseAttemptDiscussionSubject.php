<?php

namespace App\Discussion\Subjects;

use App\Models\CaseAttempt;
use App\Discussion\Contracts\DiscussionSubjectInterface;

/**
 * The one DiscussionSubjectInterface implementation Version 2 ships
 * (docs/13-ai-discussion-engine-design.md §1.5) — an adapter, not a god
 * object: reads from CaseAttempt/CaseModel/EvidenceItem/RubricCriterion (all
 * read-only, all existing Version 1 models, untouched by this class or
 * anything in Phase 13–15) and reshapes that into the three methods
 * DiscussionSubjectInterface requires. Nothing about this adapter lives
 * inside DiscussionService — a future subject (a code submission, a design
 * brief) is a new class here, not a change to how DiscussionService, the
 * state machine, or any other part of the engine works.
 */
class CaseAttemptDiscussionSubject implements DiscussionSubjectInterface
{
    public function __construct(
        private readonly CaseAttempt $attempt,
    ) {}

    /**
     * §4.1 point 1 / §1.5 — a short phrase, not the full role-framing
     * sentence (persona supplies the "senior engineer conducting a code
     * review / technical interview" half; this is only the "what").
     */
    public function framingText(): string
    {
        return sprintf(
            'reviewing an incident investigation into "%s"',
            $this->attempt->case->title,
        );
    }

    /**
     * §4.1 point 3 — server-side only, never sent to the client as-is; the
     * persona's own prompt fragment is what instructs the model never to
     * recite this verbatim (this method only supplies the material, it
     * doesn't enforce the instruction).
     */
    public function groundTruthContext(): string
    {
        $case = $this->attempt->case;

        $lines = [
            'Ticket:',
            $case->ticket_content,
            '',
            'Evidence available in this case:',
        ];

        foreach ($case->evidenceItems as $evidenceItem) {
            $lines[] = sprintf(
                '- [%s] %s: %s',
                $evidenceItem->evidenceType->code,
                $evidenceItem->title,
                json_encode($evidenceItem->payload),
            );
        }

        $lines[] = '';
        $lines[] = 'Rubric criteria a strong diagnosis covers:';

        foreach ($case->rubricCriteria as $criterion) {
            $lines[] = sprintf(
                '- %s (weight %s, %s): %s',
                $criterion->title,
                $criterion->weight,
                $criterion->matching_type->value,
                json_encode($criterion->expected_data),
            );
        }

        $lines[] = '';
        $lines[] = 'Model solution:';
        $lines[] = $case->model_solution_summary ?: '(no model solution recorded for this case)';

        return implode("\n", $lines);
    }

    /**
     * §4.1 point 4 — grounds follow-up questions in what this specific
     * student has actually done, not generic Socratic filler.
     */
    public function progressContext(): string
    {
        $lines = ['Evidence the student has viewed so far:'];

        $views = $this->attempt->evidenceViews()->with('evidenceItem')->get();

        if ($views->isEmpty()) {
            $lines[] = '(none yet)';
        } else {
            foreach ($views as $view) {
                $lines[] = sprintf('- %s (viewed %d time(s))', $view->evidenceItem->title, $view->view_count);
            }
        }

        $lines[] = '';
        $lines[] = "Student's investigation notes:";
        $lines[] = $this->attempt->investigationNote?->content ?: '(no notes written yet)';

        return implode("\n", $lines);
    }
}
