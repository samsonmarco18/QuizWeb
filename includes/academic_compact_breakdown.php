<div class="academic-result-line"><span>Final grade</span><strong><?php echo esc(grade_display($compactGrade['overall'] ?? null)); ?></strong></div>
<p class="academic-result-status"><?php echo !$compactGrade ? 'Awaiting published grades' : esc($compactGrade['status'] . (!empty($compactGrade['scale_label']) ? ' | ' . $compactGrade['scale_label'] : '')); ?></p>
<?php if (!empty($compactConfig['periods'])): $selectedPeriod = academic_selected_period(); ?>
<section class="academic-compact-components" data-grade-periods><h3>Grading components</h3>
<div class="academic-period-tabs" role="group" aria-label="Grading period"><?php foreach ($compactConfig['periods'] as $period): ?><button type="button" data-grade-period="<?php echo esc($period['id']); ?>" aria-pressed="<?php echo $selectedPeriod === $period['id'] ? 'true' : 'false'; ?>"><?php echo esc($period['name']); ?></button><?php endforeach; ?></div>
<?php foreach ($compactConfig['periods'] as $period): $periodGrade = $compactGrade['periods'][$period['id']] ?? null; ?>
<div data-grade-period-panel="<?php echo esc($period['id']); ?>" <?php echo $selectedPeriod !== $period['id'] ? 'hidden' : ''; ?>>
<p class="academic-period-label"><?php echo esc($period['name']); ?> period ? <strong><?php echo esc(grade_display($periodGrade['overall'] ?? null)); ?></strong></p>
<?php render_grade_components($periodGrade, $compactConfig, true); ?></div>
<?php endforeach; ?></section>
<?php else: ?><section class="academic-compact-components"><h3>Components</h3><?php render_grade_components($compactGrade, $compactConfig, true); ?></section><?php endif; ?>
