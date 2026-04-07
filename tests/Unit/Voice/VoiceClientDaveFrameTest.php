<?php

declare(strict_types=1);

/*
 * This file is a part of the DiscordPHP project.
 *
 * Copyright (c) 2015-2022 David Cole <david.cole1340@gmail.com>
 * Copyright (c) 2020-present Valithor Obsidion <valithor@discordphp.org>
 * Copyright (c) 2025-present Alexandre Candeias (Sky) <sky@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace Discord\Tests\Unit\Voice;

use Discord\Discord;
use Discord\Voice\Client\UDP;
use Discord\Voice\Client\WS;
use Discord\Voice\Dave\Runtime;
use Discord\Voice\Dave\State;
use Discord\Voice\VoiceClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class VoiceClientDaveFrameTest extends TestCase
{
    protected function tearDown(): void
    {
        Runtime::reset();
    }

    public function testEncryptAndDecryptPassThroughWhenDaveProtocolDisabled(): void
    {
        $voiceClient = $this->makeVoiceClientWithProtocolVersion(0);

        self::assertSame('audio', $voiceClient->encryptDaveFrame('audio'));
        self::assertSame('audio', $voiceClient->decryptDaveFrame('audio'));
    }

    public function testEncryptAndDecryptUseRuntimeCallbacksWhenDaveProtocolEnabled(): void
    {
        Runtime::configureCallbacks(
            fn (string $frame, int $protocolVersion): ?string => "enc:{$protocolVersion}:{$frame}",
            fn (string $frame, int $protocolVersion): string => "dec:{$protocolVersion}:{$frame}"
        );

        $voiceClient = $this->makeVoiceClientWithProtocolVersion(1);

        self::assertSame('enc:1:audio', $voiceClient->encryptDaveFrame('audio'));
        self::assertSame('dec:1:audio', $voiceClient->decryptDaveFrame('audio'));
    }

    public function testDecryptReturnsFalseWhenRuntimeCannotDecryptEnabledDaveProtocol(): void
    {
        Runtime::configureCallbacks(
            null,
            fn (string $frame, int $protocolVersion): false => false
        );

        $voiceClient = $this->makeVoiceClientWithProtocolVersion(1);

        self::assertFalse($voiceClient->decryptDaveFrame('audio'));
    }

    private function makeVoiceClientWithProtocolVersion(int $protocolVersion): VoiceClient
    {
        $voiceClient = (new \ReflectionClass(VoiceClient::class))->newInstanceWithoutConstructor();
        $discord = (new \ReflectionClass(Discord::class))->newInstanceWithoutConstructor();
        $ws = (new \ReflectionClass(WS::class))->newInstanceWithoutConstructor();
        $udp = (new \ReflectionClass(UDP::class))->newInstanceWithoutConstructor();

        $state = new State();
        $state->setProtocolVersion($protocolVersion);

        $loggerProperty = new \ReflectionProperty(Discord::class, 'logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($discord, new NullLogger());

        $daveStateProperty = new \ReflectionProperty(WS::class, 'daveState');
        $daveStateProperty->setAccessible(true);
        $daveStateProperty->setValue($ws, $state);

        $udp->ws = $ws;
        $voiceClient->udp = $udp;
        $voiceClient->discord = $discord;

        return $voiceClient;
    }
}
