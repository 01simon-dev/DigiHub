<?php
// digi-hub Co-op: projects, collaborators, revenue splits
// =======================================================
// A project groups collaborators (writer, artist, letterer...) with an agreed
// revenue split. Products attached to a project share every sale automatically.
// Payouts are recorded in a ledger (sale_splits); sending the money out
// (mobile money, bank) is a separate step and is simulated in this version.

function createProject($ownerId, $title, $description = '') {
    global $pdo;
    $title = trim($title);
    if ($title === '' || strlen($title) > 150) {
        return ['success' => false, 'error' => 'Enter a project title (up to 150 characters).'];
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO projects (owner_id, title, description) VALUES (?, ?, ?)");
        $stmt->execute([(int)$ownerId, $title, $description]);
        $id = (int)$pdo->lastInsertId();
        $m = $pdo->prepare("INSERT INTO project_members (project_id, user_id, role_label, split_percent) VALUES (?, ?, 'Project lead', 100)");
        $m->execute([$id, (int)$ownerId]);
        $pdo->commit();
        return ['success' => true, 'id' => $id];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'error' => 'The project could not be created. Please try again.'];
    }
}

function getCreatorProjects($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT p.*, pm.split_percent AS my_split,
            (SELECT COUNT(*) FROM project_members x WHERE x.project_id = p.id) AS member_count
        FROM projects p
        JOIN project_members pm ON pm.project_id = p.id
        WHERE pm.user_id = ?
        ORDER BY p.created_at DESC");
    $stmt->execute([(int)$userId]);
    return $stmt->fetchAll();
}

// Returns the project only if the user is a member of it.
function getProjectForUser($projectId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT p.*, pm.split_percent AS my_split
        FROM projects p
        JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
        WHERE p.id = ?");
    $stmt->execute([(int)$userId, (int)$projectId]);
    $project = $stmt->fetch();
    if ($project) {
        $project['is_owner'] = ((int)$project['owner_id'] === (int)$userId);
    }
    return $project ?: null;
}

function getProjectMembers($projectId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT pm.*, u.full_name, u.email,
            COALESCE((SELECT SUM(s.net_amount) FROM sale_splits s
                      WHERE s.project_id = pm.project_id AND s.user_id = pm.user_id), 0) AS earned
        FROM project_members pm
        JOIN users u ON u.id = pm.user_id
        WHERE pm.project_id = ?
        ORDER BY (pm.user_id = (SELECT owner_id FROM projects WHERE id = pm.project_id)) DESC, u.full_name");
    $stmt->execute([(int)$projectId]);
    return $stmt->fetchAll();
}

function addProjectMember($projectId, $ownerId, $email, $roleLabel) {
    global $pdo;
    $project = getProjectForUser($projectId, $ownerId);
    if (!$project || !$project['is_owner']) {
        return ['success' => false, 'error' => 'Only the project lead can invite collaborators.'];
    }
    $email = strtolower(trim($email));
    $roleLabel = trim($roleLabel);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Enter a valid email address.'];
    }
    if ($roleLabel === '' || strlen($roleLabel) > 60) {
        return ['success' => false, 'error' => 'Enter a role (for example Writer, Artist or Letterer).'];
    }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'creator' AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['success' => false, 'error' => 'No creator account uses that email yet. Ask them to join digi-hub first.'];
    }
    try {
        $ins = $pdo->prepare("INSERT INTO project_members (project_id, user_id, role_label, split_percent) VALUES (?, ?, ?, 0)");
        $ins->execute([(int)$projectId, (int)$user['id'], $roleLabel]);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'That creator is already in this project.'];
    }
    return ['success' => true];
}

// $splits: [user_id => percent]. Must cover every member and add up to exactly 100.
function setProjectSplits($projectId, $ownerId, array $splits) {
    global $pdo;
    $project = getProjectForUser($projectId, $ownerId);
    if (!$project || !$project['is_owner']) {
        return ['success' => false, 'error' => 'Only the project lead can change the split.'];
    }
    $members = getProjectMembers($projectId);
    $total = 0.0;
    $clean = [];
    foreach ($members as $m) {
        $raw = $splits[$m['user_id']] ?? null;
        if ($raw === null || !is_numeric($raw)) {
            return ['success' => false, 'error' => 'Enter a percentage for every collaborator.'];
        }
        $pct = round((float)$raw, 2);
        if ($pct < 0 || $pct > 100) {
            return ['success' => false, 'error' => 'Each share must be between 0 and 100.'];
        }
        $clean[(int)$m['user_id']] = $pct;
        $total += $pct;
    }
    if (abs($total - 100.0) > 0.001) {
        return ['success' => false, 'error' => 'The split adds up to ' . rtrim(rtrim(number_format($total, 2), '0'), '.') . '%. It must add up to exactly 100%.'];
    }
    $upd = $pdo->prepare("UPDATE project_members SET split_percent = ? WHERE project_id = ? AND user_id = ?");
    foreach ($clean as $userId => $pct) {
        $upd->execute([$pct, (int)$projectId, $userId]);
    }
    return ['success' => true];
}

