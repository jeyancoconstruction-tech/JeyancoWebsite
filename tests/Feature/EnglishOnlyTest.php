<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The system UI is English, everywhere, with no way to switch it.
 *
 * Two things were making it Tagalog. The visible half was strings written in
 * Tagalog directly — map hints, kiosk errors, tooltips, the AI assistant's own
 * instructions to answer in Taglish. The half that mattered more was a
 * Language picker in System Settings whose Tagalog option loaded lang/tl.json,
 * 704 translated lines, and turned every __() string in the app over at once.
 *
 * This sweep is the guard. It reads the sources rather than a response,
 * because the point is that no file anywhere reintroduces Tagalog, and only
 * looking at all of them can say that.
 */
class EnglishOnlyTest extends TestCase
{
    /**
     * Words that are unmistakably Tagalog and cannot be an English word, an
     * HTML attribute or a variable name.
     *
     * Deliberately not included: "at", "sa", "na", "may", "lang", "ni". Each
     * is either an English word, part of lang="en", or common enough in code
     * that the sweep would cry wolf and stop being read.
     */
    private const TAGALOG = [
        'ang', 'mga', 'walang', 'wala', 'hindi', 'kung', 'ito', 'nang', 'dahil', 'bawat',
        'ngayon', 'kahapon', 'araw', 'petsa', 'oras', 'pangalan', 'bago', 'bagong',
        'pindutin', 'piliin', 'buksan', 'isara', 'tanggalin', 'alisin', 'burahin',
        'idagdag', 'baguhin', 'ayusin', 'itakda', 'subukan', 'sabihin', 'tingnan',
        'manggagawa', 'empleyado', 'sahod', 'sweldo', 'kaltas', 'bawas', 'utang',
        'daliri', 'lokasyon', 'naman', 'muna', 'pasensya', 'paumanhin', 'sumagot',
        'nakarehistro', 'matatanggap', 'naitakdang', 'ninakaw', 'inilipat',
        'paano', 'magkano', 'sino', 'saan', 'taglish', 'tagalog',
    ];

    /**
     * The official Filipino names of Philippine national holidays.
     *
     * "Araw ng Kagitingan" is what the holiday is called — it is a proper
     * noun, already carrying its English gloss beside it, and translating it
     * would be renaming a national holiday rather than translating an
     * interface.
     */
    private const PROPER_NOUNS = [
        'Araw ng Kagitingan',
    ];

    /** Comments stripped: a Tagalog code comment is not user-facing text. */
    private function withoutComments(string $src): string
    {
        $src = preg_replace('/\{\{--.*?--\}\}/s', '', $src);   // Blade
        $src = preg_replace('#/\*.*?\*/#s', '', $src);         // block
        $src = preg_replace('#^\s*(//|\#).*$#m', '', $src);    // line
        return str_replace(self::PROPER_NOUNS, '', $src);
    }

    private function scan(array $files): array
    {
        $re = '/\b(' . implode('|', array_map('preg_quote', self::TAGALOG)) . ')\b/i';
        $offenders = [];

        foreach ($files as $name => $src) {
            foreach (explode("\n", $this->withoutComments($src)) as $i => $line) {
                if (preg_match($re, $line, $m)) {
                    $offenders[] = sprintf('%s:%d  "%s"  in: %s', $name, $i + 1, $m[1], trim(substr($line, 0, 90)));
                }
            }
        }
        return $offenders;
    }

    private function sources(string $dir, string $ext): array
    {
        $out = [];
        foreach (File::allFiles($dir) as $file) {
            if (! str_ends_with($file->getFilename(), $ext)) continue;
            $out[$file->getRelativePathname()] = $file->getContents();
        }
        return $out;
    }

    public function test_no_view_carries_tagalog_user_facing_text(): void
    {
        $offenders = $this->scan($this->sources(resource_path('views'), '.blade.php'));

        $this->assertSame([], $offenders, "Tagalog in a view:\n" . implode("\n", $offenders));
    }

    /** Controllers hold the flash messages, the kiosk replies and the AI prompt. */
    public function test_no_controller_or_model_carries_tagalog_user_facing_text(): void
    {
        $offenders = $this->scan($this->sources(app_path(), '.php'));

        $this->assertSame([], $offenders, "Tagalog in app code:\n" . implode("\n", $offenders));
    }

    /**
     * The switch, not the strings. This is the one that could undo everything
     * above with a single click.
     */
    public function test_there_is_no_language_switch_left(): void
    {
        $this->assertFileDoesNotExist(
            base_path('lang/tl.json'),
            'lang/tl.json turned every __() string Tagalog; it must not come back'
        );

        $appearance = File::get(resource_path('views/settings/appearance.blade.php'));
        $this->assertStringNotContainsString('name="locale"', $appearance, 'the Language picker should be gone');

        $middleware = File::get(app_path('Http/Middleware/SetLocale.php'));
        $this->assertStringContainsString("setLocale('en')", $middleware, 'the locale should be pinned to English');
        $this->assertStringNotContainsString('SystemSetting::current()->locale', $middleware);
    }

    /** The kiosk assistant was told to reply in Taglish; it is told English now. */
    public function test_the_kiosk_assistant_is_told_to_answer_in_english(): void
    {
        $src = File::get(app_path('Http/Controllers/KioskAiController.php'));

        $this->assertStringContainsString('Answer in English', $src);
        $this->assertStringNotContainsString('Taglish', $src);
    }
}
