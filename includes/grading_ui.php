<?php

function grade_display($value): string
{
    return $value === null ? '—' : number_format((float) $value, 2, '.', '');
}

function render_grade_breakdown(array $grade, array $config): void
{
    ?>
    <section class="glass panel grade-summary">
        <div><span class="eyebrow">Current overall grade</span><h2><?php echo esc(grade_display($grade['overall'])); ?><?php if ($grade['override']): ?> <small>Manually adjusted</small><?php endif; ?></h2>
        <p><?php echo esc($grade['status'] . ($grade['scale_label'] ? ' · ' . $grade['scale_label'] : '')); ?><?php if ($grade['passed'] !== null): ?> · <?php echo $grade['passed'] ? 'At or above passing grade' : 'Below passing grade'; ?><?php endif; ?></p></div>
        <div><p>Automatically calculated: <strong><?php echo esc(grade_display($grade['calculated'])); ?></strong> · Passing grade: <?php echo esc(grade_display($config['passing'])); ?></p>
        <?php if ($grade['override']): ?><p>Calculation when adjusted: <?php echo esc(grade_display($grade['override']['calculated_at_change'])); ?></p><?php endif; ?>
        <?php if ($grade['override']): ?><p>Adjusted <?php echo esc(format_date($grade['override']['changed_at'])); ?> by teacher #<?php echo (int) $grade['override']['actor_id']; ?><?php echo $grade['override']['reason'] ? ' · ' . esc($grade['override']['reason']) : ''; ?></p><?php endif; ?></div>
    </section>
    <p class="grading-formula">Category = earned ÷ possible points × 100. Overall = weighted category percentages ÷ weight of categories with scores × 100. <?php echo $config['missing_policy'] === 'exclude' ? 'Blank scores are excluded; the current grade uses available category weights.' : 'Blank scores count as zero for assigned activities.'; ?> Empty categories are excluded. Values display to two decimal places; partial grades may change.</p>
    <div class="grade-category-list">
    <?php foreach ($grade['categories'] as $category): ?>
        <details class="glass panel"><summary><strong><?php echo esc($category['name']); ?> · <?php echo esc(grade_display($category['weight'])); ?>%</strong><span><?php echo esc(grade_display($category['percentage'])); ?><?php echo $category['percentage'] !== null ? '%' : ' Not Graded'; ?></span></summary>
            <p>Category: <?php echo esc(grade_display($category['earned']) . ' / ' . grade_display($category['possible'])); ?> points · Configured contribution: <?php echo esc(grade_display($category['contribution'])); ?> · Contribution to current overall: <?php echo esc(grade_display($category['current_contribution'])); ?></p>
            <?php if ($category['items']): ?>
            <table class="grade-table"><caption>Activities in <?php echo esc($category['name']); ?></caption><thead><tr><th scope="col">Activity</th><th scope="col">Score</th><th scope="col">Percent</th><th scope="col">Status</th></tr></thead><tbody>
            <?php foreach ($category['items'] as $item): ?>
                <tr><th scope="row"><?php echo esc($item['name']); ?><small><?php echo $item['source'] === 'quiz' ? esc(ucfirst($item['attempt_policy']) . ' attempt policy · ' . $item['attempt_count'] . ' eligible attempts') : 'Manual activity'; ?></small></th>
                    <td data-label="Score"><?php echo esc(grade_display($item['score']) . ' / ' . grade_display($item['max_score'])); ?><?php if ($item['raw_max'] !== null): ?><small>Quiz result: <?php echo esc($item['raw_score'] . '/' . $item['raw_max']); ?></small><?php endif; ?><?php if ($item['override']): ?><small>Calculated: <?php echo esc(grade_display($item['calculated'])); ?></small><?php endif; ?></td>
                    <td data-label="Percent"><?php echo esc(grade_display($item['percentage'])); ?><?php echo $item['percentage'] !== null ? '%' : ''; ?><?php if ($item['override']): ?><small>Calculation when adjusted: <?php echo esc(grade_display($item['override']['calculated_at_change'])); ?> · Teacher #<?php echo (int) $item['override']['actor_id']; ?></small><?php endif; ?></td>
                    <td data-label="Status"><?php echo esc($item['status']); ?><?php if ($item['override']): ?><small>Manually adjusted · <?php echo esc(format_date($item['override']['changed_at'])); ?><?php echo $item['override']['reason'] ? ' · ' . esc($item['override']['reason']) : ''; ?></small><?php endif; ?></td></tr>
            <?php endforeach; ?></tbody></table>
            <?php else: ?><p>No active activities in this category.</p><?php endif; ?>
        </details>
    <?php endforeach; ?>
    </div>
    <?php
}

function render_grade_form_fields(array $book): void
{
    ?><input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>"><input type="hidden" name="revision" value="<?php echo (int) ($_POST['revision'] ?? $book['revision']); ?>"><?php
}

function render_grading_category_options(array $book, string $selected = ''): void
{
    foreach ($book['config']['categories'] ?? [] as $category) {
        ?><option value="<?php echo esc($category['id']); ?>" <?php echo $selected === $category['id'] ? 'selected' : ''; ?>><?php echo esc($category['name']); ?></option><?php
    }
}
