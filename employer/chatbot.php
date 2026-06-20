<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/layout.php';

require_login('employer');

$pdo            = db();
$user           = current_user();
$employerUserId = (int)$user['id'];

$empStmt = $pdo->prepare('SELECT company_id FROM employers WHERE user_id = :uid LIMIT 1');
$empStmt->execute([':uid' => $employerUserId]);
$empRow    = $empStmt->fetch();
$companyId = $empRow ? (int)$empRow['company_id'] : 0;

$candidatesStmt = $pdo->prepare('
    SELECT
        u.id AS candidate_id,
        u.name AS candidate_name,
        u.email,
        u.avatar,
        u.created_at AS joined_at,
        cp.phone,
        cp.address,
        cp.experience,
        cp.education,
        cp.career_goal,
        cp.expected_salary,
        cp.experience_years,
        (SELECT COUNT(*) FROM user_skills us WHERE us.user_id = u.id) AS skill_count,
        (SELECT COUNT(*) FROM certificates cert WHERE cert.user_id = u.id) AS cert_count,
        (SELECT COUNT(*) FROM cvs cv WHERE cv.user_id = u.id) AS cv_count,
        (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ", ")
            FROM user_skills us2 JOIN skills s ON s.id = us2.skill_id WHERE us2.user_id = u.id) AS skills_list,
        (SELECT GROUP_CONCAT(c.name ORDER BY c.issue_date DESC SEPARATOR ", ")
            FROM certificates c WHERE c.user_id = u.id) AS certs_list,
        MAX(a.apply_date) AS last_apply_date,
        COUNT(DISTINCT a.id) AS apps_at_company
    FROM applications a
    JOIN jobs j ON j.id = a.job_id
    JOIN users u ON u.id = a.candidate_id
    LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
    WHERE j.company_id = :cid
    GROUP BY u.id, u.name, u.email, u.avatar, u.created_at,
             cp.phone, cp.address, cp.experience, cp.education, cp.career_goal,
             cp.expected_salary, cp.experience_years
    ORDER BY last_apply_date DESC
');
$candidatesStmt->execute([':cid' => $companyId]);
$candidates = $candidatesStmt->fetchAll();

$companyStmt = $pdo->prepare('SELECT name FROM companies WHERE id = :id LIMIT 1');
$companyStmt->execute([':id' => $companyId]);
$company = $companyStmt->fetch();

$companyContext  = "Nhà tuyển dụng: " . $user['name'] . " - Công ty: " . ($company['name'] ?? '') . "\n\n";
$companyContext .= "Danh sách ứng viên đã ứng tuyển:\n";

foreach ($candidates as $c) {
    $score  = 0;
    $score += min(30, (int)$c['skill_count'] * 5);
    $score += min(20, (int)$c['cert_count']  * 5);
    $score += min(15, (int)$c['cv_count']    * 15);
    $score += min(20, (int)($c['experience_years'] ?? 0) * 4);
    $score += $c['career_goal'] ? 5 : 0;
    $score += $c['address']     ? 5 : 0;
    $score += $c['phone']       ? 5 : 0;

    $companyContext .= "---\n";
    $companyContext .= "ID: {$c['candidate_id']} | Tên: {$c['candidate_name']} | Điểm: {$score}/100\n";
    $companyContext .= "Kỹ năng ({$c['skill_count']}): " . ($c['skills_list'] ?: 'Chưa cập nhật') . "\n";
    $companyContext .= "Chứng chỉ ({$c['cert_count']}): " . ($c['certs_list'] ?: 'Chưa có') . "\n";
    $companyContext .= "Kinh nghiệm: " . ($c['experience_years'] ?? 0) . " năm | " . ($c['experience'] ?: 'Chưa cập nhật') . "\n";
    $companyContext .= "Học vấn: " . ($c['education'] ?: 'Chưa cập nhật') . "\n";
    $companyContext .= "CV: " . ($c['cv_count'] > 0 ? "Có ({$c['cv_count']} file)" : "Chưa có") . "\n";
    $companyContext .= "Số lần ứng tuyển tại công ty: {$c['apps_at_company']}\n";
}

