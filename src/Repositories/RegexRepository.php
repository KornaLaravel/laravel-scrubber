<?php

namespace YorCreative\Scrubber\Repositories;

use Illuminate\Support\Collection;

class RegexRepository
{
    public function __construct(
        protected Collection $regexCollection
    ) {
    }

    public static function checkAndSanitize(string $regex, string $content, int &$hits = 0): string
    {
        return preg_replace("~$regex~i", config('scrubber.redaction'), $content, -1, $hits);
    }

    public static function check(string $regex, string $content): int
    {
        return preg_match_all("~$regex~i", $content);
    }

    public function getRegexCollection(): Collection
    {
        return $this->regexCollection;
    }
}
