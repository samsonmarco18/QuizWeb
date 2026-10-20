<article class="quiz-card assigned-game-card classroom-manual-activity">
    <div class="quiz-card-topline"><span class="game-type-badge"><?php echo esc($card['activity_type']); ?></span><span class="quiz-status<?php echo $card['completed'] ? ' is-completed' : ''; ?>"><?php echo esc($card['status']); ?></span></div>
    <div class="quiz-card-content"><span class="activity-card-symbol" aria-hidden="true"><?php echo nav_icon('document'); ?></span><div class="quiz-card-copy"><h3 class="quiz-card-title"><?php echo esc($card['quiz']['title']); ?></h3><p class="quiz-card-description"><?php echo esc($card['quiz']['description'] ?: 'A classroom activity assessed by your teacher.'); ?></p><div class="card-meta"><span><?php echo nav_icon('star'); ?><?php echo esc((string) $card['points']); ?> pts</span></div></div></div>
    <div class="quiz-card-footer">
        <?php if ($card['score'] !== null): ?><div class="quiz-unplayed"><strong>Published score: <?php echo esc((string) $card['score']); ?> / <?php echo esc((string) $card['points']); ?></strong></div><?php endif; ?>
        <?php if ($card['due'] !== null): ?><div class="quiz-due-date"><?php echo nav_icon('calendar'); ?><span>Due <?php echo esc(date('M j, Y', $card['due'])); ?></span></div><?php endif; ?>
        <div class="quiz-card-actions"><a class="button button-primary" href="<?php echo esc($card['url']); ?>"><?php echo nav_icon('arrow-right'); ?><?php echo $user['role'] === 'teacher' ? 'Manage activity' : 'View activity'; ?></a></div>
    </div>
</article>
