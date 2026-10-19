<?php
$creationDraft = is_array($_POST['creation_config'] ?? null) ? $_POST['creation_config'] : grading_templates()['balanced']['config'];
?>
<form method="post" class="stack-form" id="class-creation">
    <input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>">
    <input type="hidden" name="action" value="create_classroom">
    <?php foreach ($errors as $error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endforeach; ?>
    <nav class="builder-steps" aria-label="Class creation steps" data-class-navigation hidden>
        <?php foreach (['Class details', 'Academic details', 'Grade configuration', 'Review'] as $index => $label): ?><button type="button" data-class-step="<?php echo $index; ?>"><?php echo ($index + 1) . '. ' . $label; ?></button><?php endforeach; ?>
    </nav>
    <p data-class-status role="status" aria-live="polite"></p>
    <section data-class-panel="0" class="stack-form">
        <h3 tabindex="-1">Class details</h3>
        <label><span>Classroom name</span><input name="name" maxlength="190" required placeholder="Grade 10 Science" value="<?php echo esc($_POST['name'] ?? ''); ?>"></label>
        <label><span>Subject</span><input name="subject" maxlength="190" required placeholder="Earth and Life Science" value="<?php echo esc($_POST['subject'] ?? ''); ?>"></label>
        <label><span>Description</span><textarea name="description" rows="3" placeholder="Add a short classroom description"><?php echo esc($_POST['description'] ?? ''); ?></textarea></label>
        <label><span>Assigned teacher</span><input readonly value="<?php echo esc($user['name']); ?>"></label>
    </section>
    <section data-class-panel="1" class="stack-form">
        <h3 tabindex="-1">Academic details</h3>
        <?php render_academic_fields($_POST, academic_settings()); ?>
        <p class="muted">Academic years and semesters are configured by your administrator.</p>
    </section>
    <section data-class-panel="2" class="stack-form">
        <h3 tabindex="-1">Grade configuration</h3>
        <label><span>Grading template</span><select name="grading_template"><?php foreach (grading_templates() as $id => $template): ?><option value="<?php echo esc($id); ?>" <?php echo ($_POST['grading_template'] ?? 'balanced') === $id ? 'selected' : ''; ?>><?php echo esc($template['name']); ?></option><?php endforeach; ?><option value="custom" <?php echo ($_POST['grading_template'] ?? '') === 'custom' ? 'selected' : ''; ?>>Custom</option></select></label>
        <p class="muted">Templates fill in starting values. Edit the category names, weights, periods, and rules below.</p>
        <fieldset class="stack-form"><legend>Category weights (total 100%)</legend>
        <?php foreach ($creationDraft['categories'] ?? [] as $i => $category): ?>
            <input type="hidden" name="creation_config[categories][<?php echo (int) $i; ?>][id]" value="<?php echo esc($category['id']); ?>">
            <div class="split-fields"><label><span>Category name</span><input name="creation_config[categories][<?php echo (int) $i; ?>][name]" maxlength="50" required value="<?php echo esc($category['name']); ?>"></label><label><span>Weight (%)</span><input type="number" name="creation_config[categories][<?php echo (int) $i; ?>][weight]" min="0" max="100" step="0.01" required value="<?php echo esc((string) $category['weight']); ?>"></label></div>
        <?php endforeach; ?></fieldset>
        <fieldset class="stack-form"><legend>Grading periods (total 100%)</legend>
        <?php foreach ($creationDraft['periods'] ?? academic_periods() as $i => $period): ?>
            <input type="hidden" name="creation_config[periods][<?php echo (int) $i; ?>][id]" value="<?php echo esc($period['id']); ?>">
            <input type="hidden" name="creation_config[periods][<?php echo (int) $i; ?>][name]" value="<?php echo esc($period['name']); ?>">
            <label><span><?php echo esc($period['name']); ?> (%)</span><input type="number" name="creation_config[periods][<?php echo (int) $i; ?>][weight]" min="0" max="100" step="0.01" required value="<?php echo esc((string) $period['weight']); ?>"></label>
        <?php endforeach; ?></fieldset>
        <label><span>Passing grade (%)</span><input name="creation_config[passing]" type="number" min="0" max="100" step="0.01" required value="<?php echo esc((string) ($creationDraft['passing'] ?? 75)); ?>"></label>
        <label><span>Missing scores</span><select name="creation_config[missing_policy]"><option value="exclude">Exclude ungraded scores</option><option value="zero" <?php echo ($creationDraft['missing_policy'] ?? '') === 'zero' ? 'selected' : ''; ?>>Count assigned missing scores as zero</option></select></label>
        <fieldset class="stack-form"><legend>Grade scale</legend><p>Band minimums must be unique and start at 0.</p>
        <?php foreach ($creationDraft['scale'] ?? [] as $i => $band): ?><div class="split-fields"><label><span>Minimum (%)</span><input name="creation_config[scale][<?php echo (int) $i; ?>][min]" type="number" min="0" max="100" step="0.01" required value="<?php echo esc((string) $band['min']); ?>"></label><label><span>Label</span><input name="creation_config[scale][<?php echo (int) $i; ?>][label]" maxlength="50" required value="<?php echo esc($band['label']); ?>"></label></div><?php endforeach; ?></fieldset>
        <p data-class-grade-error class="inline-error" role="alert" hidden></p>
    </section>
    <section data-class-panel="3" class="stack-form">
        <h3 tabindex="-1">Review your classroom</h3>
        <div data-class-review></div>
        <p>Your classroom and grade configuration will be saved together. You can share the join code after creation.</p>
        <button class="button button-primary" type="submit">Create Classroom</button>
    </section>
    <div class="action-row" data-class-navigation hidden><button type="button" class="button button-secondary" data-class-back>Back</button><button type="button" class="button button-primary" data-class-next>Next</button></div>
</form>
<script>window.classCreationTemplates = <?php echo json_encode(grading_templates(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
