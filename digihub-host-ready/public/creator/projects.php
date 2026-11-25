<?php
// digi-hub - Co-op projects: team up, agree the split, track shared earnings
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireCoopAccess();
$uid = (int)$_SESSION['user_id'];
$pageTitle = 'Co-op projects';
$errors = [];
$projectId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $projectId = (int)($_POST['project_id'] ?? 0);
        $res = ['success' => false, 'error' => 'Unknown action.'];

        if ($action === 'create') {
            $res = createProject($uid, $_POST['title'] ?? '', trim($_POST['description'] ?? ''));
            if ($res['success']) {
                setFlash('success', 'Project created. Invite your collaborators next.');
                redirect(SITE_URL . 'creator/projects.php?id=' . (int)$res['id']);
            }
        } elseif ($action === 'add_member') {
            $res = addProjectMember($projectId, $uid, $_POST['email'] ?? '', $_POST['role_label'] ?? '');
            $msg = 'Collaborator added with a 0% share. Set the split below.';
        } elseif ($action === 'save_splits') {
            $splits = is_array($_POST['split'] ?? null) ? $_POST['split'] : [];
            $res = setProjectSplits($projectId, $uid, $splits);
            $msg = 'Revenue split saved.';
        } elseif ($action === 'attach_product') {
            $res = attachProductToProject($projectId, $uid, (int)($_POST['product_id'] ?? 0));
            $msg = 'Product added. Its sales now follow the split.';
        }

        if ($res['success'] && $action !== 'create') {
            setFlash('success', $msg);
            redirect(SITE_URL . 'creator/projects.php?id=' . $projectId);
        }
        if (!$res['success']) {
            $errors[] = $res['error'];
        }
    }
}

$flash = getFlash();
$project = null;
if ($projectId > 0) {
    $project = getProjectForUser($projectId, $uid);
    if (!$project) {
        setFlash('error', 'Project not found.');
        redirect(SITE_URL . 'creator/projects.php');
    }
}

require __DIR__ . '/../partials/header.php';
?>

