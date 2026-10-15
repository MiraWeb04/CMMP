<?php
declare(strict_types=1);

$scheduleMonth = $scheduleMonth ?? [];
$scheduleItems = $scheduleItems ?? [];
$scheduleDays = $scheduleDays ?? [];
$schedulePath = $schedulePath ?? '';
$scheduleParams = $scheduleParams ?? [];
$today = date('Y-m-d');
$weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$leading = (int) ($scheduleMonth['leading'] ?? 0);
$dayCount = (int) ($scheduleMonth['days'] ?? 0);
$trailing = (7 - (($leading + $dayCount) % 7)) % 7;
$scheduleCount = count($scheduleItems);
$upcoming = null;
$now = date('Y-m-d H:i:s');
foreach ($scheduleItems as $item) {
    if ($item['start'] >= $now) {
        $upcoming = $item;
        break;
    }
}
$kindClass = static function (string $kind): string {
    return match ($kind) {
        'Service' => 'is-service',
        'Appointment' => 'is-appointment',
        default => 'is-event',
    };
};
?>
<section class="sched">
    <header class="sched-hero">
        <div>
            <p class="sched-kicker"><?= e($scheduleLead ?? 'Schedule') ?></p>
            <h1><?= e($scheduleHeading ?? 'Schedule') ?></h1>
            <p><?= e((string) ($scheduleMonth['label'] ?? '')) ?></p>
        </div>
        <div class="sched-stats">
            <div class="sched-stat">
                <strong><?= $scheduleCount ?></strong>
                <span><?= $scheduleCount === 1 ? 'Item this month' : 'Items this month' ?></span>
            </div>
            <div class="sched-stat">
                <strong><?= $upcoming ? e(date('M j', strtotime($upcoming['start']))) : 'None' ?></strong>
                <span><?= $upcoming ? 'Next on the calendar' : 'Nothing upcoming' ?></span>
            </div>
        </div>
    </header>
    <div class="sched-layout">
        <div class="sched-calendar">
            <div class="sched-toolbar">
                <div class="sched-nav">
                    <a class="sched-icon" href="<?= e(personnel_schedule_url($schedulePath, $scheduleParams, (string) $scheduleMonth['prev'])) ?>" aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></a>
                    <div>
                        <strong><?= e((string) $scheduleMonth['label']) ?></strong>
                        <span><?= $scheduleCount === 1 ? '1 scheduled item' : $scheduleCount . ' scheduled items' ?></span>
                    </div>
                    <a class="sched-icon" href="<?= e(personnel_schedule_url($schedulePath, $scheduleParams, (string) $scheduleMonth['next'])) ?>" aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></a>
                </div>
                <a class="sched-today" href="<?= e(personnel_schedule_url($schedulePath, $scheduleParams, date('Y-m'))) ?>">Today</a>
            </div>
            <div class="sched-board">
                <div class="sched-weekdays">
                    <?php foreach ($weekdays as $weekday): ?><span><?= e($weekday) ?></span><?php endforeach; ?>
                </div>
                <div class="sched-grid">
                    <?php for ($blank = 0; $blank < $leading; $blank++): ?>
                        <div class="sched-day is-outside"></div>
                    <?php endfor; ?>
                    <?php for ($day = 1; $day <= $dayCount; $day++): ?>
                        <?php
                        $date = sprintf('%s-%02d', $scheduleMonth['key'], $day);
                        $dayItems = $scheduleDays[$date] ?? [];
                        ?>
                        <div class="sched-day<?= $date === $today ? ' is-today' : '' ?>">
                            <span class="sched-num"><?= $day ?></span>
                            <?php foreach (array_slice($dayItems, 0, 2) as $item): ?>
                                <?php $completed = strtoupper((string) $item['status']) === 'COMPLETED'; ?>
                                <span class="sched-pill <?= e($kindClass((string) $item['kind'])) ?><?= $completed ? ' is-completed' : '' ?>" title="<?= e($item['time'] . ' ' . $item['title'] . ($completed ? ' · Completed' : '')) ?>"><?= $completed ? 'Completed · ' : '' ?><?= e($item['time']) ?> <?= e($item['title']) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($dayItems) > 2): ?>
                                <span class="sched-more">+<?= count($dayItems) - 2 ?> more</span>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                    <?php for ($blank = 0; $blank < $trailing; $blank++): ?>
                        <div class="sched-day is-outside"></div>
                    <?php endfor; ?>
                </div>
            </div>
            <ul class="sched-legend">
                <li><i class="is-service"></i>Service</li>
                <li><i class="is-appointment"></i>Appointment</li>
                <li><i class="is-event"></i>Event</li>
            </ul>
        </div>
        <aside class="sched-agenda">
            <h2>Agenda</h2>
            <?php if ($scheduleItems === []): ?>
                <div class="sched-empty">
                    <i class="fa-regular fa-calendar"></i>
                    Nothing is scheduled for <?= e((string) $scheduleMonth['label']) ?>.
                </div>
            <?php else: ?>
                <div class="sched-list">
                    <?php foreach ($scheduleItems as $item): ?>
                        <?php
                        $class = $kindClass((string) $item['kind']);
                        $completed = strtoupper((string) $item['status']) === 'COMPLETED';
                        ?>
                        <article class="sched-item<?= $completed ? ' is-completed' : '' ?>">
                            <time class="sched-date" datetime="<?= e($item['date']) ?>">
                                <strong><?= e(date('j', strtotime($item['start']))) ?></strong>
                                <span><?= e(date('M', strtotime($item['start']))) ?></span>
                            </time>
                            <div>
                                <span class="sched-kind <?= e($class) ?>"><?= e($item['kind']) ?></span>
                                <?php if ($completed): ?><span class="badge text-bg-success ms-1">Completed</span><?php endif; ?>
                                <h3><?= e($item['title']) ?></h3>
                                <p><?= e($item['time']) ?> · <?= e($item['detail']) ?></p>
                                <small><?= e($item['location']) ?> · <?= e(personnel_schedule_status_label((string) $item['status'])) ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</section>
