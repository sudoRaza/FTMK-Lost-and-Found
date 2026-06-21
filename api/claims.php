<?php
// ============================================================
// api/claims.php — CLAIM_REQUEST endpoints
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mock_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') jsonResponse(['ok' => true]);

$action = $_GET['action'] ?? '';

function requireUser(): array {
    if (!isset($_SESSION['ftmk_user']))
        jsonResponse(['ok' => false, 'error' => 'Not logged in.'], 401);
    return $_SESSION['ftmk_user'];
}

function enrichClaimDB(array $claim): array {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT ip.title, ip.type, ip.user_id AS post_owner,
                u.name, u.initials, u.avatar_color
         FROM item_post ip
         JOIN users u ON u.user_id = ?
         WHERE ip.post_id = ?'
    );
    $stmt->execute([$claim['user_id'], $claim['post_id']]);
    $row = $stmt->fetch();
    if ($row) {
        $claim['post_title']      = $row['title'];
        $claim['post_type']       = $row['type'];
        $claim['post_owner']      = $row['post_owner'];
        $claim['claimant_name']   = $row['name'];
        $claim['claimant_avatar'] = $row['initials'];
        $claim['claimant_color']  = $row['avatar_color'];
    }
    return $claim;
}

function enrichClaimMock(array $claim): array {
    foreach (mockGetPosts() as $p) {
        if ($p['id'] === $claim['post_id']) {
            $claim['post_title'] = $p['title'];
            $claim['post_type']  = $p['type'];
            $claim['post_owner'] = $p['posterId'];
            break;
        }
    }
    $claimant = mockFindUser($claim['user_id']);
    $claim['claimant_name']   = $claimant['name']        ?? $claim['user_id'];
    $claim['claimant_avatar'] = $claimant['initials']    ?? substr($claim['user_id'], 0, 2);
    $claim['claimant_color']  = $claimant['avatarColor'] ?? 'blue';
    return $claim;
}

function enrichClaim(array $claim): array {
    return USE_MOCK_DB ? enrichClaimMock($claim) : enrichClaimDB($claim);
}

// ------ SUBMIT CLAIM --------------------------------------------------------
if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user    = requireUser();
    $post_id = (int)($_GET['post_id'] ?? 0);
    $body    = getRequestBody();
    $message = trim($body['message'] ?? '');

    if (!$post_id) jsonResponse(['ok' => false, 'error' => 'Missing post_id.'], 400);
    if (!$message) jsonResponse(['ok' => false, 'error' => 'Please write a message explaining your claim.'], 400);

    if (USE_MOCK_DB) {
        $post = null;
        foreach (mockGetPosts() as $p) { if ($p['id'] === $post_id) { $post = $p; break; } }
        if (!$post) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
        if ($post['posterId'] === $user['id'])
            jsonResponse(['ok' => false, 'error' => 'You cannot claim your own post.'], 400);
        if ($post['status'] === 'resolved')
            jsonResponse(['ok' => false, 'error' => 'This post has already been resolved.'], 400);
        if (mockUserAlreadyClaimed($post_id, $user['id']))
            jsonResponse(['ok' => false, 'error' => 'You have already submitted a claim for this item.'], 409);

        $claim = [
            'id'           => mockNextClaimId(),
            'post_id'      => $post_id,
            'user_id'      => $user['id'],
            'message'      => $message,
            'claim_status' => 'pending',
            'date_request' => date('Y-m-d H:i:s'),
        ];
        $claims   = mockGetClaims();
        $claims[] = $claim;
        mockSaveClaims($claims);
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT user_id, status FROM item_post WHERE post_id = ?');
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
        if (!$post) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
        if ($post['user_id'] === $user['id'])
            jsonResponse(['ok' => false, 'error' => 'You cannot claim your own post.'], 400);
        if ($post['status'] === 'resolved')
            jsonResponse(['ok' => false, 'error' => 'This post has already been resolved.'], 400);

        $check = $db->prepare('SELECT claim_id FROM claim_request WHERE post_id = ? AND user_id = ?');
        $check->execute([$post_id, $user['id']]);
        if ($check->fetch())
            jsonResponse(['ok' => false, 'error' => 'You have already submitted a claim for this item.'], 409);

        $ins = $db->prepare(
            'INSERT INTO claim_request (post_id, user_id, message, claim_status)
             VALUES (?, ?, ?, "pending")'
        );
        $ins->execute([$post_id, $user['id'], $message]);
        $claim = [
            'id'           => (int)$db->lastInsertId(),
            'post_id'      => $post_id,
            'user_id'      => $user['id'],
            'message'      => $message,
            'claim_status' => 'pending',
            'date_request' => date('Y-m-d H:i:s'),
        ];
    }

    jsonResponse(['ok' => true, 'claim' => enrichClaim($claim)]);
}

