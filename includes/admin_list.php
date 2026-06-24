<?php
// Общие поиск, фильтры, сортировка и пагинация для админ-списков.

function adminListSearch(string $key = 'q'): string {
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : '';
}

/** @return array{0: string, 1: string} [ключ сортировки, SQL ORDER BY] */
function adminListSort(string $default, array $options): array {
    $sort = isset($_GET['sort']) ? (string)$_GET['sort'] : $default;
    if (!isset($options[$sort])) {
        $sort = $default;
    }
    return [$sort, $options[$sort]];
}

function adminListFilter(string $key, string $default, array $allowed): string {
    $value = isset($_GET[$key]) ? (string)$_GET[$key] : $default;
    return array_key_exists($value, $allowed) ? $value : $default;
}

function adminListPage(): int {
    return max(1, (int)($_GET['page'] ?? 1));
}

function adminListPerPage(int $default = 25): int {
    $per = (int)($_GET['per'] ?? $default);
    return in_array($per, [10, 25, 50, 100, 200], true) ? $per : $default;
}

function adminApplySearch(array $likeColumns, string $search, array &$where, array &$params): void {
    if ($search === '') {
        return;
    }
    $parts = [];
    foreach ($likeColumns as $col) {
        $parts[] = "({$col} LIKE ?)";
        $params[] = '%' . $search . '%';
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}

function adminListUrl(string $relativePath, array $overrides = []): string {
    $query = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    unset($query['page']);
    $qs = http_build_query($query);
    return APP_URL . $relativePath . ($qs !== '' ? '?' . $qs : '');
}

function adminListPageUrl(string $relativePath, int $page, array $overrides = []): string {
    $overrides['page'] = $page;
    $query = array_merge($_GET, $overrides);
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        }
    }
    if (($query['page'] ?? 1) <= 1) {
        unset($query['page']);
    }
    $qs = http_build_query($query);
    return APP_URL . $relativePath . ($qs !== '' ? '?' . $qs : '');
}

function adminRenderPagination(int $total, int $page, int $perPage, string $relativePath, array $preserve = []): string {
    if ($total <= $perPage) {
        return '';
    }
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $baseOverrides = $preserve;

    ob_start();
    ?>
    <nav class="pagination pagination--tight" aria-label="Страницы списка">
        <?php if ($page > 1): ?>
            <a href="<?= escape(adminListPageUrl($relativePath, $page - 1, $baseOverrides)) ?>" class="pagination__arrow" aria-label="Предыдущая страница">←</a>
        <?php else: ?>
            <span class="disabled pagination__arrow" aria-hidden="true">←</span>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i === $page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= escape(adminListPageUrl($relativePath, $i, $baseOverrides)) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?= escape(adminListPageUrl($relativePath, $page + 1, $baseOverrides)) ?>" class="pagination__arrow" aria-label="Следующая страница">→</a>
        <?php else: ?>
            <span class="disabled pagination__arrow" aria-hidden="true">→</span>
        <?php endif; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
}
