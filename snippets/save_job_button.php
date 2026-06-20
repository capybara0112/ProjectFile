<?php
// =============================================================================
// FILE: index.php  —  SAVE JOB BUTTON SNIPPET
// =============================================================================
// Insert this button inside every job card on the public jobs listing page
// and the job detail page.
//
// Where to add it in index.php (jobs list — inside the foreach job card):
//   After the salary/location meta, before the "Ứng tuyển" button.
//
// PREREQUISITE:
//   The query that loads $jobs must also fetch saved state for the current
//   candidate.  Add a subquery column to the SELECT (see below).
// =============================================================================

// ─────────────────────────────────────────────────────────────────────────────
//  STEP 1 — Add saved-state subquery to the jobs SELECT in index.php
//  Add this column to the existing $sql in CHANGE 7 of index_patch.php:
// ─────────────────────────────────────────────────────────────────────────────

// In the SELECT column list, append:
//   (CASE WHEN :uid_saved IS NOT NULL
//         THEN (SELECT COUNT(*) FROM saved_jobs sj2
//               WHERE sj2.user_id = :uid_saved AND sj2.job_id = j.id)
//         ELSE 0 END) AS is_saved

// Then bind the parameter:
//   $stmt->bindValue(':uid_saved', $user ? (int)$user['id'] : null,
//                   $user ? PDO::PARAM_INT : PDO::PARAM_NULL);

// Alternative (simpler — load all saved job IDs once before the loop):
$savedJobIds = [];
if ($user && ($user['role'] ?? '') === 'candidate') {
    $savedStmt = $pdo->prepare(
        'SELECT job_id FROM saved_jobs WHERE user_id = :uid'
    );
    $savedStmt->execute([':uid' => (int)$user['id']]);
    $savedJobIds = array_column($savedStmt->fetchAll(PDO::FETCH_ASSOC), 'job_id');
    $savedJobIds = array_map('intval', $savedJobIds);  // cast to int array
}


// ─────────────────────────────────────────────────────────────────────────────
//  STEP 2 — Button HTML (paste inside each job card in the foreach loop)
// ─────────────────────────────────────────────────────────────────────────────
?>

<?php
// Inside the jobs foreach loop:
// $isSaved = in_array((int)$j['id'], $savedJobIds, true);
// $user check ensures the button only shows for logged-in candidates
if ($user && ($user['role'] ?? '') === 'candidate'):
    $isSaved = in_array((int)$j['id'], $savedJobIds, true);
?>
    <form method="POST"
          action="<?= e(BASE_URL) ?>/candidate/index.php?page=saved_jobs"
          style="display:inline;">
        <input type="hidden" name="csrf"     value="<?= csrf_token() ?>">
        <input type="hidden" name="action"   value="toggle_save_job">
        <input type="hidden" name="job_id"   value="<?= (int)$j['id'] ?>">
        <!-- Redirect back to the current page after toggle -->
        <input type="hidden" name="redirect"
               value="<?= e(BASE_URL) ?>/?page=<?= e($_GET['page'] ?? 'jobs') ?>&id=<?= (int)($j['id'] ?? 0) ?>">
        <button type="submit"
                class="btn btn-sm <?= $isSaved ? 'btn-warning' : 'btn-outline-secondary' ?>"
                title="<?= $isSaved ? 'Bỏ lưu việc làm' : 'Lưu việc làm' ?>">
            <i class="fa-<?= $isSaved ? 'solid' : 'regular' ?> fa-bookmark me-1"></i>
            <?= $isSaved ? 'Đã lưu' : 'Lưu' ?>
        </button>
    </form>
<?php endif; ?>