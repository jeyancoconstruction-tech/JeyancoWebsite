<?php

namespace Tests\Concerns;

use App\Models\Attendance;

/**
 * For tests about how a day is priced, not about how it is reviewed.
 *
 * A day scanned in and out with nothing at the break waits for review and is
 * not paid until the office settles it (2026-09-27). Tests that scan a
 * straight-through day to check some other rule — early arrival, night
 * differential, overtime — accept it the way the office would, then read the
 * pay. AttendanceBreakReviewTest covers the review itself.
 */
trait AcceptsUnbrokenDays
{
    protected function acceptUnbrokenDays(): void
    {
        Attendance::where('needs_review', true)
            ->where('close_reason', Attendance::NO_BREAK)
            ->update(['needs_review' => false, 'close_reason' => Attendance::THROUGH]);
    }
}
