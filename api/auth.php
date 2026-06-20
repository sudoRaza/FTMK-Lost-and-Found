<?php
// ============================================================
// api/auth.php — Authentication endpoints
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mock_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') jsonResponse(['ok' => true]);

$action = $_GET['action'] ?? '';

// ------ CHECK SESSION -------------------------------------------------------
if ($action === 'check') {
    jsonResponse([
        'loggedIn' => isset($_SESSION['ftmk_user']),
        'user'     => $_SESSION['ftmk_user'] ?? null,
    ]);
}

// ------ LOGOUT --------------------------------------------------------------
if ($action === 'logout') {
    unset($_SESSION['ftmk_user']);
    jsonResponse(['ok' => true]);
}

// ------ LOGIN ---------------------------------------------------------------
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body       = getRequestBody();
    $identifier = trim($body['identifier'] ?? '');
    $password   = $body['password'] ?? '';

    if (!$identifier || !$password)
        jsonResponse(['ok' => false, 'error' => 'Please fill in all fields.'], 400);

    $isEmail     = str_contains($identifier, '@');
    $isUTemEmail = str_ends_with($identifier, '@student.utem.edu.my') || str_ends_with($identifier, '@utem.edu.my');
    $isMatric    = preg_match('/^[A-Z]\d{9,}$/i', $identifier) || preg_match('/^STF\d+$/i', $identifier);

    if ($isEmail && !$isUTemEmail)
        jsonResponse(['ok' => false, 'error' => 'Please use your UTeM email (@student.utem.edu.my or @utem.edu.my)'], 400);
    if (!$isEmail && !$isMatric)
        jsonResponse(['ok' => false, 'error' => 'Please enter a valid matric or staff number (e.g. B032310001)'], 400);

    if (USE_MOCK_DB) {
        $user = mockFindUser($identifier);
        if (!$user)
            jsonResponse(['ok' => false, 'error' => 'Account not found.'], 401);
        if (!password_verify($password, $user['password_hash']))
            jsonResponse(['ok' => false, 'error' => 'Incorrect password.'], 401);
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE user_id = ? OR email = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password']))
            jsonResponse(['ok' => false, 'error' => 'Invalid credentials.'], 401);
    }

    $sessionUser = [
        'id'          => $user['user_id']     ?? $user['id'],
        'name'        => $user['name'],
        'email'       => $user['email'],
        'role'        => $user['role'],
        'initials'    => $user['initials'],
        'avatarColor' => $user['avatar_color'] ?? $user['avatarColor'] ?? 'blue',
    ];
    $_SESSION['ftmk_user'] = $sessionUser;
    jsonResponse(['ok' => true, 'user' => $sessionUser]);
}

// ------ SIGNUP --------------------------------------------------------------
if ($action === 'signup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = getRequestBody();
    $name   = trim($body['name']     ?? '');
    $matric = strtoupper(trim($body['matric'] ?? ''));
    $email  = strtolower(trim($body['email']  ?? ''));
    $role   = $body['role']          ?? 'Student';
    $pass   = $body['password']      ?? '';

    if (!$name || !$matric || !$email || !$pass)
        jsonResponse(['ok' => false, 'error' => 'Please fill in all required fields.'], 400);
    if (strlen($pass) < 8)
        jsonResponse(['ok' => false, 'error' => 'Password must be at least 8 characters.'], 400);
    if (!str_ends_with($email, '@student.utem.edu.my') && !str_ends_with($email, '@utem.edu.my'))
        jsonResponse(['ok' => false, 'error' => 'Email must be a valid UTeM email address.'], 400);
    if (!preg_match('/^[A-Z]\d{9,}$/i', $matric) && !preg_match('/^STF\d+$/i', $matric))
        jsonResponse(['ok' => false, 'error' => 'Please enter a valid matric or staff number.'], 400);

    $words    = array_filter(explode(' ', $name));
    $initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice($words, 0, 2))));

    if (USE_MOCK_DB) {
        if (mockFindUser($matric) || mockFindUser($email))
            jsonResponse(['ok' => false, 'error' => 'An account with this matric number or email already exists.'], 409);

        $newUser = [
            'id'           => $matric,
            'name'         => $name,
            'email'        => $email,
            'role'         => $role,
            'password_hash'=> password_hash($pass, PASSWORD_DEFAULT),
            'initials'     => $initials,
            'avatarColor'  => 'blue',
        ];
        $users   = mockGetUsers();
        $users[] = $newUser;
        mockSaveUsers($users);
    } else {
        $db    = getDB();
        $check = $db->prepare('SELECT user_id FROM users WHERE user_id = ? OR email = ? LIMIT 1');
        $check->execute([$matric, $email]);
        if ($check->fetch())
            jsonResponse(['ok' => false, 'error' => 'An account with this matric number or email already exists.'], 409);

        $stmt = $db->prepare(
            'INSERT INTO users (user_id, name, email, password, role, initials, avatar_color)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$matric, $name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $initials, 'blue']);

        $newUser = [
            'id'          => $matric,
            'name'        => $name,
            'email'       => $email,
            'role'        => $role,
            'initials'    => $initials,
            'avatarColor' => 'blue',
        ];
    }

    $sessionUser = [
        'id'          => $newUser['id'],
        'name'        => $newUser['name'],
        'email'       => $newUser['email'],
        'role'        => $newUser['role'],
        'initials'    => $newUser['initials'],
        'avatarColor' => $newUser['avatarColor'],
    ];
    $_SESSION['ftmk_user'] = $sessionUser;
    jsonResponse(['ok' => true, 'user' => $sessionUser]);
}

// ------ FORGOT PASSWORD -----------------------------------------------------
if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getRequestBody();
    $id   = trim($body['identifier'] ?? '');

    if (!$id)
        jsonResponse(['ok' => false, 'error' => 'Please enter your UTeM email or matric number.'], 400);

    $isEmail     = str_contains($id, '@');
    $isUTemEmail = str_ends_with($id, '@student.utem.edu.my') || str_ends_with($id, '@utem.edu.my');
    $isMatric    = preg_match('/^[A-Z]\d{9,}$/i', $id) || preg_match('/^STF\d+$/i', $id);

    if ($isEmail && !$isUTemEmail)
        jsonResponse(['ok' => false, 'error' => 'Please use your UTeM email.'], 400);
    if (!$isEmail && !$isMatric)
        jsonResponse(['ok' => false, 'error' => 'Please enter a valid matric or staff number.'], 400);

    $displayEmail = $isEmail ? $id : strtolower($id) . '@student.utem.edu.my';
    jsonResponse(['ok' => true, 'sentTo' => $displayEmail]);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400);
