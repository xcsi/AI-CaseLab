<?php

namespace Database\Seeders;

use App\Enums\CaseStatus;
use App\Models\CaseModel;
use App\Models\Category;
use App\Models\EvidenceItem;
use App\Models\EvidenceType;
use App\Models\Hint;
use App\Models\RubricCriterion;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Three fully-populated, published demo cases for live walkthroughs (a
 * graduation defense, a portfolio demo) — not run by default in
 * DatabaseSeeder::run(), invoked explicitly with
 * `php artisan db:seed --class=DemoDataSeeder`. Evidence is attached
 * directly (create(), not the factory's random content) since there is no
 * admin evidence-authoring UI to exercise instead — the same gap every
 * evidence-dependent test in the suite already works around.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('email', 'admin@aicaselab.test')->first()
            ?? User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->first();

        if (! $author) {
            $this->command?->warn('DemoDataSeeder: no admin user found — run AdminUserSeeder first (skipped in production).');

            return;
        }

        $this->apiReturning500($author);
        $this->loginFailuresAfterPasswordReset($author);
        $this->dashboardQueriesTimingOut($author);
    }

    private function apiReturning500(User $author): void
    {
        $case = CaseModel::updateOrCreate(
            ['slug' => 'api-returning-500-on-checkout'],
            [
                'category_id' => $this->category('Backend'),
                'created_by' => $author->id,
                'title' => 'API Returning 500 on Checkout',
                'summary' => 'Customers report intermittent 500 errors completing checkout since this morning\'s deploy.',
                'ticket_content' => "Priority: High\nReporter: Sarah Chen (Support Lead)\n\nMultiple customers report the checkout page fails with a generic \"Something went wrong\" error. It doesn't happen every time — maybe 1 in 5 attempts. Started right after this morning's 9am deploy. Payment isn't going through on the failed attempts. Please investigate ASAP, we're losing sales.",
                'learning_outcomes' => 'Correlate an application error log with an upstream API timeout; recognize a missing timeout/retry policy as a root cause, not just a symptom.',
                'difficulty' => 'medium',
                'estimated_minutes' => 25,
                'status' => CaseStatus::Draft,
                'model_solution_summary' => "The checkout controller calls the payment gateway with no timeout configured. Under load, the gateway's response occasionally exceeds PHP's default socket timeout, and the resulting exception isn't caught — it bubbles up as an unhandled 500 instead of a retry or a graceful \"payment pending\" state. The fix is a bounded timeout plus a single retry with backoff, per the proposed_fix expected in the diagnosis.",
                'allow_reattempt' => true,
            ]
        );

        $this->evidence($case, 'log', 'Application error log (checkout-service)', 1, [
            'lines' => [
                ['timestamp' => '2026-07-29 09:14:02', 'level' => 'info', 'text' => 'Checkout started for order #48213'],
                ['timestamp' => '2026-07-29 09:14:04', 'level' => 'info', 'text' => 'Calling payment gateway POST /v1/charges'],
                ['timestamp' => '2026-07-29 09:14:34', 'level' => 'error', 'text' => 'GuzzleHttp\\Exception\\ConnectException: cURL error 28: Operation timed out after 30000 milliseconds'],
                ['timestamp' => '2026-07-29 09:14:34', 'level' => 'error', 'text' => 'Uncaught exception in CheckoutController@store — returning 500'],
            ],
        ]);

        $this->evidence($case, 'code_snippet', 'CheckoutController@store', 2, [
            'filename' => 'app/Http/Controllers/CheckoutController.php',
            'language' => 'php',
            'code' => "public function store(Request \$request)\n{\n    \$client = new \\GuzzleHttp\\Client();\n\n    \$response = \$client->post('https://payments.example.com/v1/charges', [\n        'json' => \$request->validated(),\n    ]);\n\n    return response()->json(\$response->getBody());\n}",
            'highlight_lines' => [3, 5],
        ]);

        $this->evidence($case, 'api_response', 'Payment gateway response (failed attempt)', 3, [
            'method' => 'POST',
            'endpoint' => '/v1/charges',
            'status' => 504,
            'response_body' => ['error' => 'gateway_timeout', 'message' => 'Upstream did not respond within 30s'],
        ]);

        Hint::updateOrCreate(
            ['case_id' => $case->id, 'order_index' => 0],
            ['content' => 'Check the application error log around the time of a failed checkout — what exception is actually thrown?', 'score_penalty' => 3],
        );
        Hint::updateOrCreate(
            ['case_id' => $case->id, 'order_index' => 1],
            ['content' => 'Look at how CheckoutController calls the payment gateway client — is there a timeout configured?', 'score_penalty' => 5],
        );

        $evidenceIds = $case->evidenceItems()->pluck('id')->all();

        RubricCriterion::updateOrCreate(
            ['case_id' => $case->id, 'title' => 'Identifies the upstream timeout as root cause'],
            ['weight' => 15, 'matching_type' => 'keyword', 'expected_data' => ['keywords' => ['timeout', 'upstream', 'gateway']]],
        );
        RubricCriterion::updateOrCreate(
            ['case_id' => $case->id, 'title' => 'Cites the error log and gateway response as evidence'],
            ['weight' => 10, 'matching_type' => 'evidence_citation', 'expected_data' => ['required_evidence_ids' => $evidenceIds]],
        );
        RubricCriterion::updateOrCreate(
            ['case_id' => $case->id, 'title' => 'Proposes a sound fix (timeout + retry policy)'],
            ['weight' => 10, 'matching_type' => 'manual', 'expected_data' => []],
        );

        $this->publish($case);
    }

    private function loginFailuresAfterPasswordReset(User $author): void
    {
        $case = CaseModel::updateOrCreate(
            ['slug' => 'login-failures-after-password-reset'],
            [
                'category_id' => $this->category('Backend'),
                'created_by' => $author->id,
                'title' => 'Login Failures After Password Reset',
                'summary' => 'Users who reset their password can no longer log in with the new one.',
                'ticket_content' => "Priority: Medium\nReporter: Miguel Santos (Support)\n\nSeveral users say they reset their password via the \"Forgot Password\" flow, got the confirmation email, set a new password, but then can't log in — it just says \"Invalid credentials.\" Logging in with the OLD password also fails. They're locked out entirely. Started after yesterday's auth service release.",
                'learning_outcomes' => 'Read a database snapshot alongside an auth log to spot a hashing mismatch, rather than assuming the reported symptom (\"can\'t log in\") points at the login code itself.',
                'difficulty' => 'easy',
                'estimated_minutes' => 15,
                'status' => CaseStatus::Draft,
                'model_solution_summary' => "Yesterday's release changed the password hashing driver's default cost factor without rehashing existing rows, but more importantly the reset endpoint was writing the new password through the OLD hasher config already cached in a stale queue worker, producing a hash the current login code's verifier never matches. The fix is restarting the queue workers after a hashing-config change (or better, versioning the hash format) — not a login-code bug at all.",
                'allow_reattempt' => true,
            ]
        );

        $this->evidence($case, 'db_snapshot', 'users table (affected account)', 1, [
            'table' => 'users',
            'columns' => [['name' => 'id', 'type' => 'bigint'], ['name' => 'email', 'type' => 'varchar'], ['name' => 'password', 'type' => 'varchar(60)'], ['name' => 'updated_at', 'type' => 'timestamp']],
            'rows' => [
                ['id' => 4821, 'email' => 'j.reyes@example.com', 'password' => '$2y$04$abcdEFGHij...', 'updated_at' => '2026-07-28 16:02:11'],
            ],
        ]);

        $this->evidence($case, 'log', 'Auth service log', 2, [
            'lines' => [
                ['timestamp' => '2026-07-28 16:02:11', 'level' => 'info', 'text' => 'Password reset completed for user 4821'],
                ['timestamp' => '2026-07-28 16:05:44', 'level' => 'warn', 'text' => 'Login attempt for user 4821 — hash verification failed'],
                ['timestamp' => '2026-07-28 16:05:44', 'level' => 'info', 'text' => 'Hasher cost factor in use: 4 (queue worker cached config, deployed cost factor: 12)'],
            ],
        ]);

        Hint::updateOrCreate(
            ['case_id' => $case->id, 'order_index' => 0],
            ['content' => 'Compare the hasher cost factor mentioned in the auth log to what was actually deployed — do they match?', 'score_penalty' => 3],
        );

        $evidenceIds = $case->evidenceItems()->pluck('id')->all();

        RubricCriterion::updateOrCreate(
            ['case_id' => $case->id, 'title' => 'Identifies the stale hasher config as root cause'],
            ['weight' => 20, 'matching_type' => 'keyword', 'expected_data' => ['keywords' => ['hash', 'cost factor', 'queue worker']]],
        );
        RubricCriterion::updateOrCreate(
            ['case_id' => $case->id, 'title' => 'Cites the DB snapshot and auth log as evidence'],
            ['weight' => 10, 'matching_type' => 'evidence_citation', 'expected_data' => ['required_evidence_ids' => $evidenceIds]],
        );

        $this->publish($case);
    }

    private function dashboardQueriesTimingOut(User $author): void
    {
        $case = CaseModel::updateOrCreate(
            ['slug' => 'dashboard-queries-timing-out'],
            [
                'category_id' => $this->category('Database'),
                'created_by' => $author->id,
                'title' => 'Dashboard Queries Timing Out',
                'summary' => 'The admin dashboard takes 30+ seconds to load and sometimes times out entirely.',
                'ticket_content' => "Priority: High\nReporter: Priya Nair (Engineering Manager)\n\nOur admin dashboard has gotten unusably slow over the last two weeks — it used to load instantly, now it hangs for 30+ seconds and sometimes just times out with a 504. It's gotten worse as we've onboarded more customers. The team suspects it's database-related but nobody's had time to dig in.",
                'learning_outcomes' => 'Recognize an N+1 query pattern from a slow-query log and an offending code snippet, and connect it to a missing index as the underlying, compounding cause.',
                'difficulty' => 'hard',
                'estimated_minutes' => 35,
                'status' => CaseStatus::Draft,
                'model_solution_summary' => "The dashboard's summary widget loops over every customer and issues a separate query per customer to count their orders (a classic N+1), and that per-customer query has no index on orders.customer_id, so each one is a full table scan. At small scale this was invisible; at the current customer count it compounds into the reported multi-second load times. The fix is two-fold: eager-load the counts in one query, and add the missing index — either alone helps, both together fix it properly.",
                'allow_reattempt' => true,
            ]
        );

        $this->evidence($case, 'log', 'MySQL slow query log (excerpt)', 1, [
            'lines' => [
                ['timestamp' => '2026-07-29 08:41:02', 'level' => 'warn', 'text' => 'Query_time: 0.412  Lock_time: 0.000  Rows_examined: 184203  SELECT COUNT(*) FROM orders WHERE customer_id = 1'],
                ['timestamp' => '2026-07-29 08:41:03', 'level' => 'warn', 'text' => 'Query_time: 0.398  Lock_time: 0.000  Rows_examined: 184203  SELECT COUNT(*) FROM orders WHERE customer_id = 2'],
                ['timestamp' => '2026-07-29 08:41:03', 'level' => 'warn', 'text' => 'Query_time: 0.405  Lock_time: 0.000  Rows_examined: 184203  SELECT COUNT(*) FROM orders WHERE customer_id = 3'],
                ['timestamp' => '2026-07-29 08:41:34', 'level' => 'error', 'text' => '...412 similar queries omitted — dashboard request exceeded 30s and returned 504'],
            ],
        ]);

        $this->evidence($case, 'code_snippet', 'DashboardController@index (summary widget)', 2, [
            'filename' => 'app/Http/Controllers/DashboardController.php',
            'language' => 'php',
            'code' => "\$customers = Customer::all();\n\nforeach (\$customers as \$customer) {\n    \$customer->order_count = Order::where('customer_id', \$customer->id)->count();\n}\n\nreturn view('dashboard', compact('customers'));",
            'highlight_lines' => [3, 4, 5],
        ]);

        $this->evidence($case, 'db_snapshot', 'orders table — SHOW INDEX output', 3, [
            'table' => 'orders',
            'columns' => [['name' => 'Key_name', 'type' => 'varchar'], ['name' => 'Column_name', 'type' => 'varchar']],
            'rows' => [
                ['Key_name' => 'PRIMARY', 'Column_name' => 'id'],
            ],
        ]);

        Hint::updateOrCreate(
            ['case_id' => $case->id, 'order_index' => 0],
            ['content' => 'Count how many similar-looking queries appear in the slow query log around the same timestamp — what does that pattern suggest?', 'score_penalty' => 4],
        );
        Hint::updateOrCreate(
            ['case_id' => $case->id, 'order_index' => 1],
            ['content' => 'Check the orders table\'s indexes — is there one on the column every one of those repeated queries filters by?', 'score_penalty' => 5],
        );

        $evidenceIds = $case->evidenceItems()->pluck('id')->all();

        RubricCriterion::updateOrCreate(
            ['case_id' => $case->id, 'title' => 'Identifies the N+1 query pattern'],
            ['weight' => 20, 'matching_type' => 'keyword', 'expected_data' => ['keywords' => ['n+1', 'loop', 'per customer', 'index']]],
        );
        RubricCriterion::updateOrCreate(
            ['case_id' => $case->id, 'title' => 'Cites the slow query log and index snapshot as evidence'],
            ['weight' => 15, 'matching_type' => 'evidence_citation', 'expected_data' => ['required_evidence_ids' => $evidenceIds]],
        );
        RubricCriterion::updateOrCreate(
            ['case_id' => $case->id, 'title' => 'Proposes both the eager-load and the missing index'],
            ['weight' => 15, 'matching_type' => 'manual', 'expected_data' => []],
        );

        $this->publish($case);
    }

    private function category(string $name): int
    {
        return Category::firstOrCreate(['slug' => \Illuminate\Support\Str::slug($name)], ['name' => $name])->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function evidence(CaseModel $case, string $typeCode, string $title, int $sequenceOrder, array $payload): void
    {
        $type = EvidenceType::where('code', $typeCode)->firstOrFail();

        EvidenceItem::updateOrCreate(
            ['case_id' => $case->id, 'title' => $title],
            [
                'evidence_type_id' => $type->id,
                'sequence_order' => $sequenceOrder,
                'payload' => $payload,
            ],
        );
    }

    private function publish(CaseModel $case): void
    {
        $case->recalculateMaxScore();
        $case->update(['status' => CaseStatus::Published]);
    }
}
