<?php

if (! function_exists('inr')) {
    function inr($amount, int $decimals = 2): string
    {
        if (! is_numeric($amount)) {
            $amount = 0;
        }

        return '₹' . number_format((float) $amount, $decimals);
    }
}

/**
 * Returns Tailwind text-color classes for a P&L value.
 *  - profit → vivid emerald with glow
 *  - loss   → vivid rose/red with glow
 *  - zero   → neutral
 */
if (! function_exists('pnl_class')) {
    function pnl_class($value, string $neutral = 'text-gray-500 dark:text-slate-400'): string
    {
        if (! is_numeric($value) || (float) $value == 0.0) {
            return $neutral;
        }

        return (float) $value > 0
            ? 'pnl-positive text-emerald-600 dark:text-emerald-400 font-bold'
            : 'pnl-negative text-rose-600 dark:text-rose-400 font-bold';
    }
}

/**
 * Returns badge background + text classes (pill chip) for a P&L value.
 */
if (! function_exists('pnl_badge_class')) {
    function pnl_badge_class($value): string
    {
        if (! is_numeric($value) || (float) $value == 0.0) {
            return 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300';
        }

        return (float) $value > 0
            ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800/60'
            : 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800/60';
    }
}

/**
 * Formats a P&L value with a leading + / - sign.
 */
if (! function_exists('pnl_formatted')) {
    function pnl_formatted($value, bool $showSign = true): string
    {
        if (! is_numeric($value)) {
            return '-';
        }

        $amount = (float) $value;

        if ($amount == 0.0) {
            return inr(0);
        }

        $prefix = $showSign ? ($amount > 0 ? '+' : '-') : '';

        return $prefix . inr(abs($amount));
    }
}
