<?php
$savedAI = ai_settings_read(db());
$serverAI = $savedAI === null ? ai_quiz_server_config() : null;
$hasAIKey = $savedAI === null ? $serverAI['key'] !== '' : !empty($savedAI['encrypted_key']);
$aiEnabled = $savedAI === null ? $hasAIKey : !empty($savedAI['enabled']);
$aiModel = $savedAI['model'] ?? $serverAI['model'] ?? 'gemini-3.8-flash';
$aiBackupModel = $savedAI['backup_model'] ?? $serverAI['backup_model'] ?? 'gemini-3.5-flash-lite';
unset($serverAI);
$aiHealthError = '';
if ($hasAIKey) {
    try { $check = ai_quiz_config(db(), true); unset($check); }
    catch (Throwable $exception) { $aiHealthError = 'The saved API key cannot be read. Replace the key or restore the server encryption secret.'; }
}
?>
<div class="admin-ai-settings">
    <div class="admin-ai-heading"><div><h2>AI quiz generator</h2><p>Configure Gemini once for all teachers. Generated questions stay editable in the Quiz Builder.</p></div><span class="admin-ai-status"><?php echo $aiEnabled && $hasAIKey && !$aiHealthError ? 'Enabled' : 'Disabled / Setup needed'; ?></span></div>
    <div class="admin-ai-summary"><span>API key: <strong><?php echo $hasAIKey ? '•••••••• (configured)' : 'Not configured'; ?></strong></span><span>Configuration: <strong><?php echo $savedAI === null ? 'Server defaults' : 'Administration'; ?></strong></span><?php if (!empty($savedAI['updated_at'])): ?><span>Updated <?php echo esc(format_date($savedAI['updated_at'])); ?></span><?php endif; ?></div>
    <?php if ($aiHealthError): ?><p class="inline-error" role="alert"><?php echo esc($aiHealthError); ?></p><?php endif; ?>
    <form method="post" class="stack-form admin-ai-form" autocomplete="off" data-ai-settings-form>
        <input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>">
        <input type="hidden" name="action" value="ai_save_settings">
        <div class="admin-ai-fields">
            <label for="admin-gemini-key"><span><?php echo $hasAIKey ? 'Replace API key (optional)' : 'Gemini API key'; ?></span><input id="admin-gemini-key" type="password" name="api_key" value="" maxlength="4096" autocomplete="new-password" spellcheck="false" placeholder="<?php echo $hasAIKey ? 'Leave blank to keep the current key' : 'Paste your Google AI Studio API key'; ?>"><small>Accepts standard and authorization keys. The saved key is encrypted and never displayed again.</small></label>
            <label for="admin-gemini-model"><span>Gemini model</span><input id="admin-gemini-model" name="model" maxlength="87" value="<?php echo esc($aiModel); ?>" required pattern="gemini-[a-zA-Z0-9.\-]+"><small>Default: gemini-3.8-flash. Check free-tier availability before changing models.</small></label>
            <label for="admin-gemini-backup"><span>Backup model (optional)</span><input id="admin-gemini-backup" name="backup_model" maxlength="87" value="<?php echo esc($aiBackupModel); ?>" pattern="gemini-[a-zA-Z0-9.\-]+"><small>Default: gemini-3.5-flash-lite. Used when the primary model is busy or times out. Leave blank to disable.</small></label>
        </div>
        <label class="admin-ai-toggle"><input type="checkbox" name="enabled" value="yes" <?php echo $aiEnabled ? 'checked' : ''; ?>><span>Enable AI quiz generation for teachers</span></label>
        <p class="admin-ai-note">Use a Google project on the Free tier without paid billing to avoid charges. QuizWeb does not control Google's billing. Teachers receive 20 generation requests per day, with provider quotas also applying.</p>
        <div class="action-row"><button type="submit" class="button button-primary">Save AI settings</button><a href="https://aistudio.google.com/api-keys" target="_blank" rel="noopener noreferrer">Get a Gemini API key</a></div>
        <p role="status" data-ai-form-status></p>
    </form>
    <div class="admin-ai-actions">
        <form method="post" data-ai-settings-form><input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>"><input type="hidden" name="action" value="ai_test_connection"><button type="submit" class="button button-secondary" <?php echo !$hasAIKey || $aiHealthError ? 'disabled' : ''; ?>>Test saved connection</button><p role="status" data-ai-form-status></p></form>
        <form method="post" data-ai-settings-form><input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>"><input type="hidden" name="action" value="ai_test_generation"><button type="submit" class="button button-secondary" <?php echo !$hasAIKey || $aiHealthError ? 'disabled' : ''; ?>>Test quiz generation</button><p role="status" data-ai-form-status></p></form>
        <form method="post" data-ai-settings-form data-ai-remove><input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>"><input type="hidden" name="action" value="ai_remove_key"><button type="submit" class="button button-ghost" <?php echo !$hasAIKey ? 'disabled' : ''; ?>>Remove key &amp; disable AI</button><p role="status" data-ai-form-status></p></form>
    </div>
    <p class="admin-ai-note">Save changes before testing. Connection checks model access only. Test quiz generation creates and validates 3 sample questions without saving a quiz. It uses Google's project quota, including a backup call if needed, and does not use a teacher's daily allowance. Neither test verifies free-tier status.</p>
</div>
