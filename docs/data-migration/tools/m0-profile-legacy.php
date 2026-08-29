<?php
/**
 * M0 Legacy Database Profiling — READ ONLY
 * Run: php docs/data-migration/tools/m0-profile-legacy.php > docs/data-migration/tools/m0-profile-output.json
 */

declare(strict_types=1);

$legacyRoot = '/var/www/personal/finedge';
require $legacyRoot.'/vendor/autoload.php';
$app = require $legacyRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Loans;
use App\Models\Repayments;
use App\Services\LoanBalanceService;
use Illuminate\Support\Facades\DB;

$balanceService = app(LoanBalanceService::class);

$out = ['generated_at' => date('c')];

$scalar = fn (string $sql, array $b = []) => DB::selectOne($sql, $b);
$rows = fn (string $sql, array $b = []) => array_map(fn ($r) => (array) $r, DB::select($sql, $b));

$effectiveSql = "
    CASE
        WHEN (l.salary_based = 1 OR l.gvnt_loan = 1) THEN GREATEST(0, COALESCE(CAST(l.current_loan_amount AS DECIMAL(15,2)), 0))
        WHEN l.current_loan_amount IS NOT NULL AND l.current_loan_amount != '' THEN GREATEST(0, CAST(l.current_loan_amount AS DECIMAL(15,2)))
        ELSE GREATEST(0, COALESCE(CAST(l.loan_amount AS DECIMAL(15,2)), 0) - COALESCE(l.repaid_amount, 0))
    END
";

$affectedPopulated = "(affected_loan_ids IS NOT NULL AND affected_loan_ids NOT IN ('null','[]','') AND JSON_VALID(affected_loan_ids) AND JSON_LENGTH(affected_loan_ids) > 0)";
$affectedEmpty = "(affected_loan_ids IS NULL OR affected_loan_ids IN ('null','[]','') OR NOT JSON_VALID(affected_loan_ids) OR JSON_LENGTH(affected_loan_ids) = 0)";
$affectedPopulatedR = str_replace('affected_loan_ids', 'r.affected_loan_ids', $affectedPopulated);
$affectedEmptyR = str_replace('affected_loan_ids', 'r.affected_loan_ids', $affectedEmpty);

$mouLoan = "(l.salary_based = 1 OR l.gvnt_loan = 1 OR cl.product_type = 'salary_based')";
$mouLoanL2 = "(l2.salary_based = 1 OR l2.gvnt_loan = 1 OR cl2.product_type = 'salary_based')";
$characterLoan = "(cl.product_type = 'character_based' OR (COALESCE(l.salary_based,0)=0 AND COALESCE(l.gvnt_loan,0)=0 AND (cl.product_type IS NULL OR cl.product_type NOT IN ('salary_based','marketize'))))";
$marketizeLoan = "(cl.product_type = 'marketize' OR EXISTS (SELECT 1 FROM marketize_loan_schedules ms WHERE ms.loan_id = l.id))";

// Step 1
$out['database'] = $scalar('SELECT DATABASE() AS v')->v ?? null;
$out['version'] = $scalar('SELECT VERSION() AS v')->v ?? null;

// Step 2
foreach (['users', 'customers', 'clients', 'loans', 'repayments', 'loans_accounts', 'transactions', 'loan_refinances', 'loan_account_balance_adjustments', 'marketize_loan_schedules'] as $t) {
    $out['counts'][$t] = (int) $scalar("SELECT COUNT(*) AS c FROM `{$t}`")->c;
}

