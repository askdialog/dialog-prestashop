<?php

namespace Dialog\AskDialog\Tests\Unit\Service\CartHandoff;

use Dialog\AskDialog\Service\CartHandoff\HandoffPayload;
use Dialog\AskDialog\Service\CartHandoff\InvalidHandoffException;
use PHPUnit\Framework\TestCase;

class HandoffPayloadTest extends TestCase
{
    /** @var array */
    private static $vectors;

    public static function setUpBeforeClass(): void
    {
        self::$vectors = json_decode(file_get_contents(__DIR__ . '/../../../fixtures/handoff-v1-vectors.json'), true);
    }

    public function testDerivesTheSigningKeyOfTheVectors()
    {
        $this->assertSame(
            self::$vectors['derivedKeyHex'],
            hash_hmac('sha256', 'dialog-cart-handoff-v1', self::$vectors['privateKey'])
        );
    }

    public function testVerifiesEveryVector()
    {
        foreach (self::$vectors['cases'] as $case) {
            $expected = $case['payload'];
            $payload = HandoffPayload::verify($case['p'], $case['s'], self::$vectors['privateKey'], $expected['exp'] - 900);

            $lines = [];
            foreach ($expected['items'] as $item) {
                $lines[] = [
                    'id' => (int) $item['id'],
                    'variant' => isset($item['variant']) ? (int) $item['variant'] : 0,
                    'qty' => $item['qty'],
                ];
            }
            $this->assertSame($lines, $payload->getLines(), $case['name']);
            $this->assertSame($expected['origin'], $payload->getOrigin(), $case['name']);
            $this->assertSame($expected['ref'], $payload->getRef(), $case['name']);
            $this->assertSame($expected['utm'], $payload->getUtm(), $case['name']);
        }
    }

    public function testRefusesTheTamperedVector()
    {
        $tampered = self::$vectors['tampered'];

        $this->assertRefused('signature', $tampered['p'], $tampered['s'], $this->validNow());
    }

    public function testRefusesAnotherKey()
    {
        $case = self::$vectors['cases'][0];

        $this->expectException(InvalidHandoffException::class);
        $this->expectExceptionMessage('signature');
        HandoffPayload::verify($case['p'], $case['s'], 'another-private-key', $this->validNow());
    }

    public function testRefusesAnUppercaseSignature()
    {
        $case = self::$vectors['cases'][0];

        $this->assertRefused('signature', $case['p'], strtoupper($case['s']), $this->validNow());
    }

    public function testRefusesAMissingSignatureOrKey()
    {
        $case = self::$vectors['cases'][0];

        $this->assertRefused('missing', $case['p'], '', $this->validNow());
        $this->expectException(InvalidHandoffException::class);
        HandoffPayload::verify($case['p'], $case['s'], '', $this->validNow());
    }

    public function testAcceptsALinkUpToOneMinuteAfterItsExpiry()
    {
        $case = self::$vectors['cases'][0];
        $exp = $case['payload']['exp'];

        HandoffPayload::verify($case['p'], $case['s'], self::$vectors['privateKey'], $exp + 60);
        $this->assertRefused('expired', $case['p'], $case['s'], $exp + 61);
    }

    public function testRefusesALinkThatWouldLiveLongerThanSigned()
    {
        $case = self::$vectors['cases'][0];
        $exp = $case['payload']['exp'];

        HandoffPayload::verify($case['p'], $case['s'], self::$vectors['privateKey'], $exp - 960);
        $this->assertRefused('too_far', $case['p'], $case['s'], $exp - 961);
    }

    public function testRefusesAnotherVersion()
    {
        foreach ([2, 0, '1', null] as $version) {
            $this->assertRefusedPayload('version', ['v' => $version] + $this->payload());
        }
        $payload = $this->payload();
        unset($payload['v']);
        $this->assertRefusedPayload('version', $payload);
    }

