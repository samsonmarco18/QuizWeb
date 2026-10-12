<div class="academic-result-line"><span>Final grade</span><strong><?php echo esc(grade_display($compactGrade['overall'] ?? null)); ?></strong></div>
<p class="academic-result-status"><?php echo !$compactGrade ? 'Awaiting published grades' : esc($compactGrade['status'] . (!empty($compactGrade['scale_label']) ? ' ? ' . $compactGrade['scale_label'] : '')); ?></p>
<section class="academic-compact-components"><h3>Components</h3><?php render_grade_components($compactGrade, $compactConfig); ?></section>