function getProjectProducts($projectId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT pr.id, pr.title, pr.price, pr.is_free, pr.category, pr.status,
            (SELECT COUNT(*) FROM purchases pu WHERE pu.product_id = pr.id AND pu.payment_status = 'completed') AS sales
        FROM project_products pp
        JOIN products pr ON pr.id = pp.product_id
        WHERE pp.project_id = ?
        ORDER BY pp.added_at DESC");
    $stmt->execute([(int)$projectId]);
    return $stmt->fetchAll();
}

// Products the lead owns that are not yet in any project.
function getAttachableProducts($ownerId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, title, is_free, price FROM products
        WHERE creator_id = ? AND status = 'active'
          AND id NOT IN (SELECT product_id FROM project_products)
        ORDER BY title");
    $stmt->execute([(int)$ownerId]);
    return $stmt->fetchAll();
}

function attachProductToProject($projectId, $ownerId, $productId) {
    global $pdo;
    $project = getProjectForUser($projectId, $ownerId);
    if (!$project || !$project['is_owner']) {
        return ['success' => false, 'error' => 'Only the project lead can add products.'];
    }
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND creator_id = ? AND status = 'active'");
    $stmt->execute([(int)$productId, (int)$ownerId]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'error' => 'Choose one of your active products.'];
    }
    try {
        $ins = $pdo->prepare("INSERT INTO project_products (project_id, product_id) VALUES (?, ?)");
        $ins->execute([(int)$projectId, (int)$productId]);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'That product already belongs to a project.'];
    }
    return ['success' => true];
}

// Called after a verified sale. Splits the sale (minus the platform fee)
// between the project's collaborators. Safe to call twice for one purchase.
function recordSaleSplits($purchaseId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT pu.id, pu.amount, pp.project_id
            FROM purchases pu
            JOIN project_products pp ON pp.product_id = pu.product_id
            WHERE pu.id = ? AND pu.payment_status = 'completed'");
        $stmt->execute([(int)$purchaseId]);
        $sale = $stmt->fetch();
        if (!$sale) return false; // not a project product: nothing to split

        $exists = $pdo->prepare("SELECT COUNT(*) FROM sale_splits WHERE purchase_id = ?");
        $exists->execute([(int)$purchaseId]);
        if ((int)$exists->fetchColumn() > 0) return true;

        $gross = round((float)$sale['amount'], 2);
        $fee = round($gross * PLATFORM_FEE_PERCENT / 100, 2);
        $pool = round($gross - $fee, 2);

        $mem = $pdo->prepare("SELECT user_id, split_percent FROM project_members
            WHERE project_id = ? AND split_percent > 0 ORDER BY split_percent DESC, user_id");
        $mem->execute([(int)$sale['project_id']]);
        $members = $mem->fetchAll();
        if (!$members) return false;

        $shares = [];
        $assigned = 0.0;
        foreach ($members as $m) {
            $share = round($pool * (float)$m['split_percent'] / 100, 2);
            $shares[(int)$m['user_id']] = $share;
            $assigned += $share;
        }
        // Give any rounding difference to the largest share so the total is exact.
        $firstUser = (int)$members[0]['user_id'];
        $shares[$firstUser] = round($shares[$firstUser] + ($pool - $assigned), 2);

        $pdo->beginTransaction();
        $ins = $pdo->prepare("INSERT INTO sale_splits (purchase_id, project_id, user_id, gross_sale, platform_fee, net_amount)
            VALUES (?, ?, ?, ?, ?, ?)");
        $first = true;
        foreach ($shares as $userId => $net) {
            // Record the platform fee once per sale (on the first row), 0 on the rest.
            $ins->execute([(int)$purchaseId, (int)$sale['project_id'], $userId, $gross, $first ? $fee : 0, $net]);
            $first = false;
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('digi-hub split recording failed: ' . $e->getMessage());
        return false;
    }
}

function getProjectLedger($projectId, $limit = 25) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT s.*, u.full_name, pr.title AS product_title, pu.created_at AS sold_at
        FROM sale_splits s
        JOIN users u ON u.id = s.user_id
        JOIN purchases pu ON pu.id = s.purchase_id
        JOIN products pr ON pr.id = pu.product_id
        WHERE s.project_id = ?
        ORDER BY s.id DESC
        LIMIT " . (int)$limit);
    $stmt->execute([(int)$projectId]);
    return $stmt->fetchAll();
}

function getCreatorCoopEarnings($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM sale_splits WHERE user_id = ?");
    $stmt->execute([(int)$userId]);
    return (float)$stmt->fetchColumn();
}
