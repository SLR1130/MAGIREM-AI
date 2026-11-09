<?php
namespace Tests\Unit;

use App\Services\RiskPhraseScreen;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RiskPhraseScreenTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_screen_level(string $message, string $expected): void
    {
        $result = (new RiskPhraseScreen())->screen($message);
        $this->assertSame($expected, $result['level'], $message);
    }

    public static function cases(): array
    {
        return [
            'passive + sarcasm emoji'   => ["i am so tired of everything 🙃 nobody would even notice if I disappeared, right?", 'severe'],
            'tagalog wish not to wake'  => ["haha sana di na lang ako nagising 😂", 'severe'],
            'dont want to be here'      => ["i dont want to be here anymore 🙃", 'severe'],
            'ayoko na alone'            => ['ayoko na', 'moderate'],
            'tagalog want to die'       => ['gusto ko nang mamatay', 'critical'],
            'two distress signals'      => ['wala na akong silbi, pagod na pagod na ako', 'severe'],
            'better off without me'     => ['everyone would be better off without me lol', 'severe'],
            'kill myself lol'           => ['i want to kill myself lol', 'critical'],
            'past tense'                => ['i used to want to die but im better now', 'moderate'],
            // must NOT be flagged
            'deadlines idiom'           => ["deadlines are killing me 😂", 'none'],
            'exam stress'               => ['i am so stressed about my exam tomorrow', 'none'],
            'negated english'           => ['i dont want to die', 'none'],
            'negated tagalog'           => ['hindi ko gustong mamatay', 'none'],
            'die of embarrassment (tl)' => ['gusto kong mamatay sa hiya', 'none'],
            'live with roommate'        => ['i dont want to live with my roommate anymore', 'none'],
        ];
    }
}
