<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/layout.php';

require_login('employer');

$pdo            = db();
$user           = current_user();
$employerUserId = (int)$user['id'];

// Lấy employer_id (user_id) — đây chính là $employerUserId
// Bảng conversations lưu employer_id = users.id của employer

// --- Xử lý gửi tin nhắn (AJAX POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_message') {
        header('Content-Type: application/json');
        verify_csrf();

        $convId  = (int)($_POST['conversation_id'] ?? 0);
        $message = trim((string)($_POST['message'] ?? ''));

        if ($convId <= 0 || $message === '') {
            echo json_encode(['ok' => false, 'error' => 'Dữ liệu không hợp lệ']);
            exit;
        }

        $chk = $pdo->prepare('SELECT id, candidate_id FROM conversations WHERE id = :id AND employer_id = :eid LIMIT 1');
        $chk->execute([':id' => $convId, ':eid' => $employerUserId]);
        $convRow = $chk->fetch();
        if (!$convRow) {
            echo json_encode(['ok' => false, 'error' => 'Không có quyền']);
            exit;
        }

        $stmt = $pdo->prepare('
            INSERT INTO messages (conversation_id, sender_id, receiver_id, message, is_read)
            VALUES (:conv_id, :sender_id, :receiver_id, :message, 0)
        ');
        $stmt->execute([
            ':conv_id'     => $convId,
            ':sender_id'   => $employerUserId,
            ':receiver_id' => (int)$convRow['candidate_id'],
            ':message'     => $message,
        ]);

        notify_user($pdo, (int)$convRow['candidate_id'], "Nhà tuyển dụng " . $user['name'] . " đã gửi tin nhắn mới.");

        echo json_encode(['ok' => true, 'created_at' => date('H:i d/m/Y')]);
        exit;
    }

    // Tạo conversation mới từ application
    if ($action === 'start_conversation') {
        verify_csrf();
        $appId = (int)($_POST['application_id'] ?? 0);
        if ($appId <= 0) {
            flash('ID đơn ứng tuyển không hợp lệ.', 'danger');
            redirect('/employer/index.php?page=applications');
        }

        // Kiểm tra application thuộc công ty của employer này
        $empStmt = $pdo->prepare('SELECT company_id FROM employers WHERE user_id = :uid LIMIT 1');
        $empStmt->execute([':uid' => $employerUserId]);
        $empRow = $empStmt->fetch();
        $companyId = $empRow ? (int)$empRow['company_id'] : 0;

        $appStmt = $pdo->prepare('
            SELECT a.id, a.candidate_id FROM applications a
            JOIN jobs j ON j.id = a.job_id
            WHERE a.id = :aid AND j.company_id = :cid
            LIMIT 1
        ');
        $appStmt->execute([':aid' => $appId, ':cid' => $companyId]);
        $app = $appStmt->fetch();

        if (!$app) {
            flash('Không tìm thấy đơn ứng tuyển.', 'danger');
            redirect('/employer/index.php?page=applications');
        }

        // Kiểm tra conversation đã tồn tại chưa
        $existStmt = $pdo->prepare('SELECT id FROM conversations WHERE application_id = :aid LIMIT 1');
        $existStmt->execute([':aid' => $appId]);
        $existing = $existStmt->fetch();

        if ($existing) {
            redirect('/employer/chat.php?conv=' . $existing['id']);
        }

        $insStmt = $pdo->prepare('
            INSERT INTO conversations (application_id, employer_id, candidate_id)
            VALUES (:app_id, :emp_id, :cand_id)
        ');
        $insStmt->execute([
            ':app_id'  => $appId,
            ':emp_id'  => $employerUserId,
            ':cand_id' => (int)$app['candidate_id'],
        ]);
        $newConvId = (int)$pdo->lastInsertId();

        notify_user($pdo, (int)$app['candidate_id'], "Nhà tuyển dụng " . $user['name'] . " đã bắt đầu cuộc trò chuyện với bạn.");

        redirect('/employer/chat.php?conv=' . $newConvId);
    }
}

// Đánh dấu đã đọc
$activeConvId = (int)($_GET['conv'] ?? 0);
if ($activeConvId > 0) {
    $pdo->prepare('
        UPDATE messages SET is_read = 1
        WHERE conversation_id = :cid AND sender_id != :uid
    ')->execute([':cid' => $activeConvId, ':uid' => $employerUserId]);
}

// Danh sách conversations
$convStmt = $pdo->prepare('
    SELECT
        c.id AS conv_id,
        c.created_at AS conv_created,
        j.title AS job_title,
        u.id AS candidate_user_id,
        u.name AS candidate_name,
        u.avatar AS candidate_avatar,
        (SELECT COUNT(*) 
         FROM messages m 
         WHERE m.conversation_id = c.id 
           AND m.sender_id != :uid_unread 
           AND m.is_read = 0) AS unread_count,
        (SELECT m2.message 
         FROM messages m2 
         WHERE m2.conversation_id = c.id 
         ORDER BY m2.created_at DESC 
         LIMIT 1) AS last_message,
        (SELECT m3.created_at 
         FROM messages m3 
         WHERE m3.conversation_id = c.id 
         ORDER BY m3.created_at DESC 
         LIMIT 1) AS last_time
    FROM conversations c
    JOIN applications a ON a.id = c.application_id
    JOIN jobs j ON j.id = a.job_id
    JOIN users u ON u.id = c.candidate_id
    WHERE c.employer_id = :uid_where
    ORDER BY last_time DESC
');

$convStmt->execute([
    'uid_unread' => $employerUserId,
    'uid_where'  => $employerUserId
]);

$conversations = $convStmt->fetchAll();

// Messages của conversation đang mở
$messages   = [];
$activeConv = null;
if ($activeConvId > 0) {
    $acStmt = $pdo->prepare('
        SELECT c.id, c.candidate_id, j.title AS job_title, u.name AS candidate_name, u.avatar AS candidate_avatar
        FROM conversations c
        JOIN applications a ON a.id = c.application_id
        JOIN jobs j ON j.id = a.job_id
        JOIN users u ON u.id = c.candidate_id
        WHERE c.id = :id AND c.employer_id = :eid
        LIMIT 1
    ');
    $acStmt->execute([':id' => $activeConvId, ':eid' => $employerUserId]);
    $activeConv = $acStmt->fetch();

    if ($activeConv) {
        $msgStmt = $pdo->prepare('
            SELECT m.id, m.sender_id, m.message, m.is_read, m.created_at, u.name AS sender_name, u.avatar AS sender_avatar
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            WHERE m.conversation_id = :conv_id
            ORDER BY m.created_at ASC
        ');
        $msgStmt->execute([':conv_id' => $activeConvId]);
        $messages = $msgStmt->fetchAll();
    }
}

render_header('Tin nhắn - Nhà tuyển dụng', [
    'extra_head' => '<style>
.chat-sidebar{height:70vh;overflow-y:auto;border-right:1px solid #dee2e6}
.chat-body{height:55vh;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding:16px}
.msg-bubble{max-width:70%;padding:10px 14px;border-radius:18px;line-height:1.4;font-size:.92rem;word-break:break-word}
.msg-mine{background:#198754;color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
.msg-theirs{background:#f0f0f0;color:#222;align-self:flex-start;border-bottom-left-radius:4px}
.msg-time{font-size:.72rem;opacity:.65;margin-top:3px}
.conv-item{cursor:pointer;transition:background .15s}
.conv-item:hover,.conv-item.active{background:#e8f5e9}
.conv-item.active{border-left:3px solid #198754}
.chat-input-row{padding:12px;border-top:1px solid #dee2e6}
</style>'
]);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fa-solid fa-comments me-2 text-success"></i>Tin nhắn với ứng viên</h5>
    <a href="<?= e(BASE_URL) ?>/employer/index.php?page=applications" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Quản lý ứng viên
    </a>
</div>

<div class="row g-0" style="min-height:70vh;border:1px solid #dee2e6;border-radius:12px;overflow:hidden">

    <!-- Sidebar -->
    <div class="col-lg-4 chat-sidebar">
        <div class="p-3 border-bottom fw-bold bg-light">
            Cuộc trò chuyện (<?= count($conversations) ?>)
        </div>
        <?php if (empty($conversations)): ?>
            <div class="p-4 text-muted text-center small">Chưa có cuộc trò chuyện.<br>Vào mục ứng viên để bắt đầu chat.</div>
        <?php else: ?>
        <?php foreach ($conversations as $conv): ?>
        <a href="?conv=<?= $conv['conv_id'] ?>"
           class="d-block text-decoration-none text-dark conv-item p-3 border-bottom <?= $activeConvId === (int)$conv['conv_id'] ? 'active' : '' ?>">
            <div class="d-flex align-items-center gap-2">
                <img src="<?= e($conv['candidate_avatar'] ? BASE_URL . '/' . $conv['candidate_avatar'] : BASE_URL . '/assets/images/default-avatar.png') ?>"
                     width="42" height="42" class="rounded-circle object-fit-cover" style="flex-shrink:0">
                <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold small"><?= e($conv['candidate_name']) ?></span>
                        <span class="text-muted" style="font-size:.72rem"><?= $conv['last_time'] ? date('H:i', strtotime($conv['last_time'])) : '' ?></span>
                    </div>
                    <div class="text-muted small text-truncate"><?= e($conv['job_title']) ?></div>
                    <div class="text-truncate" style="font-size:.8rem;color:#555"><?= e((string)($conv['last_message'] ?? '')) ?></div>
                </div>
                <?php if ((int)$conv['unread_count'] > 0): ?>
                <span class="badge bg-danger rounded-pill"><?= (int)$conv['unread_count'] ?></span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Chat area -->
    <div class="col-lg-8 d-flex flex-column">
        <?php if ($activeConv): ?>
        <div class="p-3 border-bottom bg-light d-flex align-items-center gap-2">
            <img src="<?= e($activeConv['candidate_avatar'] ? BASE_URL . '/' . $activeConv['candidate_avatar'] : BASE_URL . '/assets/images/default-avatar.png') ?>"
                 width="38" height="38" class="rounded-circle">
            <div>
                <div class="fw-semibold"><?= e($activeConv['candidate_name']) ?></div>
                <div class="text-muted small"><?= e($activeConv['job_title']) ?></div>
            </div>
            <div class="ms-auto">
                <a href="<?= e(BASE_URL) ?>/employer/candidate_profile.php?id=<?= (int)$activeConv['candidate_id'] ?>"
                   class="btn btn-sm btn-outline-primary" target="_blank">
                    <i class="fa-solid fa-user me-1"></i>Xem hồ sơ
                </a>
            </div>
        </div>

        <div class="chat-body" id="chatBody">
            <?php foreach ($messages as $msg): ?>
            <?php $isMine = (int)$msg['sender_id'] === $employerUserId; ?>
            <div class="d-flex flex-column <?= $isMine ? 'align-items-end' : 'align-items-start' ?>">
                <div class="msg-bubble <?= $isMine ? 'msg-mine' : 'msg-theirs' ?>">
                    <?= e($msg['message']) ?>
                </div>
                <div class="msg-time"><?= date('H:i d/m', strtotime($msg['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Quick reply buttons -->
        <div class="px-3 pt-2 d-flex gap-2 flex-wrap">
            <button class="btn btn-sm btn-outline-secondary quick-reply" data-msg="Chúng tôi đã nhận được hồ sơ của bạn, chúng tôi sẽ liên hệ sớm.">Xác nhận nhận hồ sơ</button>
            <button class="btn btn-sm btn-outline-success quick-reply" data-msg="Bạn đã được chọn phỏng vấn. Vui lòng xác nhận lịch phỏng vấn.">Mời phỏng vấn</button>
            <button class="btn btn-sm btn-outline-danger quick-reply" data-msg="Rất tiếc, hồ sơ của bạn chưa phù hợp với vị trí tuyển dụng lần này.">Từ chối lịch sự</button>
        </div>

        <div class="chat-input-row">
            <form id="chatForm" class="d-flex gap-2">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="conversation_id" value="<?= $activeConvId ?>">
                <input type="text" name="message" id="msgInput" class="form-control" placeholder="Nhập tin nhắn..." autocomplete="off" required>
                <button type="submit" class="btn btn-success px-3"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
        </div>

        <script>
        const chatBody = document.getElementById('chatBody');
        chatBody.scrollTop = chatBody.scrollHeight;

        document.getElementById('chatForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const msgInput = document.getElementById('msgInput');
            const msg = msgInput.value.trim();
            if (!msg) return;
            const formData = new FormData(this);
            msgInput.value = '';
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.ok) {
                    chatBody.insertAdjacentHTML('beforeend', `
                        <div class="d-flex flex-column align-items-end">
                            <div class="msg-bubble msg-mine">${escHtml(msg)}</div>
                            <div class="msg-time">${data.created_at}</div>
                        </div>
                    `);
                    chatBody.scrollTop = chatBody.scrollHeight;
                }
            } catch(err) { console.error(err); }
        });

        document.querySelectorAll('.quick-reply').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('msgInput').value = btn.dataset.msg;
                document.getElementById('msgInput').focus();
            });
        });

        function escHtml(str) {
            return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        setInterval(async () => {
            const res = await fetch('?conv=<?= $activeConvId ?>&_t=' + Date.now());
            if (res.ok) {
                const html = await res.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newBody = doc.getElementById('chatBody');
                if (newBody && newBody.children.length > chatBody.children.length) {
                    chatBody.innerHTML = newBody.innerHTML;
                    chatBody.scrollTop = chatBody.scrollHeight;
                }
            }
        }, 15000);
        </script>

        <?php else: ?>
        <div class="d-flex align-items-center justify-content-center flex-grow-1 text-muted">
            <div class="text-center">
                <i class="fa-regular fa-comments fa-3x mb-3 text-secondary"></i>
                <p>Chọn một cuộc trò chuyện hoặc<br>bắt đầu chat từ trang <a href="<?= e(BASE_URL) ?>/employer/index.php?page=applications">Quản lý ứng viên</a></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>