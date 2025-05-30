<?php

namespace YorCreative\Scrubber\Tests\Feature;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use YorCreative\Scrubber\Repositories\RegexRepository;
use YorCreative\Scrubber\Scrubber;
use YorCreative\Scrubber\Tests\TestCase;

#[Group('RegexRepository')]
#[Group('LogRecord')]
#[Group('Feature')]
class ScrubMessageTest extends TestCase
{
    public function test_it_can_detect_a_single_piece_of_sensitive_data_and_sanitize_it()
    {
        $message = 'Something something, here is the slack token {slack_token}';

        $expected = str_replace('{slack_token}', config('scrubber.redaction'), $message);

        $message = str_replace(
            '{slack_token}',
            app(RegexRepository::class)->getRegexCollection()->get('slack_token')->getTestableString(),
            $message
        );

        $sanitizedMessage = Scrubber::processMessage($message);

        $this->assertEquals($expected, $sanitizedMessage);
    }

    public function test_it_can_detect_a_multiple_pieces_of_sensitive_data_and_sanitize_them()
    {
        $message = 'here is the slack token {slack_token} and the google api token {google_api}';

        $expected = str_replace('{slack_token}', config('scrubber.redaction'), $message);
        $expected = str_replace('{google_api}', config('scrubber.redaction'), $expected);

        $message = str_replace(
            '{slack_token}',
            app(RegexRepository::class)->getRegexCollection()->get('slack_token')->getTestableString(),
            $message
        );

        $message = str_replace(
            '{google_api}',
            app(RegexRepository::class)->getRegexCollection()->get('google_api')->getTestableString(),
            $message
        );

        $sanitizedMessage = Scrubber::processMessage($message);

        $this->assertEquals($expected, $sanitizedMessage);
    }

    public function test_it_can_process_log_record()
    {
        $message = 'Something something, here is the slack token {slack_token}';

        $expectedMessage = str_replace('{slack_token}', config('scrubber.redaction'), $message);

        $rawMessage = str_replace(
            '{slack_token}',
            app(RegexRepository::class)->getRegexCollection()->get('slack_token')->getTestableString(),
            $message
        );

        $rawContext = [
            'one' => $rawMessage,
            'two' => $rawMessage,
            'three' => [
                'four' => $rawMessage,
            ],
        ];

        $expectedContext = [
            'one' => $expectedMessage,
            'two' => $expectedMessage,
            'three' => [
                'four' => $expectedMessage,
            ],
        ];

        $dateTimeImmutable = Carbon::now()->toDateTimeImmutable();

        $logRecord = $this->getTestLogRecord($dateTimeImmutable, $rawMessage, $rawContext);
        $expectedLogRecord = $this->getTestLogRecord($dateTimeImmutable, $expectedMessage, $expectedContext);

        $sanitizedMessage = Scrubber::processMessage($logRecord);

        $this->assertEquals($expectedLogRecord, $sanitizedMessage);
    }
}