<?php if ($flash): ?><div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<?php if (!$project): ?>
    <?php $projects = getCreatorProjects($uid); ?>
    <div class="form-card card wide">
        <h2>Co-op projects</h2>
        <p class="subtitle">Team up with other creators on one project, agree the revenue split once, and every sale pays each collaborator automatically. Your co-op earnings so far: <strong><?= formatMoney(getCreatorCoopEarnings($uid)) ?></strong>.</p>

        <form method="POST" action="" data-loading>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="title">Project title</label>
                <input id="title" type="text" name="title" maxlength="150" required placeholder="For example: Ndomo, issue 2">
            </div>
            <div class="form-group">
                <label for="description">What are you making together? (optional)</label>
                <textarea id="description" name="description" rows="3"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Start a project</button>
                <a href="<?= SITE_URL ?>creator/dashboard.php" class="btn btn-outline">Back to dashboard</a>
            </div>
        </form>
    </div>

    <?php if ($projects): ?>
        <div class="card table-card" style="margin-top:1.5rem;">
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th>Project</th><th>Team</th><th>Your share</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($projects as $p): ?>
                        <tr>
                            <td><?= e($p['title']) ?></td>
                            <td><?= (int)$p['member_count'] ?></td>
                            <td><?= rtrim(rtrim(number_format((float)$p['my_split'], 2), '0'), '.') ?>%</td>
                            <td><a href="<?= SITE_URL ?>creator/projects.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <?php
    $members = getProjectMembers($project['id']);
    $products = getProjectProducts($project['id']);
    $ledger = getProjectLedger($project['id']);
    $attachable = $project['is_owner'] ? getAttachableProducts($uid) : [];
    $splitTotal = array_sum(array_map(fn($m) => (float)$m['split_percent'], $members));
    ?>
    <div class="form-card card wide">
        <h2><?= e($project['title']) ?></h2>
        <?php if (!empty($project['description'])): ?><p class="subtitle"><?= e($project['description']) ?></p><?php endif; ?>
        <p class="subtitle">Each sale pays <strong><?= (int)PLATFORM_FEE_PERCENT ?>%</strong> to digi-hub. The rest is shared by the split below.</p>

        <h3 style="margin-top:1.5rem;">Team and revenue split</h3>
        <form method="POST" action="" data-loading>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_splits">
            <input type="hidden" name="project_id" value="<?= (int)$project['id'] ?>">
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th>Creator</th><th>Role</th><th>Share (%)</th><th>Earned</th></tr></thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td><?= e($m['full_name']) ?></td>
                            <td><?= e($m['role_label']) ?></td>
                            <td>
                                <?php if ($project['is_owner']): ?>
                                    <input type="number" name="split[<?= (int)$m['user_id'] ?>]" min="0" max="100" step="0.01" value="<?= e($m['split_percent']) ?>" required style="max-width:7rem;" aria-label="Share for <?= e($m['full_name']) ?>">
                                <?php else: ?>
                                    <?= rtrim(rtrim(number_format((float)$m['split_percent'], 2), '0'), '.') ?>%
                                <?php endif; ?>
                            </td>
                            <td><?= formatMoney($m['earned']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="subtitle">Shares total <strong><?= rtrim(rtrim(number_format($splitTotal, 2), '0'), '.') ?>%</strong>. They must add up to exactly 100%.</p>
            <?php if ($project['is_owner']): ?>
                <div class="form-actions"><button type="submit" class="btn btn-primary">Save split</button></div>
            <?php endif; ?>
        </form>

        <?php if ($project['is_owner']): ?>
            <h3 style="margin-top:1.5rem;">Invite a collaborator</h3>
            <form method="POST" action="" data-loading>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_member">
                <input type="hidden" name="project_id" value="<?= (int)$project['id'] ?>">
                <div class="form-group">
                    <label for="email">Their digi-hub email</label>
                    <input id="email" type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="role_label">Role</label>
                    <input id="role_label" type="text" name="role_label" maxlength="60" required placeholder="Writer, Artist, Letterer...">
                </div>
                <div class="form-actions"><button type="submit" class="btn btn-outline">Add to project</button></div>
            </form>
        <?php endif; ?>

        <h3 style="margin-top:1.5rem;">Products in this project</h3>
        <?php if ($products): ?>
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th>Title</th><th>Price</th><th>Sales</th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $pr): ?>
                        <tr>
                            <td><a href="<?= SITE_URL ?>product.php?id=<?= (int)$pr['id'] ?>"><?= e($pr['title']) ?></a></td>
                            <td><?= $pr['is_free'] ? 'Free' : formatMoney($pr['price']) ?></td>
                            <td><?= (int)$pr['sales'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="subtitle">No products yet. Paid sales only split once a product is added.</p>
        <?php endif; ?>

        <?php if ($project['is_owner'] && $attachable): ?>
            <form method="POST" action="" data-loading style="margin-top:1rem;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="attach_product">
                <input type="hidden" name="project_id" value="<?= (int)$project['id'] ?>">
                <div class="form-group">
                    <label for="product_id">Add one of your products</label>
                    <select id="product_id" name="product_id" required>
                        <?php foreach ($attachable as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= e($a['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions"><button type="submit" class="btn btn-outline">Add product</button></div>
            </form>
        <?php endif; ?>

        <h3 style="margin-top:1.5rem;">Recent sales and payouts</h3>
        <?php if ($ledger): ?>
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th>Date</th><th>Product</th><th>Creator</th><th>Payout</th></tr></thead>
                    <tbody>
                    <?php foreach ($ledger as $row): ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($row['sold_at'])) ?></td>
                            <td><?= e($row['product_title']) ?></td>
                            <td><?= e($row['full_name']) ?></td>
                            <td><?= formatMoney($row['net_amount']) ?> (<?= e($row['payout_status']) ?>)</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="subtitle">Payouts are recorded automatically. Sending the money (mobile money or bank) is simulated in this version.</p>
        <?php else: ?>
            <p class="subtitle">No sales yet.</p>
        <?php endif; ?>

        <div class="form-actions" style="margin-top:1.5rem;">
            <a href="<?= SITE_URL ?>creator/projects.php" class="btn btn-outline">All projects</a>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
