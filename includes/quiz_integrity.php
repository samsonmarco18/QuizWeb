<?php

const QUIZ_INTEGRITY_MAX_WARNINGS = 3;

// Pure state transition: sessions serialize requests, event IDs deduplicate
// retries, and starting again never clears previous warnings.
function quiz_integrity_event(array $run, string $action, string $eventId, string $reason = ''): array
{
    if (isset($run['attempt_id'])) return $run;
    if (!in_array($action, ['start', 'violation', 'abandon'], true)
        || !preg_match('/^[a-zA-Z0-9_-]{16,80}$/D', $eventId)) {
        throw new InvalidArgumentException('Invalid quiz integrity event.');
    }
    $state = $run['integrity'] ?? ['started_at' => null, 'warnings' => 0, 'disqualified' => false, 'events' => []];
    foreach ($state['events'] as $event) if ($event['id'] === $eventId) return $run;
    if ($action === 'start') {
        $state['started_at'] ??= time();
    } else {
        if (!$state['started_at']) throw new InvalidArgumentException('Start the quiz before reporting a violation.');
        if (!in_array($reason, ['fullscreen_exit', 'focus_loss', 'tab_hidden', 'screenshot_shortcut', 'page_exit'], true)) {
            throw new InvalidArgumentException('Invalid quiz integrity reason.');
        }
        if ($state['disqualified']) return $run;
        $state['warnings']++;
        $state['reason'] = $reason;
        $state['disqualified'] = $action === 'abandon' || $state['warnings'] > QUIZ_INTEGRITY_MAX_WARNINGS;
    }
    $state['events'][] = ['id' => $eventId, 'action' => $action, 'reason' => $reason, 'at' => time()];
    $state['events'] = array_slice($state['events'], -20);
    $run['integrity'] = $state;
    return $run;
}

function quiz_integrity_metadata(array $run): array
{
    $state = $run['integrity'] ?? [];
    return [
        '_violations' => (int) ($state['warnings'] ?? 0),
        '_integrity_events' => $state['events'] ?? [],
        '_reason' => $state['reason'] ?? '',
    ];
}
