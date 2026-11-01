<?php

namespace App\Support\Seo;

/**
 * NOV-PUBLIC-03A: минимальный разбор правил robots.txt для группы «User-agent: *».
 *
 * Семантика как у Google/Яндекса: правило — префикс пути; «*» — любая
 * последовательность, «$» на конце — конец адреса; побеждает самое длинное
 * совпавшее правило, при равной длине — Allow. Пустой Disallow ничего не
 * запрещает. Класс только читает переданный текст: ничего не загружает и не
 * заменяет настоящий парсер поисковика. robots.txt — не контроль доступа.
 */
class RobotsRules
{
    /** @var array<int, array{allow: bool, pattern: string, regex: string}> */
    private array $rules = [];

    public static function fromFile(string $path): self
    {
        return new self(is_file($path) ? (string) file_get_contents($path) : '');
    }

    public function __construct(string $contents)
    {
        $inWildcardGroup = false;
        $previousWasUserAgent = false;

        foreach (preg_split('/\R/', $contents) as $line) {
            $line = trim((string) preg_replace('/\s+#.*$|^#.*$/', '', $line));

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$directive, $value] = array_map('trim', explode(':', $line, 2));
            $directive = strtolower($directive);

            if ($directive === 'user-agent') {
                // Подряд идущие User-agent образуют одну группу; после правил начинается новая.
                $inWildcardGroup = ($previousWasUserAgent && $inWildcardGroup) || $value === '*';
                $previousWasUserAgent = true;

                continue;
            }

            $previousWasUserAgent = false;

            if (! in_array($directive, ['allow', 'disallow'], true)) {
                continue;
            }

            if ($inWildcardGroup && $value !== '') {
                $this->rules[] = [
                    'allow' => $directive === 'allow',
                    'pattern' => $value,
                    'regex' => $this->toRegex($value),
                ];
            }
        }
    }

    /** @return array<int, string> Значения Disallow группы «*». */
    public function disallowPatterns(): array
    {
        return array_values(array_map(
            fn (array $r) => $r['pattern'],
            array_filter($this->rules, fn (array $r) => ! $r['allow'])
        ));
    }

    /** Разрешён ли обход адреса (путь с query, начинается с «/»). */
    public function allows(string $pathWithQuery): bool
    {
        $best = null;

        foreach ($this->rules as $rule) {
            if (preg_match($rule['regex'], $pathWithQuery) !== 1) {
                continue;
            }

            if ($best === null
                || strlen($rule['pattern']) > strlen($best['pattern'])
                || (strlen($rule['pattern']) === strlen($best['pattern']) && $rule['allow'])) {
                $best = $rule;
            }
        }

        return $best === null || $best['allow'];
    }

    private function toRegex(string $pattern): string
    {
        $anchored = str_ends_with($pattern, '$');
        $body = $anchored ? substr($pattern, 0, -1) : $pattern;
        $regex = str_replace('\*', '.*', preg_quote($body, '#'));

        return '#^' . $regex . ($anchored ? '$' : '') . '#u';
    }
}
