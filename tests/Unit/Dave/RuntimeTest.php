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

namespace Discord\Tests\Unit\Dave;

use Discord\Voice\Dave\Runtime;
use PHPUnit\Framework\TestCase;

final class RuntimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Runtime::configureCallbacks();
    }

    public function testEncryptDecryptAndCommitWelcomeCallbacksAreUsed(): void
    {
        Runtime::configureCallbacks(
            fn (string $frame, int $protocolVersion): ?string => "enc:{$protocolVersion}:{$frame}",
            fn (string $frame, int $protocolVersion): string => "dec:{$protocolVersion}:{$frame}",
            fn (string $payload, int $protocolVersion): ?string => "commit:{$protocolVersion}:{$payload}"
        );

        self::assertSame('enc:1:frame', Runtime::encryptMediaFrame('frame', 1));
        self::assertSame('dec:1:frame', Runtime::decryptMediaFrame('frame', 1));
        self::assertSame('commit:1:proposals', Runtime::buildMlsCommitWelcome('proposals', 1));
    }

    public function testProtocolZeroFallsBackToPassthroughOrNull(): void
    {
        self::assertSame('frame', Runtime::encryptMediaFrame('frame', 0));
        self::assertSame('frame', Runtime::decryptMediaFrame('frame', 0));
        self::assertNull(Runtime::buildMlsCommitWelcome('proposals', 0));
    }
}
