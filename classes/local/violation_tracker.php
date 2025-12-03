<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Session based helper to track proctoring violations.
 *
 * @package    quizaccess_videocapture
 * @copyright  2024 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_videocapture\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Tracks quiz proctoring violations using the Moodle session.
 */
class violation_tracker {

    /** @var string Session storage key. */
    private const SESSION_KEY = 'quizaccess_videocapture';

    /** @var string Canonical face violation source. */
    public const SOURCE_FACE = 'face';

    /** @var string Canonical audio violation source. */
    public const SOURCE_AUDIO = 'audio';

    /** @var array<string, string> Maps arbitrary inputs to canonical sources. */
    private const SOURCE_ALIASES = [
        self::SOURCE_FACE => self::SOURCE_FACE,
        'facerecognition' => self::SOURCE_FACE,
        self::SOURCE_AUDIO => self::SOURCE_AUDIO,
        'microphone' => self::SOURCE_AUDIO,
    ];

    /**
     * Reset the violation counters.
     *
     * @param string|null $source Optional source to reset. When null everything is reset.
     */
    public static function reset(?string $source = null): void {
        global $SESSION;

        if ($source === null) {
            $SESSION->{self::SESSION_KEY} = self::initial_state();
            return;
        }

        $state = self::bootstrap_state();
        $canonical = self::normalise_source($source);
        $state->{$canonical}->count = 0;
        self::store_state($state);
    }

    /**
     * Increment the violation counter.
     *
     * @param string $source Friendly identifier of the violation source.
     * @param int $maxallowed Configured maximum allowed violations.
     * @return array Contains current count, locked flag and remaining attempts.
     */
    public static function increment(string $source, int $maxallowed): array {
        $state = self::bootstrap_state();
        $canonical = self::normalise_source($source);

        $state->{$canonical}->count++;
        $state->lastsource = $canonical;

        self::store_state($state);

        return self::format_response($state, $maxallowed, $canonical);
    }

    /**
     * Get the current state without mutating it.
     *
     * @param int $maxallowed Configured maximum allowed violations.
     * @return array Contains current count, locked flag and remaining attempts.
     */
    public static function get_state(int $maxallowed, ?string $source = null): array {
        $state = self::bootstrap_state();

        $canonical = $source ? self::normalise_source($source) : null;

        return self::format_response($state, $maxallowed, $canonical);
    }

    /**
     * Ensure the internal session object exists and return it.
     *
     * @return \stdClass
     */
    private static function bootstrap_state(): \stdClass {
        global $SESSION;

        $legacycount = 0;
        if (isset($SESSION->videocapturefails)) {
            $legacycount = (int)$SESSION->videocapturefails;
            unset($SESSION->videocapturefails);
        }

        if (!isset($SESSION->{self::SESSION_KEY}) || !is_object($SESSION->{self::SESSION_KEY})) {
            $SESSION->{self::SESSION_KEY} = self::initial_state();
        }

        $state = $SESSION->{self::SESSION_KEY};

        if (!isset($state->face) || !is_object($state->face)) {
            $state->face = (object) [
                'count' => isset($state->count) ? (int)$state->count : $legacycount,
            ];
        } else if (!isset($state->face->count)) {
            $state->face->count = 0;
        }

        if (!isset($state->audio) || !is_object($state->audio)) {
            $audiolegacy = 0;
            if (isset($state->sources) && is_array($state->sources)) {
                $audiolegacy += (int)($state->sources['audio'] ?? 0);
                $audiolegacy += (int)($state->sources['microphone'] ?? 0);
            }
            $state->audio = (object) [
                'count' => $audiolegacy,
            ];
        } else if (!isset($state->audio->count)) {
            $state->audio->count = 0;
        }

        if (!isset($state->lastsource)) {
            $state->lastsource = self::SOURCE_FACE;
        }

        // Drop legacy flat counters after migrating.
        unset($state->count, $state->sources);

        $SESSION->{self::SESSION_KEY} = $state;

        return $state;
    }

    /**
     * Persist the state back into the session.
     *
     * @param \stdClass $state Current state.
     */
    private static function store_state(\stdClass $state): void {
        global $SESSION;

        $SESSION->{self::SESSION_KEY} = $state;
    }

    /**
     * Build a normalized response array.
     *
     * @param int $count Current violation count.
     * @param int $maxallowed Maximum allowed violations.
     * @return array
     */
    private static function format_response(\stdClass $state, int $maxallowed, ?string $activesource = null): array {
        $face = self::format_channel($state->face->count ?? 0, $maxallowed);
        $audio = self::format_channel($state->audio->count ?? 0, $maxallowed);

        $response = [
            'faceviolations' => $face,
            'audioviolations' => $audio,
        ];

        $response['locked'] = $face['locked'] || $audio['locked'];

        $source = $activesource ?? ($state->lastsource ?? self::SOURCE_FACE);
        $response['source'] = $source;

        $selected = $source === self::SOURCE_AUDIO ? $audio : $face;
        $response['count'] = $selected['count'];
        $response['remaining'] = $selected['remaining'];
        $response['remainingattempts'] = $selected['remaining'];

        return $response;
    }

    /**
     * Normalise a source identifier to a canonical value.
     *
     * @param string|null $source Raw source string.
     * @return string
     */
    private static function normalise_source(?string $source): string {
        $clean = strtolower((string)$source);
        if (isset(self::SOURCE_ALIASES[$clean])) {
            return self::SOURCE_ALIASES[$clean];
        }
        return self::SOURCE_FACE;
    }

    /**
     * Provide the default session payload.
     *
     * @return \stdClass
     */
    private static function initial_state(): \stdClass {
        return (object) [
            self::SOURCE_FACE => (object) ['count' => 0],
            self::SOURCE_AUDIO => (object) ['count' => 0],
            'lastsource' => self::SOURCE_FACE,
        ];
    }

    /**
     * Helper to format the per-source response block.
     *
     * @param int $count Current count.
     * @param int $maxallowed Threshold before locking.
     * @return array
     */
    private static function format_channel(int $count, int $maxallowed): array {
        $remaining = max($maxallowed - $count, 0);
        $locked = $maxallowed > 0 && $count >= $maxallowed;

        return [
            'count' => $count,
            'locked' => $locked,
            'remaining' => $remaining,
        ];
    }
}
