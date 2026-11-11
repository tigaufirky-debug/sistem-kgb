<?php

function calculateNextKgbDate($tmtMulai, $latestApprovedPeriode)
{
    $seed = $latestApprovedPeriode ?: $tmtMulai;
    if (!$seed) {
        return null;
    }

    try {
        $next = (new DateTimeImmutable($seed))->add(new DateInterval('P2Y'));
        $today = new DateTimeImmutable(date('Y-m-d'));

        while ($next < $today) {
            $next = $next->add(new DateInterval('P2Y'));
        }

        return $next->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

function kgbYearFromDate($date)
{
    if (!$date) {
        return '-';
    }

    try {
        return (new DateTimeImmutable($date))->format('Y');
    } catch (Exception $e) {
        return '-';
    }
}
