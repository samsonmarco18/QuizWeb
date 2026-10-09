<?php
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
