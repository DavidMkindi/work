<?php
/**
 * Reusable notifications popup for the topbar.
 *
 * A bell button with an unread badge opens a compact panel anchored to the
 * top-right of the page. The panel uses the theme's hs-overlay mechanism so
 * the page background is locked (scroll disabled) while it is open, and it
 * links through to the full notifications page via "View More Notifications".
 *
 * Expected context: current user must be logged in and $connect may already
 * be defined by the including page. Loads backend/config.php if needed.
 */

if (!defined('NOTIFICATIONS_DROPDOWN_LOADED')) {
    define('NOTIFICATIONS_DROPDOWN_LOADED', true);

    $notifCurrentUserId = (int) ($_SESSION['user_id'] ?? 0);

    if (!isset($connect) || !$connect instanceof mysqli) {
        require_once __DIR__ . '/config.php';
    }

    $notifItems = [];
    $notifUnreadCount = 0;

    if ($notifCurrentUserId > 0 && isset($connect) && $connect && !$connect->connect_error) {
        $stmt = $connect->prepare(
            'SELECT id, type, title, message, link, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY is_read ASC, created_at DESC
             LIMIT 5'
        );
        if ($stmt) {
            $stmt->bind_param('i', $notifCurrentUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $notifItems[] = $row;
            }
            $stmt->close();
        }

        $unreadStmt = $connect->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        if ($unreadStmt) {
            $unreadStmt->bind_param('i', $notifCurrentUserId);
            $unreadStmt->execute();
            $unreadStmt->bind_result($notifUnreadCount);
            $unreadStmt->fetch();
            $unreadStmt->close();
        }
    }

    $notifTimeAgo = function (string $datetime): string {
        $ts = strtotime($datetime);
        if (!$ts) {
            return '';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        }
        if ($diff < 604800) {
            return floor($diff / 86400) . 'd ago';
        }
        return date('M d, Y', $ts);
    };
}
?>
<!-- Notifications Topbar Button -->
<div class="topbar-item relative inline-flex">
    <button type="button" id="notifications-dropdown-btn" data-hs-overlay="#notifications-panel" class="btn btn-icon size-8 hover:bg-default-150 transition-all rounded-full relative" title="Notifications" aria-haspopup="dialog" aria-expanded="false">
        <i data-lucide="bell" class="size-5 text-default-700"></i>
        <?php if ($notifUnreadCount > 0): ?>
            <span id="notif-unread-badge" class="absolute top-1.5 end-1 w-2 h-2 rounded-full bg-primary"></span>
        <?php endif; ?>
    </button>
</div>