    public function testRefusesMalformedLines()
    {
        $invalid = [
            [],
            array_fill(0, 51, ['id' => '1', 'qty' => 1]),
            [['id' => 12, 'qty' => 1]],
            [['id' => '0', 'qty' => 1]],
            [['id' => '12abc', 'qty' => 1]],
            [['id' => '12', 'variant' => 34, 'qty' => 1]],
            [['id' => '12', 'variant' => '', 'qty' => 1]],
            [['id' => '12', 'qty' => 0]],
            [['id' => '12', 'qty' => 100]],
            [['id' => '12', 'qty' => '2']],
            [['id' => '12']],
            ['12'],
        ];
        foreach ($invalid as $items) {
            $this->assertRefusedPayload('items', ['items' => $items] + $this->payload());
        }
        $this->assertRefusedPayload('items', ['items' => ['a' => ['id' => '1', 'qty' => 1]]] + $this->payload());
    }

    public function testAcceptsFiftyLines()
    {
        $payload = HandoffPayload::verify(
            ...$this->sign(['items' => array_fill(0, 50, ['id' => '1', 'qty' => 99])] + $this->payload())
        );

        $this->assertCount(50, $payload->getLines());
    }

    public function testRefusesAMalformedOriginRefOrExpiry()
    {
        $this->assertRefusedPayload('origin', ['origin' => 'Dialog Plugin'] + $this->payload());
        $this->assertRefusedPayload('ref', ['ref' => 'not a ref'] + $this->payload());
        $this->assertRefusedPayload('ref', ['ref' => str_repeat('a', 65)] + $this->payload());
        $this->assertRefusedPayload('malformed', ['exp' => '1767225600'] + $this->payload());
    }

    public function testRefusesAPayloadThatIsNotBase64UrlJson()
    {
        $key = hash_hmac('sha256', 'dialog-cart-handoff-v1', self::$vectors['privateKey'], true);

        $this->assertRefused('malformed', 'a+b/', hash_hmac('sha256', 'a+b/', $key), $this->validNow());
        $notJson = rtrim(strtr(base64_encode('not json'), '+/', '-_'), '=');
        $this->assertRefused('malformed', $notJson, hash_hmac('sha256', $notJson, $key), $this->validNow());
    }

    public function testKeepsOnlyKnownUtmWithSafeValues()
    {
        $payload = HandoffPayload::verify(...$this->sign(['utm' => [
            'utm_source' => 'chatgpt.com',
            'utm_medium' => 'dialog plugin',
            'utm_campaign' => ['my-shop'],
            'utm_term' => str_repeat('a', 101),
            'utm_content' => 'card_1',
            'gclid' => 'abc',
        ]] + $this->payload()));

        $this->assertSame(['utm_source' => 'chatgpt.com', 'utm_content' => 'card_1'], $payload->getUtm());
    }

    public function testAcceptsALinkWithoutUtm()
    {
        $payload = $this->payload();
        unset($payload['utm']);

        $this->assertSame([], HandoffPayload::verify(...$this->sign($payload))->getUtm());
    }

    private function payload(): array
    {
        return self::$vectors['cases'][0]['payload'];
    }

    private function validNow(): int
    {
        return $this->payload()['exp'] - 900;
    }

    /**
     * The arguments of verify() for a payload signed as the monolith signs it
     */
    private function sign(array $payload): array
    {
        $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $key = hash_hmac('sha256', 'dialog-cart-handoff-v1', self::$vectors['privateKey'], true);

        return [$encoded, hash_hmac('sha256', $encoded, $key), self::$vectors['privateKey'], $this->validNow()];
    }

    private function assertRefusedPayload(string $reason, array $payload)
    {
        list($encoded, $signature) = $this->sign($payload);
        $this->assertRefused($reason, $encoded, $signature, $this->validNow());
    }

    private function assertRefused(string $reason, string $encoded, string $signature, int $now)
    {
        try {
            HandoffPayload::verify($encoded, $signature, self::$vectors['privateKey'], $now);
        } catch (InvalidHandoffException $e) {
            $this->assertSame($reason, $e->getMessage());

            return;
        }
        $this->fail('The link was accepted, expected a refusal: ' . $reason);
    }
}