$out['loans_by_status'] = $rows('SELECT status_code, COUNT(*) AS cnt FROM loans GROUP BY status_code ORDER BY cnt DESC');
$out['loans_by_flags'] = $rows('SELECT salary_based, gvnt_loan, COUNT(*) AS cnt FROM loans GROUP BY salary_based, gvnt_loan ORDER BY cnt DESC');
$out['product_composition'] = $rows("
    SELECT
        COALESCE(cl.product_type, 'unknown') AS product_type,
        COUNT(*) AS loan_count,
        SUM(CASE WHEN l.status_code = '301' THEN 1 ELSE 0 END) AS active_loans,
        SUM(CASE WHEN l.status_code = '300' THEN 1 ELSE 0 END) AS settled_loans,
        SUM(CASE WHEN l.salary_based = 1 THEN 1 ELSE 0 END) AS salary_based_flag,
        SUM(CASE WHEN l.gvnt_loan = 1 THEN 1 ELSE 0 END) AS gvnt_flag
    FROM loans l
    LEFT JOIN clients cl ON cl.id = l.client_id
    GROUP BY COALESCE(cl.product_type, 'unknown')
    ORDER BY loan_count DESC
");

$out['active_loans'] = (int) $scalar("SELECT COUNT(*) AS c FROM loans WHERE status_code = '301'")->c;
$out['settled_loans'] = (int) $scalar("SELECT COUNT(*) AS c FROM loans WHERE status_code = '300'")->c;

// Step 3 identity
foreach ([
    'customers.nrc' => "SELECT nrc AS k, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id LIMIT 5) AS sample_ids FROM customers WHERE nrc IS NOT NULL AND nrc != '' GROUP BY nrc HAVING cnt > 1 ORDER BY cnt DESC LIMIT 20",
    'users.nrc' => "SELECT nrc AS k, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id LIMIT 5) AS sample_ids FROM users WHERE nrc IS NOT NULL AND nrc != '' GROUP BY nrc HAVING cnt > 1 ORDER BY cnt DESC LIMIT 20",
    'users.emp_number' => "SELECT emp_number AS k, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id LIMIT 5) AS sample_ids FROM users WHERE emp_number IS NOT NULL AND emp_number != '' GROUP BY emp_number HAVING cnt > 1 ORDER BY cnt DESC LIMIT 20",
    'users.phone_number' => "SELECT phone_number AS k, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id LIMIT 5) AS sample_ids FROM users WHERE phone_number IS NOT NULL AND phone_number != '' GROUP BY phone_number HAVING cnt > 1 ORDER BY cnt DESC LIMIT 20",
] as $label => $sql) {
    $out['duplicates'][$label] = $rows($sql);
    $out['duplicate_key_counts'][$label] = (int) $scalar('SELECT COUNT(*) AS c FROM ('.preg_replace('/LIMIT \d+$/', '', $sql).') d')->c;
}

$out['identity_gaps'] = [
    'customers_without_users' => (int) $scalar('SELECT COUNT(*) AS c FROM customers c LEFT JOIN users u ON u.id = c.user_id WHERE u.id IS NULL')->c,
    'users_with_loans_no_customer' => (int) $scalar('SELECT COUNT(DISTINCT l.user_id) AS c FROM loans l LEFT JOIN customers c ON c.user_id = l.user_id WHERE c.id IS NULL')->c,
    'customers_without_clients' => (int) $scalar('SELECT COUNT(*) AS c FROM customers c WHERE c.client_id IS NULL OR c.client_id = 0 OR c.client_id = ""')->c,
    'loans_missing_users' => (int) $scalar('SELECT COUNT(*) AS c FROM loans l LEFT JOIN users u ON u.id = l.user_id WHERE u.id IS NULL')->c,
];

// Step 4 multi-loan
$totalCustomersWithLoans = (int) $scalar('SELECT COUNT(DISTINCT user_id) AS c FROM loans')->c;
$historicalGt1 = (int) $scalar('SELECT COUNT(*) AS c FROM (SELECT user_id FROM loans GROUP BY user_id HAVING COUNT(*) > 1) t')->c;
$activeGt1 = (int) $scalar("SELECT COUNT(*) AS c FROM (SELECT user_id FROM loans WHERE status_code = '301' GROUP BY user_id HAVING COUNT(*) > 1) t")->c;

$out['multi_loan'] = [
    'historical_gt1_customers' => $historicalGt1,
    'historical_gt1_pct' => $totalCustomersWithLoans ? round(100 * $historicalGt1 / $totalCustomersWithLoans, 2) : 0,
    'active_gt1_customers' => $activeGt1,
    'active_gt1_pct' => $totalCustomersWithLoans ? round(100 * $activeGt1 / $totalCustomersWithLoans, 2) : 0,
    'total_customers_with_loans' => $totalCustomersWithLoans,
    'multi_active_same_product' => (int) $scalar("
        SELECT COUNT(*) AS c FROM (
            SELECT l.user_id, COALESCE(cl.product_type,'unknown') AS pt
            FROM loans l LEFT JOIN clients cl ON cl.id = l.client_id
            WHERE l.status_code = '301'
            GROUP BY l.user_id, COALESCE(cl.product_type,'unknown')
            HAVING COUNT(*) > 1
        ) t
    ")->c,
    'multi_active_different_product' => (int) $scalar("
        SELECT COUNT(*) AS c FROM (
            SELECT l.user_id
            FROM loans l LEFT JOIN clients cl ON cl.id = l.client_id
            WHERE l.status_code = '301'
            GROUP BY l.user_id
            HAVING COUNT(DISTINCT COALESCE(cl.product_type,'unknown')) > 1
        ) t
    ")->c,
    'refinance_customers' => (int) $scalar('SELECT COUNT(DISTINCT user_id) AS c FROM loan_refinances')->c,
];

$out['multi_loan_samples'] = $rows("
    SELECT l.user_id, u.fname, u.lname, GROUP_CONCAT(l.id ORDER BY l.id) AS loan_ids,
           GROUP_CONCAT(l.status_code ORDER BY l.id) AS status_codes,
           COUNT(*) AS loan_count
    FROM loans l
    JOIN users u ON u.id = l.user_id
    JOIN (
        SELECT user_id FROM loans WHERE status_code = '301'
        GROUP BY user_id HAVING COUNT(*) > 1
        LIMIT 10
    ) multi ON multi.user_id = l.user_id
    GROUP BY l.user_id, u.fname, u.lname
    LIMIT 10
");

// Step 5 repayments
$out['repayments_by_status'] = $rows("
    SELECT status_code, COUNT(*) AS cnt,
           SUM(CAST(repayment_amount AS DECIMAL(15,2))) AS total_amount
    FROM repayments GROUP BY status_code ORDER BY cnt DESC
");

$successful = (int) $scalar("SELECT COUNT(*) AS c FROM repayments WHERE status_code = 215")->c;
$withAffected = (int) $scalar("SELECT COUNT(*) AS c FROM repayments WHERE status_code = 215 AND {$affectedPopulated}")->c;
$withoutAffected = (int) $scalar("SELECT COUNT(*) AS c FROM repayments WHERE status_code = 215 AND {$affectedEmpty}")->c;

$out['repayments_attribution'] = [
    'successful' => $successful,
    'with_affected_loan_ids' => $withAffected,
    'without_affected_loan_ids' => $withoutAffected,
    'pct_with_affected' => $successful ? round(100 * $withAffected / $successful, 2) : 0,
    'with_pi_split' => (int) $scalar('SELECT COUNT(*) AS c FROM repayments WHERE status_code = 215 AND principal_amount > 0')->c,
    'waivers' => (int) $scalar('SELECT COUNT(*) AS c FROM repayments WHERE is_waiver = 1')->c,
    'first_affected_repayment_at' => $scalar("SELECT MIN(created_at) AS v FROM repayments WHERE {$affectedPopulated}")->v ?? null,
];

$out['repayments_by_year'] = $rows("
    SELECT YEAR(created_at) AS yr,
        COUNT(*) AS total,
        SUM(CASE WHEN status_code = 215 THEN 1 ELSE 0 END) AS successful,
        SUM(CASE WHEN status_code = 215 AND {$affectedPopulated} THEN 1 ELSE 0 END) AS with_affected,
        SUM(CASE WHEN status_code = 215 AND {$affectedEmpty} THEN 1 ELSE 0 END) AS without_affected,
        ROUND(100 * SUM(CASE WHEN status_code = 215 AND {$affectedPopulated} THEN 1 ELSE 0 END) / NULLIF(SUM(CASE WHEN status_code = 215 THEN 1 ELSE 0 END),0), 2) AS pct_attributable
    FROM repayments
    WHERE created_at IS NOT NULL
    GROUP BY YEAR(created_at)
    ORDER BY yr
");

// Step 6 ambiguous MOU repayments
$out['ambiguous_mou'] = (array) $scalar("
    SELECT
        COUNT(DISTINCT r.user_id) AS customer_count,
        COUNT(*) AS repayment_count,
        SUM(CAST(r.repayment_amount AS DECIMAL(15,2))) AS total_repayment_amount
    FROM repayments r
    JOIN (
        SELECT l.user_id FROM loans l
        LEFT JOIN clients cl ON cl.id = l.client_id
        WHERE l.status_code = '301' AND {$mouLoan}
        GROUP BY l.user_id
        HAVING COUNT(*) > 1
    ) multi ON multi.user_id = r.user_id
    WHERE r.status_code = 215 AND {$affectedEmptyR}
");

$out['ambiguous_mou_samples'] = $rows("
    SELECT r.user_id, u.fname, u.lname,
           COUNT(*) AS ambiguous_repayment_count,
           SUM(CAST(r.repayment_amount AS DECIMAL(15,2))) AS total_amount,
           MIN(r.created_at) AS first_repayment,
           MAX(r.created_at) AS last_repayment
    FROM repayments r
    JOIN users u ON u.id = r.user_id
    JOIN (
        SELECT l.user_id FROM loans l
        LEFT JOIN clients cl ON cl.id = l.client_id
        WHERE l.status_code = '301' AND {$mouLoan}
        GROUP BY l.user_id HAVING COUNT(*) > 1
    ) multi ON multi.user_id = r.user_id
    WHERE r.status_code = 215 AND {$affectedEmptyR}
    GROUP BY r.user_id, u.fname, u.lname
    ORDER BY total_amount DESC
    LIMIT 8
");

// Step 10 account balance reconciliation
$reconRows = $rows("
    SELECT
        la.customer_id AS user_id,
        CAST(la.balance AS DECIMAL(15,2)) AS account_balance,
        COALESCE(SUM(CASE WHEN l.status_code = '301' THEN {$effectiveSql} ELSE 0 END), 0) AS sum_active_effective,
        ABS(CAST(la.balance AS DECIMAL(15,2)) - COALESCE(SUM(CASE WHEN l.status_code = '301' THEN {$effectiveSql} ELSE 0 END), 0)) AS variance
    FROM loans_accounts la
    LEFT JOIN loans l ON l.user_id = la.customer_id
    LEFT JOIN clients cl ON cl.id = l.client_id
    GROUP BY la.customer_id, la.balance
");

$buckets = [
    'exact_match' => 0,
    'lt_0_01' => 0,
    'lte_1' => 0,
    'lte_10' => 0,
    'lte_100' => 0,
    'gt_100' => 0,
];
$totalAbsVariance = 0.0;
$maxVariance = ['user_id' => null, 'variance' => 0.0];

foreach ($reconRows as $row) {
    $v = (float) $row['variance'];
    $totalAbsVariance += $v;
    if ($v > $maxVariance['variance']) {
        $maxVariance = ['user_id' => $row['user_id'], 'variance' => $v, 'account_balance' => $row['account_balance'], 'sum_active_effective' => $row['sum_active_effective']];
    }
    if ($v == 0) {
        $buckets['exact_match']++;
    } elseif ($v < 0.01) {
        $buckets['lt_0_01']++;
    } elseif ($v <= 1) {
        $buckets['lte_1']++;
    } elseif ($v <= 10) {
        $buckets['lte_10']++;
    } elseif ($v <= 100) {
        $buckets['lte_100']++;
    } else {
        $buckets['gt_100']++;
    }
}

$out['account_reconciliation'] = [
    'customers_with_accounts' => count($reconRows),
    'buckets' => $buckets,
    'total_absolute_variance' => round($totalAbsVariance, 2),
    'largest_variance' => $maxVariance,
    'top_variances' => array_slice(array_values(array_filter($reconRows, fn ($r) => (float) $r['variance'] > 0)), 0, 10),
];

usort($reconRows, fn ($a, $b) => (float) $b['variance'] <=> (float) $a['variance']);
$out['account_reconciliation']['top_variances'] = array_slice($reconRows, 0, 10);

// Step 11 balance adjustments
$out['balance_adjustments'] = (array) $scalar('
    SELECT COUNT(*) AS adjustment_count,
           COUNT(DISTINCT customer_id) AS customers_affected,
           SUM(CASE WHEN adjustment_amount > 0 THEN adjustment_amount ELSE 0 END) AS total_positive,
           SUM(CASE WHEN adjustment_amount < 0 THEN adjustment_amount ELSE 0 END) AS total_negative
    FROM loan_account_balance_adjustments
');
$out['balance_adjustment_samples'] = $rows('SELECT id, customer_id, old_balance, new_balance, adjustment_amount, LEFT(reason,120) AS reason, created_at FROM loan_account_balance_adjustments ORDER BY ABS(adjustment_amount) DESC LIMIT 10');

// Step 12 refinances
$out['refinances'] = [
    'count' => (int) $scalar('SELECT COUNT(*) AS c FROM loan_refinances')->c,
    'broken_chains' => (array) $scalar("
        SELECT
            SUM(CASE WHEN ol.id IS NULL THEN 1 ELSE 0 END) AS missing_old_loan,
            SUM(CASE WHEN nl.id IS NULL THEN 1 ELSE 0 END) AS missing_new_loan,
            SUM(CASE WHEN ol.user_id != lr.user_id OR nl.user_id != lr.user_id THEN 1 ELSE 0 END) AS customer_mismatch,
            SUM(CASE WHEN ol.status_code = '301' THEN 1 ELSE 0 END) AS old_loan_still_active,
            COUNT(*) AS total
        FROM loan_refinances lr
        LEFT JOIN loans ol ON ol.id = lr.refinanced_loan_id
        LEFT JOIN loans nl ON nl.id = lr.new_loan_id
    "),
];
$out['refinance_samples'] = $rows("
    SELECT lr.id, lr.user_id, lr.refinanced_loan_id, lr.new_loan_id,
           lr.refinanced_amount, lr.recieved_amount, ol.status_code AS old_status, nl.status_code AS new_status
    FROM loan_refinances lr
    LEFT JOIN loans ol ON ol.id = lr.refinanced_loan_id
    LEFT JOIN loans nl ON nl.id = lr.new_loan_id
    ORDER BY lr.id DESC LIMIT 10
");

// Step 13 data quality exceptions
$exceptions = [];
$addEx = function (string $key, string $sql, string $classification) use (&$exceptions, $scalar) {
    $cnt = (int) $scalar($sql)->c;
    $exceptions[$key] = ['count' => $cnt, 'classification' => $classification];
};
$addEx('loans_missing_users', 'SELECT COUNT(*) AS c FROM loans l LEFT JOIN users u ON u.id = l.user_id WHERE u.id IS NULL', 'BLOCKING');
$addEx('customers_missing_users', 'SELECT COUNT(*) AS c FROM customers c LEFT JOIN users u ON u.id = c.user_id WHERE u.id IS NULL', 'MANUAL REVIEW');
$addEx('customers_without_clients', 'SELECT COUNT(*) AS c FROM customers c WHERE c.client_id IS NULL OR c.client_id = 0 OR c.client_id = ""', 'REQUIRES RULE');
$addEx('repayments_without_users', 'SELECT COUNT(*) AS c FROM repayments r LEFT JOIN users u ON u.id = r.user_id WHERE u.id IS NULL', 'BLOCKING');
$addEx('successful_zero_or_negative_amount', "SELECT COUNT(*) AS c FROM repayments WHERE status_code = 215 AND CAST(repayment_amount AS DECIMAL(15,2)) <= 0", 'MANUAL REVIEW');
$addEx('duplicate_repayment_references', "SELECT COUNT(*) AS c FROM (SELECT client_transaction_reference FROM repayments WHERE client_transaction_reference IS NOT NULL AND client_transaction_reference != '' GROUP BY client_transaction_reference HAVING COUNT(*) > 1) t", 'MANUAL REVIEW');
$addEx('repayment_before_loan_creation', "
    SELECT COUNT(*) AS c FROM repayments r
    JOIN loans l ON l.user_id = r.user_id
    WHERE r.status_code = 215 AND r.created_at < l.created_at
", 'MANUAL REVIEW');
$addEx('settled_with_positive_effective', "
    SELECT COUNT(*) AS c FROM loans l
    LEFT JOIN clients cl ON cl.id = l.client_id
    WHERE l.status_code = '300' AND ({$effectiveSql}) > 0.01
", 'REQUIRES RULE');
$addEx('active_with_zero_balance', "
    SELECT COUNT(*) AS c FROM loans l
    LEFT JOIN clients cl ON cl.id = l.client_id
    WHERE l.status_code = '301' AND ({$effectiveSql}) <= 0.01
", 'REQUIRES RULE');
$addEx('negative_effective_balance', "
    SELECT COUNT(*) AS c FROM loans l LEFT JOIN clients cl ON cl.id = l.client_id WHERE ({$effectiveSql}) < -0.01
", 'MANUAL REVIEW');
$addEx('repaid_exceeds_loan_amount', "
    SELECT COUNT(*) AS c FROM loans WHERE repaid_amount IS NOT NULL AND CAST(loan_amount AS DECIMAL(15,2)) > 0 AND repaid_amount > CAST(loan_amount AS DECIMAL(15,2)) + 0.01
", 'MANUAL REVIEW');
$addEx('loans_missing_client', 'SELECT COUNT(*) AS c FROM loans l LEFT JOIN clients cl ON cl.id = l.client_id WHERE cl.id IS NULL', 'REQUIRES RULE');
$addEx('invalid_affected_loan_ids_json', "SELECT COUNT(*) AS c FROM repayments WHERE affected_loan_ids IS NOT NULL AND affected_loan_ids NOT IN ('null','[]','') AND NOT JSON_VALID(affected_loan_ids)", 'MANUAL REVIEW');
$addEx('affected_refs_nonexistent_loan', "
    SELECT COUNT(*) AS c FROM repayments r
    WHERE {$affectedPopulatedR}
      AND EXISTS (
        SELECT 1 FROM JSON_TABLE(r.affected_loan_ids, '\$[*]' COLUMNS(loan_id BIGINT PATH '\$.loan_id')) jt
        LEFT JOIN loans l ON l.id = jt.loan_id
        WHERE l.id IS NULL
      )
", 'MANUAL REVIEW');
$addEx('loans_accounts_without_users', 'SELECT COUNT(*) AS c FROM loans_accounts la LEFT JOIN users u ON u.id = la.customer_id WHERE u.id IS NULL', 'BLOCKING');
$addEx('users_multiple_loans_accounts', 'SELECT COUNT(*) AS c FROM (SELECT customer_id FROM loans_accounts GROUP BY customer_id HAVING COUNT(*) > 1) t', 'MANUAL REVIEW');

$out['exceptions'] = $exceptions;

// Step 14 product population
$out['product_population'] = $rows("
    SELECT
        CASE
            WHEN {$mouLoan} THEN 'MOU/Salary (accrual)'
            WHEN {$marketizeLoan} THEN 'Marketize'
            WHEN cl.product_type = 'character_based' THEN 'Character'
            WHEN cl.product_type IS NOT NULL THEN CONCAT('Other: ', cl.product_type)
            ELSE 'Unclassified fixed'
        END AS legacy_product,
        COUNT(DISTINCT l.user_id) AS customers,
        COUNT(*) AS loans,
        SUM(CASE WHEN l.status_code = '301' THEN 1 ELSE 0 END) AS active_loans,
        SUM(CASE WHEN l.status_code = '301' THEN {$effectiveSql} ELSE 0 END) AS active_outstanding,
        SUM(COALESCE(l.repaid_amount,0)) AS total_repaid,
        SUM(CAST(l.loan_amount AS DECIMAL(15,2))) AS total_loan_amount
    FROM loans l
    LEFT JOIN clients cl ON cl.id = l.client_id
    GROUP BY legacy_product
    ORDER BY loans DESC
");

// Step 15 portfolio totals
$out['portfolio_totals'] = $rows("
    SELECT
        CASE
            WHEN {$mouLoan} THEN 'MOU/Salary'
            WHEN {$marketizeLoan} THEN 'Marketize'
            WHEN cl.product_type = 'character_based' THEN 'Character'
            ELSE COALESCE(cl.product_type,'Unclassified')
        END AS product,
        l.status_code,
        COUNT(*) AS loan_count,
        SUM(CAST(l.obtained_amount AS DECIMAL(15,2))) AS original_principal,
        SUM(CAST(l.loan_amount AS DECIMAL(15,2))) AS loan_obligation,
        SUM(COALESCE(l.repaid_amount,0)) AS repaid,
        SUM({$effectiveSql}) AS effective_outstanding
    FROM loans l
    LEFT JOIN clients cl ON cl.id = l.client_id
    GROUP BY product, l.status_code
    ORDER BY product, l.status_code
");

$out['loans_accounts_total_balance'] = (float) $scalar('SELECT SUM(CAST(balance AS DECIMAL(15,2))) AS v FROM loans_accounts')->v;

// Step 7 character reconstruction (5 samples via PHP replay)
function replayCharacter(int $userId, LoanBalanceService $svc): array
{
    $repayments = Repayments::where('user_id', $userId)->where('status_code', 215)->orderBy('created_at')->get();
    $loans = Loans::where('user_id', $userId)->orderBy('id')->get()->keyBy('id');
    $sim = [];
    foreach ($loans as $l) {
        $sim[$l->id] = ['repaid' => 0.0, 'status' => $l->status_code, 'loan_amount' => (float) $l->loan_amount];
    }
    $allocations = [];
    foreach ($repayments as $r) {
        if (! empty($r->affected_loan_ids) && is_array($r->affected_loan_ids)) {
            foreach ($r->affected_loan_ids as $item) {
                $lid = (int) ($item['loan_id'] ?? 0);
                $amt = (float) ($item['amount_applied'] ?? 0);
                if ($lid && isset($sim[$lid])) {
                    $sim[$lid]['repaid'] += $amt;
                    $allocations[] = ['repayment_id' => $r->id, 'loan_id' => $lid, 'amount' => $amt, 'source' => 'affected_loan_ids'];
                }
            }
            continue;
        }
        // waterfall replay
        $active = collect($sim)->filter(fn ($s, $id) => $s['status'] === '301' || $s['status'] === 301)->sortBy(fn ($s, $id) => $loans[$id]->due_date ?? '');
        $remaining = (float) $r->repayment_amount;
        foreach ($active as $lid => $s) {
            if ($remaining <= 0) {
                break;
            }
            $bal = max(0, $s['loan_amount'] - $s['repaid']);
            $apply = min($remaining, $bal);
            if ($apply <= 0) {
                continue;
            }
            $sim[$lid]['repaid'] += $apply;
            if ($sim[$lid]['repaid'] >= $sim[$lid]['loan_amount'] - 0.01) {
                $sim[$lid]['status'] = '300';
            }
            $remaining -= $apply;
            $allocations[] = ['repayment_id' => $r->id, 'loan_id' => $lid, 'amount' => $apply, 'source' => 'waterfall_replay'];
        }
    }
    $mismatches = [];
    foreach ($loans as $l) {
        $expectedRepaid = (float) ($l->repaid_amount ?? 0);
        $reconstructed = $sim[$l->id]['repaid'];
        $effective = $svc->getEffectiveOutstandingBalance($l);
        if (abs($expectedRepaid - $reconstructed) > 0.05) {
            $mismatches[] = ['loan_id' => $l->id, 'stored_repaid' => $expectedRepaid, 'reconstructed_repaid' => $reconstructed, 'effective' => $effective, 'status' => $l->status_code];
        }
    }

    return ['user_id' => $userId, 'loan_count' => $loans->count(), 'repayment_count' => $repayments->count(), 'mismatches' => $mismatches, 'allocations_sample' => array_slice($allocations, 0, 5)];
}

$charSamples = $rows("
    SELECT l.user_id, COUNT(*) AS loan_count,
           SUM(CASE WHEN l.status_code='301' THEN 1 ELSE 0 END) AS active_count,
           SUM(CASE WHEN l.status_code='300' THEN 1 ELSE 0 END) AS settled_count,
           MAX(CASE WHEN cl.product_type='character_based' THEN 1 ELSE 0 END) AS is_character
    FROM loans l
    JOIN clients cl ON cl.id = l.client_id
    WHERE cl.product_type = 'character_based'
    GROUP BY l.user_id
    HAVING loan_count >= 1
    ORDER BY loan_count DESC
    LIMIT 30
");

$out['character_reconstruction'] = [];
$picked = [];
foreach ($charSamples as $s) {
    if (count($picked) >= 5) {
        break;
    }
    $uid = (int) $s['user_id'];
    if (isset($picked[$uid])) {
        continue;
    }
    $picked[$uid] = true;
    $out['character_reconstruction'][] = replayCharacter($uid, $balanceService);
}

// Step 8 marketize reconstruction
$out['marketize_reconstruction'] = $rows("
    SELECT l.id AS loan_id, l.user_id, l.loan_amount, l.repaid_amount, l.status_code,
           ({$effectiveSql}) AS effective_outstanding,
           COALESCE(ms.schedules,0) AS schedule_rows,
           COALESCE(ms.paid_schedules,0) AS paid_schedules,
           COALESCE(ms.sum_weekly,0) AS sum_weekly,
           COALESCE(ms.sum_paid,0) AS sum_paid_amount
    FROM loans l
    JOIN clients cl ON cl.id = l.client_id
    LEFT JOIN (
        SELECT loan_id, COUNT(*) AS schedules,
               SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_schedules,
               SUM(CAST(weekly_amount AS DECIMAL(15,2))) AS sum_weekly,
               SUM(COALESCE(paid_amount,0)) AS sum_paid
        FROM marketize_loan_schedules GROUP BY loan_id
    ) ms ON ms.loan_id = l.id
    WHERE cl.product_type = 'marketize' OR ms.loan_id IS NOT NULL
    ORDER BY l.id DESC LIMIT 8
");

// Step 9 MOU balance samples
$out['mou_balance_samples'] = $rows("
    SELECT l.id, l.user_id, l.status_code,
           CAST(l.obtained_amount AS DECIMAL(15,2)) AS obtained_amount,
           CAST(l.loan_amount AS DECIMAL(15,2)) AS loan_amount,
           CAST(l.principle_amount AS DECIMAL(15,2)) AS principle_amount,
           CAST(l.accrued_interest AS DECIMAL(15,2)) AS accrued_interest,
           CAST(l.current_loan_amount AS DECIMAL(15,2)) AS current_loan_amount,
           COALESCE(l.repaid_amount,0) AS repaid_amount,
           ({$effectiveSql}) AS effective_outstanding,
           CAST(la.balance AS DECIMAL(15,2)) AS account_balance
    FROM loans l
    LEFT JOIN clients cl ON cl.id = l.client_id
    LEFT JOIN loans_accounts la ON la.customer_id = l.user_id
    WHERE {$mouLoan}
    ORDER BY l.id DESC LIMIT 12
");

// Step 16 pilot loans (heuristic selection)
$pilotQueries = [
    'simple_mou' => "SELECT l.id FROM loans l JOIN clients cl ON cl.id=l.client_id JOIN (SELECT user_id FROM loans GROUP BY user_id HAVING COUNT(*)=1) single ON single.user_id=l.user_id WHERE {$mouLoan} AND l.status_code='301' LIMIT 3",
    'mou_with_repayments' => "SELECT DISTINCT l.id FROM loans l JOIN clients cl ON cl.id=l.client_id JOIN repayments r ON r.user_id=l.user_id WHERE {$mouLoan} AND r.status_code=215 LIMIT 3",
    'mou_multi' => "SELECT l.id FROM loans l LEFT JOIN clients cl ON cl.id=l.client_id JOIN (SELECT l2.user_id FROM loans l2 LEFT JOIN clients cl2 ON cl2.id=l2.client_id WHERE l2.status_code='301' AND (l2.salary_based=1 OR l2.gvnt_loan=1 OR cl2.product_type='salary_based') GROUP BY l2.user_id HAVING COUNT(*)>1) multi ON multi.user_id=l.user_id WHERE {$mouLoan} AND l.status_code='301' LIMIT 4",
    'character' => "SELECT l.id FROM loans l JOIN clients cl ON cl.id=l.client_id WHERE cl.product_type='character_based' LIMIT 5",
    'marketize' => "SELECT l.id FROM loans l JOIN clients cl ON cl.id=l.client_id WHERE cl.product_type='marketize' LIMIT 2",
    'settled' => "SELECT id FROM loans WHERE status_code='300' LIMIT 2",
    'refinanced' => "SELECT new_loan_id AS id FROM loan_refinances LIMIT 1",
    'adjustment' => "SELECT l.id FROM loans l JOIN loan_account_balance_adjustments a ON a.customer_id=l.user_id LIMIT 1",
];

$pilotIds = [];
foreach ($pilotQueries as $cat => $sql) {
    foreach ($rows($sql) as $r) {
        $pilotIds[(int) $r['id']] = $cat;
    }
}

$pilot = [];
foreach (array_keys($pilotIds) as $lid) {
    if (count($pilot) >= 20) {
        break;
    }
    $loan = Loans::find($lid);
    if (! $loan) {
        continue;
    }
    $repCount = (int) $scalar('SELECT COUNT(*) AS c FROM repayments WHERE user_id = ? AND status_code = 215', [$loan->user_id])->c;
    $withAff = (int) $scalar("SELECT COUNT(*) AS c FROM repayments WHERE user_id = ? AND status_code = 215 AND {$affectedPopulated}", [$loan->user_id])->c;
    $client = DB::table('clients')->where('id', $loan->client_id)->first();
    $effective = $balanceService->getEffectiveOutstandingBalance($loan);
    $confidence = 'HIGH';
    if ($loan->salary_based || $loan->gvnt_loan) {
        $multi = (int) $scalar("SELECT COUNT(*) AS c FROM loans WHERE user_id = ? AND status_code='301'", [$loan->user_id])->c;
        if ($multi > 1 && $withAff < $repCount) {
            $confidence = 'LOW';
        } elseif ($multi > 1) {
            $confidence = 'MEDIUM';
        }
    }
    if ($pilotIds[$lid] === 'adjustment') {
        $confidence = 'MANUAL REVIEW';
    }
    $pilot[] = [
        'legacy_loan_id' => $loan->id,
        'user_id' => $loan->user_id,
        'category' => $pilotIds[$lid],
        'product' => $client->product_type ?? 'unknown',
        'status_code' => $loan->status_code,
        'principal' => $loan->loan_amount,
        'effective_outstanding' => $effective,
        'repayment_count' => $repCount,
        'repayments_with_affected' => $withAff,
        'migration_confidence' => $confidence,
    ];
}

$out['pilot_loans'] = $pilot;

// Ambiguous exposure on active portfolio
$activeOutstanding = (float) $scalar("SELECT SUM({$effectiveSql}) AS v FROM loans l LEFT JOIN clients cl ON cl.id = l.client_id WHERE l.status_code='301'")->v;
$ambiguousActiveUsers = $rows("
    SELECT l.user_id, SUM({$effectiveSql}) AS exposure
    FROM loans l
    LEFT JOIN clients cl ON cl.id = l.client_id
    JOIN (
        SELECT l2.user_id FROM loans l2
        LEFT JOIN clients cl2 ON cl2.id = l2.client_id
        WHERE l2.status_code = '301' AND {$mouLoanL2}
        GROUP BY l2.user_id HAVING COUNT(*) > 1
    ) multi ON multi.user_id = l.user_id
    WHERE l.status_code = '301' AND {$mouLoan}
    GROUP BY l.user_id
");
$ambiguousExposure = array_sum(array_map(fn ($r) => (float) $r['exposure'], $ambiguousActiveUsers));

$out['risk_metrics'] = [
    'pct_repayments_auto_attributable' => $out['repayments_attribution']['pct_with_affected'],
    'pct_repayments_requiring_reconstruction' => $successful ? round(100 * $withoutAffected / $successful, 2) : 0,
    'ambiguous_mou_repayment_count' => (int) ($out['ambiguous_mou']['repayment_count'] ?? 0),
    'ambiguous_mou_repayment_value' => (float) ($out['ambiguous_mou']['total_repayment_amount'] ?? 0),
    'active_outstanding_total' => round($activeOutstanding, 2),
    'ambiguous_active_exposure' => round($ambiguousExposure, 2),
    'pct_active_outstanding_ambiguous' => $activeOutstanding > 0 ? round(100 * $ambiguousExposure / $activeOutstanding, 2) : 0,
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