// ------ INCOMING CLAIMS -----------------------------------------------------
if ($action === 'incoming') {
    $user = requireUser();

    if (USE_MOCK_DB) {
        $myPostIds = array_column(
            array_filter(mockGetPosts(), fn($p) => $p['posterId'] === $user['id']),
            'id'
        );
        $claims = array_values(array_filter(mockGetClaims(), fn($c) => in_array($c['post_id'], $myPostIds)));
        $claims = array_map('enrichClaim', $claims);
    } else {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT cr.* FROM claim_request cr
             JOIN item_post ip ON cr.post_id = ip.post_id
             WHERE ip.user_id = ?
             ORDER BY cr.claim_status = "pending" DESC, cr.date_request DESC'
        );
        $stmt->execute([$user['id']]);
        $claims = array_map(fn($r) => enrichClaim([
            'id'           => $r['claim_id'],
            'post_id'      => $r['post_id'],
            'user_id'      => $r['user_id'],
            'message'      => $r['message'],
            'claim_status' => $r['claim_status'],
            'date_request' => $r['date_request'],
        ]), $stmt->fetchAll());
    }

    jsonResponse(['ok' => true, 'claims' => $claims]);
}

// ------ OUTGOING CLAIMS -----------------------------------------------------
if ($action === 'outgoing') {
    $user = requireUser();

    if (USE_MOCK_DB) {
        $claims = array_map('enrichClaim', mockGetClaimsByUser($user['id']));
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM claim_request WHERE user_id = ? ORDER BY date_request DESC');
        $stmt->execute([$user['id']]);
        $claims = array_map(fn($r) => enrichClaim([
            'id'           => $r['claim_id'],
            'post_id'      => $r['post_id'],
            'user_id'      => $r['user_id'],
            'message'      => $r['message'],
            'claim_status' => $r['claim_status'],
            'date_request' => $r['date_request'],
        ]), $stmt->fetchAll());
    }

    jsonResponse(['ok' => true, 'claims' => $claims]);
}

// ------ CLAIMS FOR ONE POST -------------------------------------------------
if ($action === 'for_post') {
    $user    = requireUser();
    $post_id = (int)($_GET['post_id'] ?? 0);
    if (!$post_id) jsonResponse(['ok' => false, 'error' => 'Missing post_id.'], 400);

    if (USE_MOCK_DB) {
        $post = null;
        foreach (mockGetPosts() as $p) { if ($p['id'] === $post_id) { $post = $p; break; } }
        if (!$post) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
        if ($post['posterId'] !== $user['id'])
            jsonResponse(['ok' => false, 'error' => 'Not your post.'], 403);
        $claims = array_map('enrichClaim', mockGetClaimsForPost($post_id));
    } else {
        $db   = getDB();
        $chk  = $db->prepare('SELECT user_id FROM item_post WHERE post_id = ?');
        $chk->execute([$post_id]);
        $row  = $chk->fetch();
        if (!$row) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
        if ($row['user_id'] !== $user['id'])
            jsonResponse(['ok' => false, 'error' => 'Not your post.'], 403);

        $stmt = $db->prepare('SELECT * FROM claim_request WHERE post_id = ? ORDER BY date_request DESC');
        $stmt->execute([$post_id]);
        $claims = array_map(fn($r) => enrichClaim([
            'id'           => $r['claim_id'],
            'post_id'      => $r['post_id'],
            'user_id'      => $r['user_id'],
            'message'      => $r['message'],
            'claim_status' => $r['claim_status'],
            'date_request' => $r['date_request'],
        ]), $stmt->fetchAll());
    }

    jsonResponse(['ok' => true, 'claims' => $claims]);
}

