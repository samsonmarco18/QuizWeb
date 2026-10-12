<?php
function academic_ui_icon(string $name): string {
    $paths = [
        'book' => '<path d="M5 4h13v16H5z"/><path d="M8 4V2h13v16h-3M8 8h7M8 12h7M8 16h4"/>',
        'send' => '<path d="m3 10 18-7-7 18-3-8-8-3Z"/><path d="m11 13 10-10"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'download' => '<path d="M12 3v12m-4-4 4 4 4-4M4 16v5h16v-5"/>',
        'filter' => '<path d="M3 4h18l-7 8v7l-4 2v-9Z"/>',
    ];
    return '<svg class="grade-ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['book']) . '</svg>';
}

function render_academic_fields(array $values, array $settings): void { ?>
    <div class="split-fields">
        <label><span>Subject code</span><input name="subject_code" maxlength="40" required value="<?php echo esc($values['subject_code'] ?? ''); ?>" placeholder="e.g. IT101"></label>
        <label><span>Section</span><input name="section" maxlength="80" required value="<?php echo esc($values['section'] ?? ''); ?>" placeholder="e.g. BSIT 1A"></label>
        <?php foreach (['academic_year' => ['Academic year', 'years'], 'semester' => ['Semester', 'semesters']] as $key => [$label, $options]): ?>
        <label><span><?php echo esc($label); ?></span><select name="<?php echo esc($key); ?>" required><option value="">Choose <?php echo esc(mb_strtolower($label)); ?></option><?php foreach ($settings[$options] as $option): ?><option value="<?php echo esc($option); ?>" <?php echo ($values[$key] ?? '') === $option ? 'selected' : ''; ?>><?php echo esc($option); ?></option><?php endforeach; ?></select></label>
        <?php endforeach; ?>
        <label><span>Credit units (optional for semester mean)</span><input name="credit_units" type="number" min="0.01" max="100" step="0.01" value="<?php echo esc((string) ($values['credit_units'] ?? '')); ?>"></label>
        <label><span>Required for semester result</span><select name="required"><option value="yes">Required subject</option><option value="no" <?php echo empty($values['required'] ?? true) ? 'selected' : ''; ?>>Optional / excluded from semester result</option></select></label>
    </div>
<?php }
function render_period_options(string $selected = ''): void { ?>
    <option value="">Choose grading period</option><?php foreach (academic_periods() as $period): ?><option value="<?php echo esc($period['id']); ?>" <?php echo $period['id'] === $selected ? 'selected' : ''; ?>><?php echo esc($period['name']); ?></option><?php endforeach; ?>
<?php }
