<?php
try { $aiConfig = ai_quiz_config(); $aiReady = $aiConfig['enabled'] && $aiConfig['key'] !== ''; }
catch (Throwable $error) { $aiReady = false; }
unset($aiConfig);
$aiRemaining = null;
try { $aiRemaining = ai_quiz_remaining(db(), (int) $user['id']); }
catch (Throwable $error) { /* Do not invent an allowance if usage cannot be read. */ }
?>
<details class="ai-quiz-panel" id="ai-quiz-panel" data-classroom-id="<?php echo $classroomId; ?>" data-ready="<?php echo $aiReady ? 'true' : 'false'; ?>">
    <summary>Generate with AI <span class="ai-quiz-tag">Gemini</span></summary>
    <div class="ai-quiz-content">
        <p id="ai-quiz-description">Create drafts from your lesson for the selected activity. Review the answers before adding them.</p>
        <?php if (!$aiReady): ?><p class="ai-quiz-setup">AI generation is unavailable. Ask your administrator to configure and enable Gemini in Administration → AI settings.</p><?php endif; ?>
        <p id="ai-quiz-mode-note" hidden></p>
        <label for="ai-quiz-source"><span>Lesson text</span><textarea id="ai-quiz-source" rows="5" placeholder="Paste your lesson here, or attach a file below. Maximum 60 KB."></textarea></label>
        <label for="ai-quiz-file"><span>Lesson attachment (optional)</span><input id="ai-quiz-file" type="file" accept=".pdf,.txt,application/pdf,text/plain"><small>PDF up to 5 MB; UTF-8 TXT up to 60 KB. Convert Word or PowerPoint to PDF first.</small></label>
        <div class="ai-quiz-fields">
            <label for="ai-quiz-count"><span id="ai-quiz-count-label">Questions</span><input id="ai-quiz-count" type="number" min="3" max="20" value="6"></label>
            <label for="ai-quiz-difficulty"><span>Difficulty</span><select id="ai-quiz-difficulty"><option value="mixed">Mixed (adaptive practice)</option><option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option></select></label>
            <label for="ai-quiz-language"><span>Language</span><select id="ai-quiz-language"><option>English</option><option>Filipino</option></select></label>
            <label for="ai-quiz-topic"><span>Focus topic (optional)</span><input id="ai-quiz-topic" maxlength="100" placeholder="Use the lesson's topics"></label>
        </div>
        <p class="ai-quiz-privacy">The text and attachment you select are sent to Google. Free-tier content may be used to improve its products. Use lesson materials without student personal information.</p>
        <div class="action-row"><button id="ai-quiz-generate" type="button" class="button button-secondary" <?php echo $aiRemaining === 0 ? 'disabled' : ''; ?>>Generate draft</button><small class="ai-quiz-usage"><strong id="ai-quiz-remaining" data-remaining="<?php echo $aiRemaining === null ? '' : $aiRemaining; ?>" role="status" aria-live="polite"><?php echo $aiRemaining === null ? 'Attempts unavailable' : $aiRemaining . ' / 10 attempts left today'; ?></strong><span>Resets at midnight (Manila). Google quotas also apply.</span></small></div>
        <div id="ai-quiz-progress" class="ai-quiz-progress" hidden>
            <div class="ai-quiz-progress-heading"><strong id="ai-quiz-progress-label" role="status" aria-live="polite">Generating your draft…</strong><small id="ai-quiz-elapsed" aria-live="off">0s elapsed</small></div>
            <div id="ai-quiz-progress-bar" class="ai-quiz-progress-track" role="progressbar" aria-label="Quiz generation" aria-valuetext="Waiting for the generator"><span></span></div>
            <ol class="ai-quiz-progress-steps" aria-label="Generation stages"><li data-ai-stage="0">Generate draft</li><li data-ai-stage="1">Read response</li><li data-ai-stage="2">Ready to review</li></ol>
            <small id="ai-quiz-progress-note">You can keep editing while the generator works.</small>
        </div>
        <p id="ai-quiz-status" role="status" aria-live="polite"></p>
        <section id="ai-quiz-draft" hidden aria-label="Generated questions to review">
            <strong>Review generated questions</strong>
            <p>Check every answer against your lesson. Added questions can be edited in the question editor and start at 10 points each.</p>
            <div id="ai-quiz-preview"></div>
            <div class="action-row"><button id="ai-quiz-add" type="button" class="button button-primary">Add questions to quiz</button><button id="ai-quiz-discard" type="button" class="button button-ghost">Discard AI draft</button></div>
        </section>
    </div>
</details>