// ------ ACCEPT CLAIM --------------------------------------------------------
if ($action === 'accept' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user     = requireUser();
    $claim_id = (int)($_GET['claim_id'] ?? 0);
    if (!$claim_id) jsonResponse(['ok' => false, 'error' => 'Missing claim_id.'], 400);

    if (USE_MOCK_DB) {
        $claim = mockFindClaim($claim_id);
        if (!$claim) jsonResponse(['ok' => false, 'error' => 'Claim not found.'], 404);
        $post = null;
        foreach (mockGetPosts() as $p) { if ($p['id'] === $claim['post_id']) { $post = $p; break; } }
        if (!$post || $post['posterId'] !== $user['id'])
            jsonResponse(['ok' => false, 'error' => 'Not authorized.'], 403);
        $claims = mockGetClaims();
        foreach ($claims as &$c) {
            if ($c['post_id'] === $claim['post_id'])
                $c['claim_status'] = ($c['id'] === $claim_id) ? 'accepted' : 'rejected';
        }
        mockSaveClaims($claims);
        $posts = mockGetPosts();
        foreach ($posts as &$p) {
            if ($p['id'] === $claim['post_id']) { $p['status'] = 'resolved'; break; }
        }
        mockSavePosts($posts);
    } else {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT cr.post_id, ip.user_id AS post_owner
             FROM claim_request cr
             JOIN item_post ip ON cr.post_id = ip.post_id
             WHERE cr.claim_id = ?'
        );
        $stmt->execute([$claim_id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(['ok' => false, 'error' => 'Claim not found.'], 404);
        if ($row['post_owner'] !== $user['id'])
            jsonResponse(['ok' => false, 'error' => 'Not authorized.'], 403);

        $post_id = $row['post_id'];
        $db->prepare('UPDATE claim_request SET claim_status = "rejected" WHERE post_id = ?')->execute([$post_id]);
        $db->prepare('UPDATE claim_request SET claim_status = "accepted" WHERE claim_id = ?')->execute([$claim_id]);
        $db->prepare('UPDATE item_post SET status = "resolved" WHERE post_id = ?')->execute([$post_id]);
    }

    jsonResponse(['ok' => true]);
}

// ------ REJECT CLAIM --------------------------------------------------------
if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user     = requireUser();
    $claim_id = (int)($_GET['claim_id'] ?? 0);
    if (!$claim_id) jsonResponse(['ok' => false, 'error' => 'Missing claim_id.'], 400);

    if (USE_MOCK_DB) {
        $claim = mockFindClaim($claim_id);
        if (!$claim) jsonResponse(['ok' => false, 'error' => 'Claim not found.'], 404);
        $post = null;
        foreach (mockGetPosts() as $p) { if ($p['id'] === $claim['post_id']) { $post = $p; break; } }
        if (!$post || $post['posterId'] !== $user['id'])
            jsonResponse(['ok' => false, 'error' => 'Not authorized.'], 403);
        $claims = mockGetClaims();
        foreach ($claims as &$c) {
            if ($c['id'] === $claim_id) { $c['claim_status'] = 'rejected'; break; }
        }
        mockSaveClaims($claims);
    } else {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT cr.post_id, ip.user_id AS post_owner
             FROM claim_request cr
             JOIN item_post ip ON cr.post_id = ip.post_id
             WHERE cr.claim_id = ?'
        );
        $stmt->execute([$claim_id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(['ok' => false, 'error' => 'Claim not found.'], 404);
        if ($row['post_owner'] !== $user['id'])
            jsonResponse(['ok' => false, 'error' => 'Not authorized.'], 403);
        $db->prepare('UPDATE claim_request SET claim_status = "rejected" WHERE claim_id = ?')->execute([$claim_id]);
    }

    jsonResponse(['ok' => true]);
}

// ------ BADGE COUNT ---------------------------------------------------------
if ($action === 'badge') {
    $user = requireUser();

    if (USE_MOCK_DB) {
        $pendingClaims  = mockCountIncomingPendingClaims($user['id']);
        $unreadContacts = mockCountUnreadContacts($user['id']);
    } else {
        $db  = getDB();
        $s1  = $db->prepare(
            'SELECT COUNT(*) FROM claim_request cr
             JOIN item_post ip ON cr.post_id = ip.post_id
             WHERE ip.user_id = ? AND cr.claim_status = "pending"'
        );
        $s1->execute([$user['id']]);
        $pendingClaims = (int)$s1->fetchColumn();

        $s2 = $db->prepare(
            'SELECT COUNT(*) FROM contact c
             JOIN item_post ip ON c.post_id = ip.post_id
             WHERE ip.user_id = ? AND c.is_read = 0'
        );
        $s2->execute([$user['id']]);
        $unreadContacts = (int)$s2->fetchColumn();
    }

    jsonResponse(['ok' => true, 'claims' => $pendingClaims, 'contacts' => $unreadContacts,
                  'total' => $pendingClaims + $unreadContacts]);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400);