<!-- Notifications Popup Panel (anchored top-right of the page) -->
<div id="notifications-panel" data-unread-count="<?= (int) $notifUnreadCount ?>" class="hs-overlay hidden fixed top-20 z-80 w-96 end-8 bg-card dark:bg-default-100 hs-overlay-open:flex flex-col rounded-lg shadow-lg border border-default-200 opacity-0 scale-95 hs-overlay-open:opacity-100 hs-overlay-open:scale-100 transition-all duration-300 ease-in-out overflow-hidden" style="max-width: calc(100vw - 2rem)">

    <div id="notif-list">
        <div class="flex items-center justify-between gap-2 px-3 py-3 border-b border-default-200">
            <h6 class="text-sm font-semibold text-default-800">Notifications</h6>
            <div class="flex items-center gap-2">
                <?php if ($notifUnreadCount > 0): ?>
                    <span id="notif-new-badge" class="inline-flex items-center gap-1 text-xs font-medium text-primary bg-primary/10 px-2 py-1 rounded-full">
                        <i data-lucide="circle-dot" class="size-3"></i><span id="notif-new-count"><?= $notifUnreadCount ?></span> new
                    </span>
                <?php endif; ?>
                <button type="button" data-hs-overlay="#notifications-panel" class="btn size-7 rounded-full btn-sm hover:bg-default-150" aria-label="Close notifications">
                    <i data-lucide="x" class="size-4"></i>
                </button>
            </div>
        </div>

        <div class="max-h-64 overflow-y-auto">
            <?php if (empty($notifItems)): ?>
                <div class="px-4 py-8 text-center text-default-500">
                    <i data-lucide="bell-off" class="size-10 mx-auto mb-3 text-default-400"></i>
                    <p class="text-sm font-medium">No notifications yet.</p>
                </div>
            <?php else: foreach ($notifItems as $ntf): ?>
                <button type="button"
                       class="notif-item w-full flex items-start gap-2 px-3 py-2 text-start hover:bg-default-100 transition-all <?= (int) $ntf['is_read'] === 0 ? 'bg-primary/10' : '' ?>"
                       data-id="<?= (int) $ntf['id'] ?>"
                       data-title="<?= htmlspecialchars($ntf['title'], ENT_QUOTES, 'UTF-8') ?>"
                       data-message="<?= htmlspecialchars($ntf['message'], ENT_QUOTES, 'UTF-8') ?>"
                       data-time="<?= htmlspecialchars($notifTimeAgo($ntf['created_at']), ENT_QUOTES, 'UTF-8') ?>"
                       data-link="<?= htmlspecialchars((int) $ntf['is_read'] === 0
                            ? 'backend/mark_notification_read.php?id=' . (int) $ntf['id']
                            : ($ntf['link'] !== '' ? $ntf['link'] : 'notifications.php'), ENT_QUOTES, 'UTF-8') ?>"
                       data-unread="<?= (int) $ntf['is_read'] === 0 ? '1' : '0' ?>">
                    <span class="shrink-0 size-7 rounded-full flex items-center justify-center <?= $ntf['type'] === 'production_job' ? 'bg-info/10 text-info' : 'bg-primary/10 text-primary' ?>">
                        <i data-lucide="<?= $ntf['type'] === 'production_job' ? 'factory' : 'bell-ring' ?>" class="size-3"></i>
                    </span>
                    <span class="flex-1 overflow-hidden" style="min-width: 0">
                        <span class="block text-xs font-medium text-default-800 truncate"><?= htmlspecialchars($ntf['title'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="block text-xs text-default-500 truncate"><?= htmlspecialchars($ntf['message'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="block text-xs text-default-400 mt-1"><?= htmlspecialchars($notifTimeAgo($ntf['created_at']), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                    <?php if ((int) $ntf['is_read'] === 0): ?>
                        <span class="notif-unread-dot shrink-0 w-2 h-2 rounded-full bg-primary mt-2"></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; endif; ?>
        </div>

        <a href="notifications.php" class="flex items-center justify-center gap-2 px-3 py-3 border-t border-default-200 text-xs font-medium text-primary hover:bg-default-100 transition-all">
            View More Notifications
            <i data-lucide="arrow-right" class="size-4"></i>
        </a>
    </div>

    <div id="notif-detail" class="hidden flex-col">
        <div class="flex items-center justify-between gap-2 px-3 py-3 border-b border-default-200">
            <button type="button" id="notif-detail-back" class="btn size-7 rounded-full btn-sm hover:bg-default-150" aria-label="Back to notifications">
                <i data-lucide="arrow-left" class="size-4"></i>
            </button>
            <h6 class="text-sm font-semibold text-default-800">Notification Details</h6>
            <button type="button" data-hs-overlay="#notifications-panel" class="btn size-7 rounded-full btn-sm hover:bg-default-150" aria-label="Close notifications">
                <i data-lucide="x" class="size-4"></i>
            </button>
        </div>
        <div class="px-4 py-4 overflow-y-auto max-h-64">
            <span class="shrink-0 size-8 rounded-full flex items-center justify-center bg-primary/10 text-primary mb-3">
                <i data-lucide="bell-ring" class="size-4"></i>
            </span>
            <h6 id="notif-detail-title" class="text-sm font-semibold text-default-900"></h6>
            <p id="notif-detail-message" class="text-sm text-default-600 mt-2 whitespace-pre-line"></p>
            <span id="notif-detail-time" class="block text-xs text-default-400 mt-3"></span>
        </div>
        <a id="notif-detail-link" href="notifications.php" class="flex items-center justify-center gap-2 px-3 py-3 border-t border-default-200 text-xs font-medium text-primary hover:bg-default-100 transition-all">
            Open Notification Page
            <i data-lucide="external-link" class="size-4"></i>
        </a>
    </div>
</div>

<script>
    (function () {
        var panel = document.getElementById('notifications-panel');
        if (panel && panel.parentNode !== document.body) {
            document.body.appendChild(panel);
        }

        var list = document.getElementById('notif-list');
        var detail = document.getElementById('notif-detail');
        if (!list || !detail) return;

        function updateUnreadCount() {
            var count = parseInt(panel.getAttribute('data-unread-count') || '0', 10);
            count = Math.max(0, count - 1);
            panel.setAttribute('data-unread-count', String(count));
            if (count > 0) {
                var badge = document.getElementById('notif-unread-badge');
                if (!badge) {
                    var btn = document.getElementById('notifications-dropdown-btn');
                    if (btn) {
                        badge = document.createElement('span');
                        badge.id = 'notif-unread-badge';
                        badge.className = 'absolute top-1.5 end-1 w-2 h-2 rounded-full bg-primary';
                        btn.appendChild(badge);
                    }
                }
                var newCount = document.getElementById('notif-new-count');
                if (newCount) newCount.textContent = count;
            } else {
                var badge = document.getElementById('notif-unread-badge');
                if (badge) badge.parentNode.removeChild(badge);
                var newBadge = document.getElementById('notif-new-badge');
                if (newBadge) newBadge.parentNode.removeChild(newBadge);
            }
        }

        document.addEventListener('click', function (e) {
            var item = e.target.closest('.notif-item');
            if (!item) return;

            document.getElementById('notif-detail-title').textContent = item.getAttribute('data-title');
            document.getElementById('notif-detail-message').textContent = item.getAttribute('data-message');
            document.getElementById('notif-detail-time').textContent = item.getAttribute('data-time');

            list.classList.add('hidden');
            detail.classList.remove('hidden');
            detail.classList.add('flex');

            if (item.getAttribute('data-unread') === '1') {
                item.setAttribute('data-unread', '0');
                item.classList.remove('bg-primary/10');
                var dot = item.querySelector('.notif-unread-dot');
                if (dot) dot.parentNode.removeChild(dot);
                fetch('backend/mark_notification_read.php?id=' + encodeURIComponent(item.getAttribute('data-id')) + '&ajax=1', {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(updateUnreadCount).catch(updateUnreadCount);
            }
        });

        var back = document.getElementById('notif-detail-back');
        if (back) back.addEventListener('click', function () {
            detail.classList.add('hidden');
            detail.classList.remove('flex');
            list.classList.remove('hidden');
        });

        if (panel) {
            panel.addEventListener('hs-overlay:close', function () {
                detail.classList.add('hidden');
                detail.classList.remove('flex');
                list.classList.remove('hidden');
            });
        }
    })();
</script>
