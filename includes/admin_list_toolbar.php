<?php
if (empty($adminToolbar) || !is_array($adminToolbar)) {
    return;
}
$tb = $adminToolbar;
?>
<form method="GET" class="filters-bar admin-filters-bar" action="<?= escape($tb['action'] ?? '') ?>">
    <?php foreach ($tb['hidden'] ?? [] as $hiddenName => $hiddenValue): ?>
        <input type="hidden" name="<?= escape((string)$hiddenName) ?>" value="<?= escape((string)$hiddenValue) ?>">
    <?php endforeach; ?>
    <?php if (!empty($tb['search'])): ?>
        <div class="filters-bar__item filters-bar__grow">
            <label class="sr-only" for="admin-list-q">Поиск</label>
            <input type="search" id="admin-list-q" name="q" placeholder="<?= escape($tb['search']['placeholder'] ?? 'Поиск') ?>" value="<?= escape($tb['search']['value'] ?? '') ?>">
        </div>
    <?php endif; ?>
    <?php foreach ($tb['filters'] ?? [] as $filterIndex => $filter): ?>
        <div class="filters-bar__item">
            <?php if (!empty($filter['label'])): ?>
                <label class="filters-bar__label" for="admin-list-f<?= (int)$filterIndex ?>"><?= escape($filter['label']) ?></label>
            <?php endif; ?>
            <select id="admin-list-f<?= (int)$filterIndex ?>" name="<?= escape($filter['name']) ?>">
                <?php foreach ($filter['options'] as $optionValue => $optionLabel): ?>
                    <option value="<?= escape((string)$optionValue) ?>" <?= (string)($filter['value'] ?? '') === (string)$optionValue ? 'selected' : '' ?>>
                        <?= escape($optionLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>
    <?php if (!empty($tb['sort'])): ?>
        <div class="filters-bar__item">
            <label class="sr-only" for="admin-list-sort">Сортировка</label>
            <select id="admin-list-sort" name="sort">
                <?php foreach ($tb['sort']['options'] as $sortKey => $sortLabel): ?>
                    <option value="<?= escape($sortKey) ?>" <?= ($tb['sort']['value'] ?? '') === $sortKey ? 'selected' : '' ?>>
                        <?= escape($sortLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <?php if (!empty($tb['per'])): ?>
        <div class="filters-bar__item">
            <label class="sr-only" for="admin-list-per">На странице</label>
            <select id="admin-list-per" name="per">
                <?php foreach ([10, 25, 50, 100] as $perOption): ?>
                    <option value="<?= $perOption ?>" <?= (int)($tb['per']['value'] ?? 25) === $perOption ? 'selected' : '' ?>><?= $perOption ?> на стр.</option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="filters-bar__actions">
        <button type="submit" class="btn">Применить</button>
        <?php if (!empty($tb['reset_url'])): ?>
            <a class="btn btn-secondary" href="<?= escape($tb['reset_url']) ?>">Сбросить</a>
        <?php endif; ?>
    </div>
</form>
<?php if (isset($tb['count'])): ?>
    <p class="results-count"><?= escape($tb['count_label'] ?? 'Найдено') ?>: <?= (int)$tb['count'] ?></p>
<?php endif; ?>