render_header('Chatbot đánh giá ứng viên', [
    'extra_head' => '<style>
#chatContainer{height:60vh;overflow-y:auto;display:flex;flex-direction:column;gap:10px;padding:16px;background:#f8f9fa;border-radius:12px}
.cb-bubble{max-width:82%;padding:12px 16px;border-radius:16px;line-height:1.5;font-size:.9rem;word-break:break-word}
.cb-user{background:#198754;color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
.cb-bot{background:#fff;color:#222;align-self:flex-start;border-bottom-left-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.cb-typing{background:#fff;align-self:flex-start;padding:12px 16px;border-radius:16px;border-bottom-left-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#aaa;animation:blink 1.2s infinite}
.dot:nth-child(2){animation-delay:.3s}.dot:nth-child(3){animation-delay:.6s}
@keyframes blink{0%,80%,100%{opacity:.2}40%{opacity:1}}
.quick-chip{cursor:pointer;border:1px solid #198754;color:#198754;background:#fff;border-radius:20px;padding:4px 14px;font-size:.82rem;transition:.15s}
.quick-chip:hover{background:#198754;color:#fff}
</style>'
]);
?>

<div class="row justify-content-center">
<div class="col-lg-9">
<div class="app-card">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="rounded-circle bg-success d-flex align-items-center justify-content-center text-white" style="width:46px;height:46px;flex-shrink:0">
            <i class="fa-solid fa-robot"></i>
        </div>
        <div>
            <h5 class="mb-0 fw-bold">HRBot - Trợ lý đánh giá ứng viên</h5>
            <div class="text-muted small">Có <strong><?= count($candidates) ?></strong> ứng viên trong database</div>
        </div>
        <a href="<?= e(BASE_URL) ?>/employer/index.php?page=applications" class="btn btn-sm btn-outline-secondary ms-auto">
            <i class="fa-solid fa-arrow-left me-1"></i>Quản lý ứng viên
        </a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <button class="quick-chip" onclick="sendQuick(this)">Ứng viên nào phù hợp nhất?</button>
        <button class="quick-chip" onclick="sendQuick(this)">Top 5 ứng viên nổi bật</button>
        <button class="quick-chip" onclick="sendQuick(this)">Ứng viên có nhiều kỹ năng nhất</button>
        <button class="quick-chip" onclick="sendQuick(this)">Ứng viên có nhiều chứng chỉ nhất</button>
        <button class="quick-chip" onclick="sendQuick(this)">So sánh tất cả ứng viên</button>
    </div>

    <div id="chatContainer">
        <div class="d-flex align-items-start gap-2">
            <div class="cb-bubble cb-bot">
                <strong>Xin chào <?= e($user['name']) ?>! 🤝</strong><br>
                Tôi là HRBot — trợ lý đánh giá ứng viên thông minh.<br>
                Tôi đã phân tích <strong><?= count($candidates) ?></strong> ứng viên đã ứng tuyển vào công ty bạn.<br><br>
                Bạn có thể hỏi tôi:<br>
                • Ứng viên nào phù hợp nhất cho vị trí cụ thể<br>
                • Xếp hạng ứng viên theo kỹ năng / chứng chỉ<br>
                • So sánh chi tiết giữa các ứng viên<br>
                • Đánh giá điểm mạnh/yếu của từng ứng viên
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <input type="text" id="userInput" class="form-control" placeholder="Hỏi về ứng viên..." autocomplete="off">
        <button id="sendBtn" class="btn btn-success px-4" onclick="sendMessage()">
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </div>
</div>
</div>
</div>

<script>
const COMPANY_CONTEXT = <?= json_encode($companyContext, JSON_UNESCAPED_UNICODE) ?>;
const BASE_URL        = <?= json_encode(BASE_URL, JSON_HEX_TAG) ?>;

const chatHistory = [];

const systemPrompt = `Bạn là HRBot, trợ lý đánh giá ứng viên thông minh cho nhà tuyển dụng.

${COMPANY_CONTEXT}

Hệ thống tính điểm:
- Kỹ năng: tối đa 30 điểm (5đ/kỹ năng)
- Chứng chỉ: tối đa 20 điểm (5đ/chứng chỉ)
- Có CV: 15 điểm
- Kinh nghiệm: tối đa 20 điểm (4đ/năm)
- Có mục tiêu nghề nghiệp: 5 điểm
- Hồ sơ đầy đủ (địa chỉ, SĐT): 10 điểm

Nhiệm vụ:
1. Phân tích và xếp hạng ứng viên theo yêu cầu của nhà tuyển dụng
2. Hiển thị điểm đánh giá rõ ràng
3. Chỉ ra điểm mạnh và điểm cần cải thiện
4. Khi liệt kê, dùng danh sách có thứ tự rõ ràng
5. Link xem hồ sơ: ${BASE_URL}/employer/candidate_profile.php?id=ID_UNG_VIEN
6. Trả lời bằng tiếng Việt, chuyên nghiệp, súc tích`;

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}
function formatBotMessage(text) {
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g,     '<em>$1</em>')
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" class="text-success">$1</a>')
        .replace(/\n/g, '<br>');
}
function appendMessage(role, text) {
    const container = document.getElementById('chatContainer');
    const wrap   = document.createElement('div');
    wrap.className = 'd-flex align-items-start gap-2 ' + (role === 'user' ? 'justify-content-end' : '');
    const bubble = document.createElement('div');
    bubble.className = 'cb-bubble ' + (role === 'user' ? 'cb-user' : 'cb-bot');
    bubble.innerHTML = role === 'user' ? escHtml(text) : formatBotMessage(text);
    wrap.appendChild(bubble);
    container.appendChild(wrap);
    container.scrollTop = container.scrollHeight;
}
function showTyping() {
    const container = document.getElementById('chatContainer');
    const d = document.createElement('div');
    d.id = 'typingIndicator';
    d.innerHTML = '<div class="cb-typing"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>';
    container.appendChild(d);
    container.scrollTop = container.scrollHeight;
}
function removeTyping() {
    const el = document.getElementById('typingIndicator');
    if (el) el.remove();
}

let isSending = false;

async function fetchWithRetry(url, options, maxRetries = 3) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        const res = await fetch(url, options);
        if (res.status !== 429) return res;

        const retryAfter = parseInt(res.headers.get('Retry-After') || '0', 10);
        const waitMs     = retryAfter > 0 ? retryAfter * 1000 : (5000 * attempt);

        if (attempt < maxRetries) {
            appendMessage('assistant',
                `⏳ Gemini đang bận, tự động thử lại sau ${waitMs/1000} giây... (lần ${attempt}/${maxRetries})`);
            await new Promise(r => setTimeout(r, waitMs));
            const msgs = document.querySelectorAll('#chatContainer .cb-bot');
            msgs[msgs.length - 1]?.closest('.d-flex')?.remove();
        }
    }
    return fetch(url, options);
}

