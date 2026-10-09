<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_role('admin'); $settings = academic_settings(); $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_form_csrf();
    try {
        $input = ['years' => preg_split('/\R/u', trim((string) ($_POST['years'] ?? ''))), 'semesters' => preg_split('/\R/u', trim((string) ($_POST['semesters'] ?? ''))),
            'average_method' => $_POST['average_method'] ?? '', 'status_policy' => $_POST['status_policy'] ?? [], 'gpa_scale' => []];
        foreach (array_slice((array) ($_POST['gpa_bands'] ?? []), 0, 12) as $band) {
            if (!is_array($band)) throw new InvalidArgumentException('Choose valid GPA bands.');
            if (trim((string) ($band['min'] ?? '')) === '' && trim((string) ($band['value'] ?? '')) === '') continue;
            $input['gpa_scale'][] = ['min' => $band['min'] ?? '', 'value' => $band['value'] ?? ''];
        }
        $validated = academic_validate_settings($input); $pdo = db(); $pdo->beginTransaction();
        database_upsert_record($pdo, 'academic_settings', 'id', ['id' => 'institution', 'data' => db_json_encode($validated)]);
        record_audit('academic_settings_updated', (int) $user['id']); $pdo->commit();
        flash_set('success', 'Academic terms and semester rules saved.'); redirect('/QuizWeb/academic_settings.php');
    } catch (Throwable $ex) { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); $error = $ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Settings could not be saved.'; }
}
render_header('Academic Settings', 'gradebook-page', ['/QuizWeb/assets/css/gradebook.css']);
?>
<header class="page-heading"><div><span class="eyebrow">Administration</span><h1>Academic settings</h1><p>Configure academic terms and how semester results are calculated.</p></div><a class="button button-secondary" href="/QuizWeb/admin.php">Back to Administration</a></header>
<?php if ($error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endif; ?>
<section class="glass panel"><form method="post" class="stack-form"><input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>">
<div class="split-fields"><label><span>Academic years (one per line)</span><textarea name="years" rows="5" required><?php echo esc($_POST['years'] ?? implode("\n", $settings['years'])); ?></textarea></label><label><span>Semesters (one per line)</span><textarea name="semesters" rows="5" required><?php echo esc($_POST['semesters'] ?? implode("\n", $settings['semesters'])); ?></textarea></label></div>
<label><span>Semester calculation</span><select name="average_method"><option value="mean">Simple mean of final subject grades</option><option value="credit_gpa" <?php echo ($_POST['average_method'] ?? $settings['average_method']) === 'credit_gpa' ? 'selected' : ''; ?>>Institutional GPA weighted by credit units</option></select></label>
<details><summary>Optional institutional GPA scale</summary><p>Enter the institution?s approved percentage thresholds and matching GPA values. Leave unused rows blank.</p><?php $bands = $_POST['gpa_bands'] ?? $settings['gpa_scale']; for ($index = 0; $index < max(8, count($bands)); $index++): ?><div class="split-fields"><label><span>Minimum percentage</span><input type="number" name="gpa_bands[<?php echo $index; ?>][min]" min="0" max="100" step="0.01" value="<?php echo esc((string) ($bands[$index]['min'] ?? '')); ?>"></label><label><span>GPA value</span><input type="number" name="gpa_bands[<?php echo $index; ?>][value]" min="0" max="10" step="0.01" value="<?php echo esc((string) ($bands[$index]['value'] ?? '')); ?>"></label></div><?php endfor; ?></details>
<div class="split-fields"><?php foreach (['incomplete', 'withdrawn'] as $status): ?><label><span><?php echo esc(ucfirst($status)); ?> subject policy</span><select name="status_policy[<?php echo esc($status); ?>]"><?php foreach (['pending' => 'Keep semester result Pending', 'exclude' => 'Exclude subject from semester result', 'zero' => 'Include as zero'] as $value => $label): ?><option value="<?php echo esc($value); ?>" <?php echo ($_POST['status_policy'][$status] ?? $settings['status_policy'][$status]) === $value ? 'selected' : ''; ?>><?php echo esc($label); ?></option><?php endforeach; ?></select></label><?php endforeach; ?></div>
<button class="button button-primary">Save Academic Settings</button></form></section>
<?php render_footer(); ?>
