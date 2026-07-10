<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('user.manage');
$message = null;
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
    try {
        $claimId = (int)($_POST['claim_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $users->reviewProfileClaim($claimId, $status, (int)$currentUser['id']);
        $audit->log('profile.claim_' . $status, (int)$currentUser['id'], 'profile_claim', (string)$claimId);
        $message = 'Claim request ' . $status . '.';
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$claims = $users->profileClaims();
renderHeader('Profile claims', $currentUser);
?>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?><?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<section class="panel"><h2>Claim requests</h2><p class="muted">Approving records the claimant against the organization profile and closes future claim submissions. Review identity evidence outside this application before approving.</p><?php if ($claims === []): ?><p class="muted">No claim requests.</p><?php else: ?><table><thead><tr><th>Profile</th><th>Claimant</th><th>Message</th><th>Status</th><th>Requested</th><th>Review</th></tr></thead><tbody><?php foreach ($claims as $claim): ?><tr><td><a href="/user/<?= e($claim['profile_slug']) ?>"><?= e($claim['profile_name']) ?></a></td><td><?= e($claim['claimant_name']) ?></td><td><?= e($claim['message']) ?></td><td><?= e($claim['status']) ?></td><td><?= e($claim['created_at']) ?></td><td><?php if ($claim['status'] === 'pending'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="claim_id" value="<?= (int)$claim['id'] ?>"><button name="status" value="approved" data-confirm="Approve this profile claim?">Approve</button><button name="status" value="rejected" class="danger">Reject</button></form><?php else: ?><?= e($claim['reviewer_name'] ?? '') ?><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<?php renderFooter();