async function sendMessage() {
    if (isSending) return;

    const input = document.getElementById('userInput');
    const msg   = input.value.trim();
    if (!msg) return;

    isSending = true;
    input.value = '';
    document.getElementById('sendBtn').disabled = true;

    appendMessage('user', msg);
    chatHistory.push({ role: 'user', content: msg });
    showTyping();

    try {
        const response = await fetchWithRetry(BASE_URL + '/api/chatbot_proxy.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                max_tokens: 1000,
                messages: [
                    { role: 'system', content: systemPrompt },
                    ...chatHistory
                ]
            })
        });

        const rawText = await response.text();
        let data;
        try { data = JSON.parse(rawText); }
        catch (e) {
            console.error('Proxy không trả JSON:', rawText);
            throw new Error('Proxy lỗi');
        }

        removeTyping();

        if (!response.ok) {
            const errMsg = response.status === 429
                ? '⚠️ Gemini đang quá tải. Bạn thử lại sau vài giây nhé!'
                : (data.error?.message || data.error || 'Lỗi gọi API.');
            appendMessage('assistant', errMsg);
        } else {
            const botReply = data.choices?.[0]?.message?.content
                || 'Xin lỗi, tôi không thể trả lời lúc này.';
            chatHistory.push({ role: 'assistant', content: botReply });
            appendMessage('assistant', botReply);
        }

    } catch (err) {
        removeTyping();
        console.error(err);
        appendMessage('assistant', '❌ Lỗi kết nối. Mở Console để xem chi tiết.');
    }

    isSending = false;
    document.getElementById('sendBtn').disabled = false;
    input.focus();
}
function sendQuick(btn) {
    document.getElementById('userInput').value = btn.textContent;
    sendMessage();
}
document.getElementById('userInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') sendMessage();
});
</script>

<?php render_footer(); ?>